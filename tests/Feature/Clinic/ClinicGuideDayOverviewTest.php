<?php

namespace Tests\Feature;

use App\Enums\AfbDispatchStatus;
use App\Enums\AfbDispatchType;
use App\Enums\PipelineStage;
use App\Models\AfbDispatch;
use App\Models\AfbPersonDocument;
use App\Models\Clinic;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use App\Models\ResourceOrderItem;
use App\Models\SalesLead;
use Carbon\Carbon;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Storage;
use Webkul\Contact\Models\Person;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Product\Models\Product;

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);

    $this->seed(TestSeeder::class);

    $user = makeUser();
    $this->actingAs($user, 'user');
});

/**
 * Creates an OrderItem whose product is linked to a PartnerProduct which is linked to a Clinic.
 * This satisfies the whereHas('product.partnerProducts.clinics') filter in ClinicGuideController.
 */
function createClinicLinkedOrderItem(int $orderId, ?int $personId = null): OrderItem
{
    $product = Product::factory()->create();
    PartnerProduct::factory()->create(['product_id' => $product->id]);

    return OrderItem::factory()->create([
        'order_id'   => $orderId,
        'product_id' => $product->id,
        'person_id'  => $personId,
    ]);
}

test('clinic guide get returns orders for given date', function () {
    $targetDate = '2026-03-15';

    $salesLead = SalesLead::factory()->create();

    $orderOnDate = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id'    => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at' => Carbon::parse($targetDate)->setHour(10)->setMinute(30),
    ]);
    createClinicLinkedOrderItem($orderOnDate->id);

    $orderOtherDate = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id'    => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at' => Carbon::parse('2026-03-16')->setHour(14)->setMinute(0),
    ]);
    createClinicLinkedOrderItem($orderOtherDate->id);

    $response = $this->getJson(route('admin.clinic-guide.get', ['date' => $targetDate]));

    $response->assertOk();
    $response->assertJsonPath('date', $targetDate);
    $response->assertJsonPath('count', 1);

    $orderIds = collect($response->json('orders'))->pluck('order.id')->all();
    expect($orderIds)->toContain($orderOnDate->id)
        ->and($orderIds)->not->toContain($orderOtherDate->id);
});

test('clinic guide get filters out orders from other days', function () {
    $salesLead = SalesLead::factory()->create();

    $orderApril1 = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id'    => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at' => Carbon::parse('2026-04-01 09:00:00'),
    ]);
    createClinicLinkedOrderItem($orderApril1->id);

    $orderApril2 = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id'    => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at' => Carbon::parse('2026-04-02 11:00:00'),
    ]);
    createClinicLinkedOrderItem($orderApril2->id);

    $response = $this->getJson(route('admin.clinic-guide.get', ['date' => '2026-04-01']));

    $response->assertOk();
    $response->assertJsonPath('count', 1);
});

test('clinic guide get includes orders with only scheduled slots on the requested day', function () {
    $targetDate = '2026-07-01';
    $salesLead = SalesLead::factory()->create();

    $order = Order::factory()->create([
        'sales_lead_id'         => $salesLead->id,
        'pipeline_stage_id'     => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at'  => null,
    ]);

    $item = createClinicLinkedOrderItem($order->id);
    ResourceOrderItem::factory()->create([
        'orderitem_id' => $item->id,
        'from'         => Carbon::parse("$targetDate 11:30:00"),
        'to'           => Carbon::parse("$targetDate 12:30:00"),
    ]);

    $response = $this->getJson(route('admin.clinic-guide.get', ['date' => $targetDate]));

    $response->assertOk();
    $response->assertJsonPath('count', 1);
    expect(collect($response->json('orders'))->pluck('order.id')->all())->toContain($order->id);
});

test('clinic guide get excludes orders without first_examination_at', function () {
    $salesLead = SalesLead::factory()->create();

    Order::factory()->create([
        'pipeline_stage_id'    => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'sales_lead_id'        => $salesLead->id,
        'first_examination_at' => null,
    ]);

    $response = $this->getJson(route('admin.clinic-guide.get', ['date' => now()->format('Y-m-d')]));

    $response->assertOk();
    $response->assertJsonPath('count', 0);
});

test('clinic guide get response contains expected fields', function () {
    $person = Person::factory()->create([
        'name' => 'Jan Testpatiënt',
    ]);

    $salesLead = SalesLead::factory()->create();
    $salesLead->attachPersons([$person->id]);

    $targetDate = '2026-05-10';
    $order = Order::factory()->create([
        'sales_lead_id'          => $salesLead->id,
        'pipeline_stage_id'      => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at'   => $targetDate,
        'first_examination_time' => '09:15',
    ]);

    createClinicLinkedOrderItem($order->id, $person->id);

    $response = $this->getJson(route('admin.clinic-guide.get', ['date' => $targetDate]));

    $response->assertOk();
    $response->assertJsonPath('count', 1);

    $orderData = $response->json('orders.0');

    // Order fields
    expect($orderData['order'])->toHaveKeys(['id', 'title', 'first_examination_at', 'time', 'total_price'])
        ->and($orderData['order']['time'])->toBe('09:15');

    // Sales lead fields
    expect($orderData['sales_lead'])->toHaveKeys(['id', 'name']);

    // Patient fields
    expect($orderData['patient'])->toHaveKeys(['id', 'name']);

    // Order items
    expect($orderData['order_items'])->toHaveCount(1);

    // Order URL
    expect($orderData['order_url'])->toContain('orders/view');

    expect($orderData['afb_documents'])->toBe([]);
});

test('clinic guide get includes afb_documents when AFB was sent successfully', function () {
    Storage::fake('local');

    $person = Person::factory()->create();
    $salesLead = SalesLead::factory()->create();
    $salesLead->attachPersons([$person->id]);

    $targetDate = '2026-05-11';
    $order = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id'    => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at' => Carbon::parse($targetDate)->setHour(10)->setMinute(0),
    ]);
    createClinicLinkedOrderItem($order->id, $person->id);

    $clinic = Clinic::query()->firstOrFail();
    $dispatch = AfbDispatch::query()->create([
        'clinic_id'              => $clinic->id,
        'clinic_department_id'   => null,
        'email_id'               => null,
        'type'                   => AfbDispatchType::BATCH->value,
        'status'                 => AfbDispatchStatus::SUCCESS->value,
        'sent_at'                => now(),
    ]);

    $relativePath = 'afb/test-'.uniqid('', true).'.pdf';
    Storage::disk('local')->put($relativePath, '%PDF-1.4 test');

    $doc = AfbPersonDocument::query()->create([
        'afb_dispatch_id' => $dispatch->id,
        'order_id'        => $order->id,
        'person_id'       => $person->id,
        'patient_name'    => $person->name,
        'file_name'       => 'afb-test.pdf',
        'file_path'       => $relativePath,
        'sent_at'         => now(),
    ]);

    $response = $this->getJson(route('admin.clinic-guide.get', ['date' => $targetDate]));

    $response->assertOk();
    $expectedUrl = route('admin.clinic-guide.afb-pdf.view', ['personDocumentId' => $doc->id]);
    $response->assertJsonPath('orders.0.afb_documents.0.url', $expectedUrl);
});

test('clinic guide AFB PDF view serves PDF inline', function () {
    Storage::fake('local');

    $person = Person::factory()->create();
    $salesLead = SalesLead::factory()->create();
    $order = Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
    ]);

    $clinic = Clinic::query()->firstOrFail();
    $dispatch = AfbDispatch::query()->create([
        'clinic_id'            => $clinic->id,
        'clinic_department_id' => null,
        'email_id'             => null,
        'type'                 => AfbDispatchType::BATCH->value,
        'status'               => AfbDispatchStatus::SUCCESS->value,
        'sent_at'              => now(),
    ]);

    $relativePath = 'afb/inline-'.uniqid('', true).'.pdf';
    Storage::disk('local')->put($relativePath, '%PDF-1.4 inline');

    $doc = AfbPersonDocument::query()->create([
        'afb_dispatch_id' => $dispatch->id,
        'order_id'        => $order->id,
        'person_id'       => $person->id,
        'patient_name'    => $person->name,
        'file_name'       => 'form.pdf',
        'file_path'       => $relativePath,
        'sent_at'         => now(),
    ]);

    $this->get(route('admin.clinic-guide.afb-pdf.view', ['personDocumentId' => $doc->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="form.pdf"');
});

test('clinic guide get orders are sorted by time ascending when time override is set', function () {
    $salesLead = SalesLead::factory()->create();
    $targetDate = '2026-06-01';

    $orderLate = Order::factory()->create([
        'sales_lead_id'          => $salesLead->id,
        'pipeline_stage_id'      => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at'   => $targetDate,
        'first_examination_time' => '16:00',
    ]);
    createClinicLinkedOrderItem($orderLate->id);

    $orderEarly = Order::factory()->create([
        'sales_lead_id'          => $salesLead->id,
        'pipeline_stage_id'      => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at'   => $targetDate,
        'first_examination_time' => '08:00',
    ]);
    createClinicLinkedOrderItem($orderEarly->id);

    $orderMid = Order::factory()->create([
        'sales_lead_id'          => $salesLead->id,
        'pipeline_stage_id'      => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at'   => $targetDate,
        'first_examination_time' => '12:00',
    ]);
    createClinicLinkedOrderItem($orderMid->id);

    $response = $this->getJson(route('admin.clinic-guide.get', ['date' => $targetDate]));

    $response->assertOk();
    $orderIds = collect($response->json('orders'))->pluck('order.id')->all();

    expect($orderIds)->toBe([$orderEarly->id, $orderMid->id, $orderLate->id]);
});

test('clinic guide get orders without time override sort by earliest resource order item time', function () {
    $salesLead = SalesLead::factory()->create();
    $targetDate = '2026-06-10';

    // Order with date override only — no time override. ROI scheduled at 14:00.
    $orderLate = Order::factory()->create([
        'sales_lead_id'          => $salesLead->id,
        'pipeline_stage_id'      => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at'   => $targetDate,
        'first_examination_time' => null,
    ]);
    $itemLate = createClinicLinkedOrderItem($orderLate->id);
    ResourceOrderItem::factory()->create([
        'orderitem_id' => $itemLate->id,
        'from'         => Carbon::parse("$targetDate 14:00:00"),
        'to'           => Carbon::parse("$targetDate 15:00:00"),
    ]);

    // Order with date override only — no time override. ROI scheduled at 09:00.
    $orderEarly = Order::factory()->create([
        'sales_lead_id'          => $salesLead->id,
        'pipeline_stage_id'      => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at'   => $targetDate,
        'first_examination_time' => null,
    ]);
    $itemEarly = createClinicLinkedOrderItem($orderEarly->id);
    ResourceOrderItem::factory()->create([
        'orderitem_id' => $itemEarly->id,
        'from'         => Carbon::parse("$targetDate 09:00:00"),
        'to'           => Carbon::parse("$targetDate 10:00:00"),
    ]);

    $response = $this->getJson(route('admin.clinic-guide.get', ['date' => $targetDate]));

    $response->assertOk();
    $orderIds = collect($response->json('orders'))->pluck('order.id')->all();

    expect($orderIds)->toBe([$orderEarly->id, $orderLate->id]);
});

test('clinic guide get orders without any time information sort without error', function () {
    $salesLead = SalesLead::factory()->create();
    $targetDate = '2026-06-15';

    // Orders with date override only, no time override, no scheduled ROIs.
    $order1 = Order::factory()->create([
        'sales_lead_id'          => $salesLead->id,
        'pipeline_stage_id'      => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at'   => $targetDate,
        'first_examination_time' => null,
    ]);
    createClinicLinkedOrderItem($order1->id);

    $order2 = Order::factory()->create([
        'sales_lead_id'          => $salesLead->id,
        'pipeline_stage_id'      => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at'   => $targetDate,
        'first_examination_time' => null,
    ]);
    createClinicLinkedOrderItem($order2->id);

    $response = $this->getJson(route('admin.clinic-guide.get', ['date' => $targetDate]));

    $response->assertOk();
    $response->assertJsonPath('count', 2);
});

test('clinic guide index page loads successfully', function () {
    $response = $this->get(route('admin.clinic-guide.index'));

    $response->assertOk();
    $response->assertViewIs('adminc::clinic_guide.index');
});

test('clinic guide get defaults to today when no date parameter', function () {
    $salesLead = SalesLead::factory()->create();

    $order = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'pipeline_stage_id'    => PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
        'first_examination_at' => now()->setHour(10)->setMinute(0)->setSecond(0),
    ]);
    createClinicLinkedOrderItem($order->id);

    $response = $this->getJson(route('admin.clinic-guide.get'));

    $response->assertOk();
    $response->assertJsonPath('date', now()->format('Y-m-d'));
    $response->assertJsonPath('count', 1);
});
