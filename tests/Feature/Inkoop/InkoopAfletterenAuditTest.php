<?php

use App\Enums\ActivityType;
use App\Enums\OrderItemStatus;
use App\Models\Clinic;
use App\Models\ClinicDepartment;
use App\Models\Inkoop\InkoopInvoice;
use App\Models\Inkoop\InkoopInvoiceItem;
use App\Models\Inkoop\InkoopPerson;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Product\Models\Product;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->actingAs($this->admin, 'user');

    $this->clinic = Clinic::factory()->create();
    $dept = ClinicDepartment::factory()->create(['clinic_id' => $this->clinic->id]);
    $this->resource = Resource::factory()->create(['clinic_department_id' => $dept->id]);

    $this->invoice = InkoopInvoice::create([
        'clinic_id'      => $this->clinic->id,
        'pdf_path'       => 'test/test.pdf',
        'invoice_number' => '2025-00123',
    ]);

    $this->crmPerson = Person::factory()->create();

    $this->inkoopPerson = InkoopPerson::create([
        'clinic_id'  => $this->clinic->id,
        'invoice_id' => $this->invoice->id,
        'firstname'  => $this->crmPerson->first_name,
        'lastname'   => $this->crmPerson->last_name,
        'crm_id'     => $this->crmPerson->id,
    ]);

    $this->order = Order::factory()->create();
});

/** Link one order item to a fresh invoice line via the real save flow, return the invoice item. */
function linkAfletterOrderItem($test, OrderItem $orderItem, float $price = 150.0): InkoopInvoiceItem
{
    createMainPurchasePrice($orderItem, $price);

    $invoiceItem = InkoopInvoiceItem::create([
        'clinic_id'         => $test->clinic->id,
        'inkoop_invoice_id' => $test->invoice->id,
        'person_id'         => $test->inkoopPerson->id,
        'name'              => 'Scan',
        'description'       => 'Scan',
        'price'             => $price,
    ]);

    $test->put(route('admin.inkoop.save-product-crm-ids', $test->invoice->id), [
        'crm_ids' => [
            $test->inkoopPerson->id => [
                $invoiceItem->id => [$orderItem->id],
            ],
        ],
    ])->assertRedirect();

    return $invoiceItem;
}

function afletterActivities(int $orderId)
{
    return Activity::where('order_id', $orderId)
        ->where('type', ActivityType::SYSTEM->value)
        ->where('additional->afletteren', true)
        ->get();
}

it('logs a system activity on the order when a crm link is reset', function () {
    $orderItem = createOrderItemForClinic($this->order, $this->crmPerson, $this->resource, OrderItemStatus::WON->value);
    $invoiceItem = linkAfletterOrderItem($this, $orderItem);

    expect(afletterActivities($this->order->id))->toHaveCount(0);

    $this->put(route('admin.inkoop.reset-crm-id', [$this->invoice->id, $invoiceItem->id]))
        ->assertRedirect();

    $activities = afletterActivities($this->order->id);
    expect($activities)->toHaveCount(1);

    $activity = $activities->first();
    expect($activity->user_id)->toBe($this->admin->id)
        ->and($activity->additional['screen'])->toBe('Inkoop – stap 2')
        ->and($activity->comment)->toContain('losgekoppeld')
        ->and($activity->comment)->toContain('2025-00123')
        ->and($activity->comment)->toContain("#{$orderItem->id}");
});

it('logs one activity per order when a reset spans multiple orders', function () {
    $orderA = $this->order;
    $orderB = Order::factory()->create();

    $itemA = createOrderItemForClinic($orderA, $this->crmPerson, $this->resource, OrderItemStatus::WON->value);
    $itemB = createOrderItemForClinic($orderB, $this->crmPerson, $this->resource, OrderItemStatus::WON->value);
    createMainPurchasePrice($itemA, 100);
    createMainPurchasePrice($itemB, 100);

    $invoiceItem = InkoopInvoiceItem::create([
        'clinic_id'         => $this->clinic->id,
        'inkoop_invoice_id' => $this->invoice->id,
        'person_id'         => $this->inkoopPerson->id,
        'name'              => 'Dubbel',
        'description'       => 'Dubbel',
        'price'             => 200,
    ]);

    $this->put(route('admin.inkoop.save-product-crm-ids', $this->invoice->id), [
        'crm_ids' => [
            $this->inkoopPerson->id => [
                $invoiceItem->id => [$itemA->id, $itemB->id],
            ],
        ],
    ])->assertRedirect();

    $this->put(route('admin.inkoop.reset-crm-id', [$this->invoice->id, $invoiceItem->id]))
        ->assertRedirect();

    expect(afletterActivities($orderA->id))->toHaveCount(1)
        ->and(afletterActivities($orderB->id))->toHaveCount(1);
});

it('logs a forced-received activity and skips already-forced items on a re-run', function () {
    $orderItem = createOrderItemForClinic($this->order, $this->crmPerson, $this->resource, OrderItemStatus::WON->value);
    $invoiceItem = linkAfletterOrderItem($this, $orderItem);

    $this->put(route('admin.inkoop.force-received-bulk', $this->invoice->id), [
        'force_item_ids' => [$invoiceItem->id],
    ])->assertRedirect();

    $activities = afletterActivities($this->order->id);
    expect($activities)->toHaveCount(1)
        ->and($activities->first()->comment)->toContain('geforceerd');

    // Second run: item is already forced -> no new activity.
    $this->put(route('admin.inkoop.force-received-bulk', $this->invoice->id), [
        'force_item_ids' => [$invoiceItem->id],
    ])->assertRedirect();

    expect(afletterActivities($this->order->id))->toHaveCount(1);
});

it('logs an invoice price change made from the order afletteren tab', function () {
    $orderItem = createOrderItemForClinic($this->order, $this->crmPerson, $this->resource, OrderItemStatus::WON->value);

    $this->patch(route('admin.order_items.invoice_price.update', $orderItem->id), [
        'purchase_price_misc' => 250,
    ])->assertOk();

    $activities = afletterActivities($this->order->id);
    expect($activities)->toHaveCount(1);
    expect($activities->first()->additional['screen'])->toBe('Order – Afletteren-tab');
    expect($activities->first()->comment)->toContain('→');

    // Same value again -> no new activity.
    $this->patch(route('admin.order_items.invoice_price.update', $orderItem->id), [
        'purchase_price_misc' => 250,
    ])->assertOk();

    expect(afletterActivities($this->order->id))->toHaveCount(1);
});

it('logs force toggling on and off from the order afletteren tab', function () {
    $orderItem = createOrderItemForClinic($this->order, $this->crmPerson, $this->resource, OrderItemStatus::WON->value);

    $this->patch(route('admin.order_items.force_received', $orderItem->id), ['force' => true])->assertOk();
    $this->patch(route('admin.order_items.force_received', $orderItem->id), ['force' => false])->assertOk();

    $activities = afletterActivities($this->order->id)->sortBy('id')->values();
    expect($activities)->toHaveCount(2)
        ->and($activities[0]->comment)->toContain('geforceerd als geheel ontvangen')
        ->and($activities[1]->comment)->toContain('forcering verwijderd');
});

it('logs an invoice price change made from the order item edit form', function () {
    $product = Product::factory()->create();
    $orderItem = OrderItem::factory()->create([
        'order_id'   => $this->order->id,
        'product_id' => $product->id,
        'person_id'  => $this->crmPerson->id,
    ]);

    $this->post(route('admin.order_items.update', $orderItem->id), [
        'order_id'                    => $this->order->id,
        'product_id'                  => $product->id,
        'person_id'                   => $this->crmPerson->id,
        'quantity'                    => 1,
        'total_price'                 => 100,
        'status'                      => 'new',
        'invoice_purchase_price_misc' => 75,
        '_method'                     => 'put',
    ])->assertRedirect();

    $activities = afletterActivities($this->order->id);
    expect($activities)->toHaveCount(1)
        ->and($activities->first()->additional['screen'])->toBe('Orderregel – bewerken');
});
