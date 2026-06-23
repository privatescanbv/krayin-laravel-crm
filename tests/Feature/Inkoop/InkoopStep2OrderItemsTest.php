<?php

use App\Enums\OrderItemStatus;
use App\Models\Clinic;
use App\Models\ClinicDepartment;
use App\Models\Inkoop\InkoopInvoice;
use App\Models\Inkoop\InkoopPerson;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Resource;
use App\Models\ResourceOrderItem;
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

    InkoopPerson::create([
        'clinic_id'  => $this->clinic->id,
        'invoice_id' => $this->invoice->id,
        'firstname'  => $this->crmPerson->first_name,
        'lastname'   => $this->crmPerson->last_name,
        'crm_id'     => $this->crmPerson->id,
    ]);

    $this->order = Order::factory()->create();
});

function createOrderItemForClinic(Order $order, Person $person, Resource $resource, string $status): OrderItem
{
    $item = OrderItem::factory()->create([
        'order_id'  => $order->id,
        'person_id' => $person->id,
    ]);

    ResourceOrderItem::create([
        'resource_id'  => $resource->id,
        'orderitem_id' => $item->id,
        'from'         => now(),
        'to'           => now()->addHour(),
    ]);

    // Observers auto-set status to planned; override directly to set desired status.
    DB::table('order_items')->where('id', $item->id)->update(['status' => $status]);
    $item->refresh();

    return $item;
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
