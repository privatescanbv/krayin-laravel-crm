<?php

use App\Enums\OrderItemStatus;
use App\Enums\PurchasePriceType;
use App\Models\Clinic;
use App\Models\ClinicDepartment;
use App\Models\Inkoop\InkoopInvoice;
use App\Models\Inkoop\InkoopInvoiceItem;
use App\Models\Inkoop\InkoopInvoiceItemCrmProduct;
use App\Models\Inkoop\InkoopPerson;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Resource;
use App\Models\ResourceOrderItem;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Models\Person;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create(), 'user');

    $this->clinic = Clinic::factory()->create();
    $dept = ClinicDepartment::factory()->create(['clinic_id' => $this->clinic->id]);
    $this->resource = Resource::factory()->create(['clinic_department_id' => $dept->id]);

    $this->invoice = InkoopInvoice::create([
        'clinic_id' => $this->clinic->id,
        'pdf_path'  => 'test/test.pdf',
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

function createOrderItemForClinic(
    Order $order,
    Person $person,
    Resource $resource,
    string $status,
    ?Carbon $from = null,
): OrderItem {
    $from ??= now();

    $item = OrderItem::factory()->create([
        'order_id'  => $order->id,
        'person_id' => $person->id,
    ]);

    ResourceOrderItem::create([
        'resource_id'  => $resource->id,
        'orderitem_id' => $item->id,
        'from'         => $from,
        'to'           => $from->copy()->addHour(),
    ]);

    // Observers auto-set status to planned; override directly to set desired status.
    DB::table('order_items')->where('id', $item->id)->update(['status' => $status]);
    $item->refresh();

    return $item;
}

function createMainPurchasePrice(OrderItem $item, float $amount): void
{
    $item->purchasePrice()->create([
        'type'                      => PurchasePriceType::MAIN,
        'purchase_price_misc'       => $amount,
        'purchase_price_doctor'     => 0,
        'purchase_price_cardiology' => 0,
        'purchase_price_clinic'     => 0,
        'purchase_price_radiology'  => 0,
        'purchase_price'            => $amount,
    ]);
}

function step2ItemIds($response): Collection
{
    return $response->viewData('orderItemsByPerson')
        ->flatMap(fn ($items) => $items->pluck('id'));
}

it('includes active order items in step2', function () {
    $activeItem = createOrderItemForClinic($this->order, $this->crmPerson, $this->resource, OrderItemStatus::PLANNED->value);

    $response = $this->get(route('admin.inkoop.step2', $this->invoice->id));

    $response->assertOk();
    expect(step2ItemIds($response)->contains($activeItem->id))->toBeTrue();
});

it('excludes LOST order items from step2', function () {
    $activeItem = createOrderItemForClinic($this->order, $this->crmPerson, $this->resource, OrderItemStatus::PLANNED->value);
    $lostItem = createOrderItemForClinic($this->order, $this->crmPerson, $this->resource, OrderItemStatus::LOST->value);

    $response = $this->get(route('admin.inkoop.step2', $this->invoice->id));

    $response->assertOk();
    $ids = step2ItemIds($response);
    expect($ids->contains($activeItem->id))->toBeTrue()
        ->and($ids->contains($lostItem->id))->toBeFalse();
});

it('includes WON order items in step2', function () {
    $wonItem = createOrderItemForClinic($this->order, $this->crmPerson, $this->resource, OrderItemStatus::WON->value);

    $response = $this->get(route('admin.inkoop.step2', $this->invoice->id));

    $response->assertOk();
    expect(step2ItemIds($response)->contains($wonItem->id))->toBeTrue();
});

it('excludes order items belonging to a different (unlinked) person', function () {
    // Regression: the invoice person is linked to $this->crmPerson, but the order
    // item hangs on a different CRM person (e.g. a duplicate record). It must not
    // appear, since step2 filters strictly on the linked person's crm_id.
    $otherPerson = Person::factory()->create();

    $linkedItem = createOrderItemForClinic($this->order, $this->crmPerson, $this->resource, OrderItemStatus::WON->value);
    $otherPersonItem = createOrderItemForClinic($this->order, $otherPerson, $this->resource, OrderItemStatus::WON->value);

    $response = $this->get(route('admin.inkoop.step2', $this->invoice->id));

    $response->assertOk();
    $ids = step2ItemIds($response);
    expect($ids->contains($linkedItem->id))->toBeTrue()
        ->and($ids->contains($otherPersonItem->id))->toBeFalse();
});

it('excludes order items booked at a different clinic', function () {
    $otherClinic = Clinic::factory()->create();
    $otherDept = ClinicDepartment::factory()->create(['clinic_id' => $otherClinic->id]);
    $otherResource = Resource::factory()->create(['clinic_department_id' => $otherDept->id]);

    $sameClinicItem = createOrderItemForClinic($this->order, $this->crmPerson, $this->resource, OrderItemStatus::WON->value);
    $otherClinicItem = createOrderItemForClinic($this->order, $this->crmPerson, $otherResource, OrderItemStatus::WON->value);

    $response = $this->get(route('admin.inkoop.step2', $this->invoice->id));

    $response->assertOk();
    $ids = step2ItemIds($response);
    expect($ids->contains($sameClinicItem->id))->toBeTrue()
        ->and($ids->contains($otherClinicItem->id))->toBeFalse();
});

it('filters order items to the month before the reference_date month', function () {
    // Klinieken factureren een maand achteraf: een factuur met referentiedatum
    // in september hoort bij onderzoeken in augustus.
    $this->invoice->update(['reference_date' => '2025-09-15']);

    $augustOrder = Order::factory()->create();
    $septemberOrder = Order::factory()->create();

    $augustItem = createOrderItemForClinic(
        $augustOrder,
        $this->crmPerson,
        $this->resource,
        OrderItemStatus::WON->value,
        Carbon::parse('2025-08-10 10:00:00'),
    );
    $septemberItem = createOrderItemForClinic(
        $septemberOrder,
        $this->crmPerson,
        $this->resource,
        OrderItemStatus::WON->value,
        Carbon::parse('2025-09-10 10:00:00'),
    );

    $response = $this->get(route('admin.inkoop.step2', $this->invoice->id));

    $response->assertOk();
    $ids = step2ItemIds($response);
    expect($ids->contains($augustItem->id))->toBeTrue()
        ->and($ids->contains($septemberItem->id))->toBeFalse();
});

it('uses first_examination_at override for the examination month filter', function () {
    $this->invoice->update(['reference_date' => '2025-06-15']);

    $order = Order::factory()->create([
        'first_examination_at' => '2025-05-11',
    ]);

    $item = createOrderItemForClinic(
        $order,
        $this->crmPerson,
        $this->resource,
        OrderItemStatus::WON->value,
        Carbon::parse('2026-08-05 08:00:00'),
    );

    $response = $this->get(route('admin.inkoop.step2', $this->invoice->id));

    $response->assertOk();
    expect(step2ItemIds($response)->contains($item->id))->toBeTrue();
});

it('includes order items from any month when reference_date is not set', function () {
    $augustItem = createOrderItemForClinic(
        $this->order,
        $this->crmPerson,
        $this->resource,
        OrderItemStatus::WON->value,
        Carbon::parse('2025-08-10 10:00:00'),
    );
    $julyItem = createOrderItemForClinic(
        $this->order,
        $this->crmPerson,
        $this->resource,
        OrderItemStatus::WON->value,
        Carbon::parse('2025-07-10 10:00:00'),
    );

    $response = $this->get(route('admin.inkoop.step2', $this->invoice->id));

    $response->assertOk();
    $ids = step2ItemIds($response);
    expect($ids->contains($augustItem->id))->toBeTrue()
        ->and($ids->contains($julyItem->id))->toBeTrue();
});

it('stores CRM purchase price instead of invoice line price when linking products', function () {
    $orderItem = createOrderItemForClinic($this->order, $this->crmPerson, $this->resource, OrderItemStatus::WON->value);
    createMainPurchasePrice($orderItem, 200.00);

    $invoiceItem = InkoopInvoiceItem::create([
        'clinic_id'         => $this->clinic->id,
        'inkoop_invoice_id' => $this->invoice->id,
        'person_id'         => $this->inkoopPerson->id,
        'name'              => 'MRI totaal',
        'description'       => 'MRI totaal',
        'price'             => 500.00,
    ]);

    $response = $this->put(route('admin.inkoop.save-product-crm-ids', $this->invoice->id), [
        'crm_ids' => [
            $this->inkoopPerson->id => [
                $invoiceItem->id => [$orderItem->id],
            ],
        ],
    ]);

    $response->assertRedirect(route('admin.inkoop.step2', $this->invoice->id));

    $crmProduct = InkoopInvoiceItemCrmProduct::where('inkoop_invoice_item_id', $invoiceItem->id)->first();
    expect($crmProduct)->not->toBeNull()
        ->and((float) $crmProduct->purchase_price)->toBe(200.0);

    $invoicePurchase = $orderItem->fresh()->invoicePurchasePrice;
    expect($invoicePurchase)->not->toBeNull()
        ->and((float) $invoicePurchase->purchase_price)->toBe(200.0);
});

it('stores each CRM product price when multiple products are linked to one invoice line', function () {
    $itemA = createOrderItemForClinic($this->order, $this->crmPerson, $this->resource, OrderItemStatus::WON->value);
    $itemB = createOrderItemForClinic($this->order, $this->crmPerson, $this->resource, OrderItemStatus::WON->value);
    createMainPurchasePrice($itemA, 200.00);
    createMainPurchasePrice($itemB, 250.00);

    $invoiceItem = InkoopInvoiceItem::create([
        'clinic_id'         => $this->clinic->id,
        'inkoop_invoice_id' => $this->invoice->id,
        'person_id'         => $this->inkoopPerson->id,
        'name'              => 'Gecombineerde scan',
        'description'       => 'Gecombineerde scan',
        'price'             => 500.00,
    ]);

    $response = $this->put(route('admin.inkoop.save-product-crm-ids', $this->invoice->id), [
        'crm_ids' => [
            $this->inkoopPerson->id => [
                $invoiceItem->id => [$itemA->id, $itemB->id],
            ],
        ],
    ]);

    $response->assertRedirect(route('admin.inkoop.step2', $this->invoice->id));

    $prices = InkoopInvoiceItemCrmProduct::where('inkoop_invoice_item_id', $invoiceItem->id)
        ->orderBy('crm_id')
        ->pluck('purchase_price')
        ->map(fn ($p) => (float) $p)
        ->sort()
        ->values()
        ->all();

    expect($prices)->toBe([200.0, 250.0]);

    expect((float) $itemA->fresh()->invoicePurchasePrice->purchase_price)->toBe(200.0)
        ->and((float) $itemB->fresh()->invoicePurchasePrice->purchase_price)->toBe(250.0);
});

it('excludes fully received order items from step2 candidates after linking', function () {
    $orderItem = createOrderItemForClinic($this->order, $this->crmPerson, $this->resource, OrderItemStatus::WON->value);
    createMainPurchasePrice($orderItem, 200.00);

    $invoiceItem = InkoopInvoiceItem::create([
        'clinic_id'         => $this->clinic->id,
        'inkoop_invoice_id' => $this->invoice->id,
        'person_id'         => $this->inkoopPerson->id,
        'name'              => 'Scan',
        'description'       => 'Scan',
        'price'             => 200.00,
    ]);

    $this->put(route('admin.inkoop.save-product-crm-ids', $this->invoice->id), [
        'crm_ids' => [
            $this->inkoopPerson->id => [
                $invoiceItem->id => [$orderItem->id],
            ],
        ],
    ])->assertRedirect();

    $response = $this->get(route('admin.inkoop.step2', $this->invoice->id));

    $response->assertOk();
    expect(step2ItemIds($response)->contains($orderItem->id))->toBeFalse();
    $response->assertSee('Afgeletterd', false);
    $response->assertSee('Scan', false);
});

it('clears invoice purchase price when resetting a crm link', function () {
    $orderItem = createOrderItemForClinic($this->order, $this->crmPerson, $this->resource, OrderItemStatus::WON->value);
    createMainPurchasePrice($orderItem, 200.00);

    $invoiceItem = InkoopInvoiceItem::create([
        'clinic_id'         => $this->clinic->id,
        'inkoop_invoice_id' => $this->invoice->id,
        'person_id'         => $this->inkoopPerson->id,
        'name'              => 'Scan',
        'description'       => 'Scan',
        'price'             => 200.00,
    ]);

    $this->put(route('admin.inkoop.save-product-crm-ids', $this->invoice->id), [
        'crm_ids' => [
            $this->inkoopPerson->id => [
                $invoiceItem->id => [$orderItem->id],
            ],
        ],
    ])->assertRedirect();

    expect($orderItem->fresh()->invoicePurchasePrice)->not->toBeNull();

    $this->put(route('admin.inkoop.reset-crm-id', [$this->invoice->id, $invoiceItem->id]))
        ->assertRedirect();

    expect(InkoopInvoiceItemCrmProduct::where('inkoop_invoice_item_id', $invoiceItem->id)->exists())->toBeFalse()
        ->and($orderItem->fresh()->invoicePurchasePrice)->toBeNull();
});
