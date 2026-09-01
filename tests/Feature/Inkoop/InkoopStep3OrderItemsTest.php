<?php

use App\Enums\OrderItemStatus;
use App\Models\Clinic;
use App\Models\ClinicDepartment;
use App\Models\Inkoop\InkoopInvoice;
use App\Models\Inkoop\InkoopInvoiceItem;
use App\Models\Inkoop\InkoopPerson;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Resource;
use App\Models\ResourceOrderItem;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        'clinic_id'       => $this->clinic->id,
        'pdf_path'        => 'test/test.pdf',
        // Factuur van juli hoort bij onderzoeken in juni (kliniek factureert een maand achteraf).
        'reference_date'  => '2025-07-15',
    ]);

    $this->crmPerson = Person::factory()->create();

    $this->inkoopPerson = InkoopPerson::create([
        'clinic_id'  => $this->clinic->id,
        'invoice_id' => $this->invoice->id,
        'firstname'  => $this->crmPerson->first_name,
        'lastname'   => $this->crmPerson->last_name,
        'crm_id'     => $this->crmPerson->id,
    ]);
});

function createStep3OrderItem(
    Order $order,
    Person $person,
    Resource $resource,
    string $status,
    Carbon $from,
): OrderItem {
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

    DB::table('order_items')->where('id', $item->id)->update(['status' => $status]);
    $item->refresh();

    return $item;
}

it('does not list unmatched order items outside the reference_date month in step3', function () {
    $juneOrder = Order::factory()->create(['first_examination_at' => '2025-06-11']);
    $novOrder = Order::factory()->create(['first_examination_at' => '2025-11-26']);

    $juneItem = createStep3OrderItem(
        $juneOrder,
        $this->crmPerson,
        $this->resource,
        OrderItemStatus::WON->value,
        Carbon::parse('2025-06-11 10:00:00'),
    );
    $novItem = createStep3OrderItem(
        $novOrder,
        $this->crmPerson,
        $this->resource,
        OrderItemStatus::PLANNED->value,
        Carbon::parse('2025-11-26 10:30:00'),
    );

    InkoopInvoiceItem::create([
        'clinic_id'         => $this->clinic->id,
        'inkoop_invoice_id' => $this->invoice->id,
        'person_id'         => $this->inkoopPerson->id,
        'name'              => 'Scan juni',
        'description'       => 'Scan juni',
        'price'             => 100.00,
    ]);

    $response = $this->get(route('admin.inkoop.step3', $this->invoice->id));

    $response->assertOk();

    $byPerson = $response->viewData('crmOrderItemsByPerson');
    $ids = $byPerson[$this->inkoopPerson->id]->pluck('id');

    expect($ids->contains($juneItem->id))->toBeTrue()
        ->and($ids->contains($novItem->id))->toBeFalse();

    $response->assertDontSee('1/2 orderregel(s) gekoppeld');
});

it('hides a person from step3 when all reference-month order items are linked', function () {
    $order = Order::factory()->create(['first_examination_at' => '2025-06-11']);
    $item = createStep3OrderItem(
        $order,
        $this->crmPerson,
        $this->resource,
        OrderItemStatus::WON->value,
        Carbon::parse('2025-06-11 10:00:00'),
    );

    $invoiceItem = InkoopInvoiceItem::create([
        'clinic_id'         => $this->clinic->id,
        'inkoop_invoice_id' => $this->invoice->id,
        'person_id'         => $this->inkoopPerson->id,
        'name'              => 'Scan juni',
        'description'       => 'Scan juni',
        'price'             => 100.00,
    ]);

    $invoiceItem->crmProducts()->create([
        'clinic_id'      => $this->clinic->id,
        'product_id'     => $item->product_id,
        'crm_id'         => $item->id,
        'crm_status'     => $item->status->value ?? (string) $item->status,
        'purchase_price' => 100,
    ]);

    $response = $this->get(route('admin.inkoop.step3', $this->invoice->id));

    $response->assertOk();
    $response->assertDontSee(trim($this->crmPerson->first_name.' '.$this->crmPerson->last_name));
    $response->assertDontSee('orderregel(s) gekoppeld');
});
