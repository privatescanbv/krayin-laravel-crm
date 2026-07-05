<?php

use App\Enums\ProductReports;
use App\Models\Clinic;
use App\Models\Order;
use App\Models\OrderCheck;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use App\Services\OrderCheckService;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Webkul\Contact\Models\Person;
use Webkul\Product\Models\Product;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->service = app(OrderCheckService::class);
});

test('creates separate checks per person when both have products with same reporting type', function () {
    $order = Order::factory()->create();
    $product = Product::factory()->create();

    PartnerProduct::factory()->create([
        'product_id' => $product->id,
        'reporting'  => [ProductReports::LAB_1->value],
        'active'     => true,
    ]);

    $person1 = Person::factory()->create(['first_name' => 'Jan', 'last_name' => 'Jansen', 'is_active' => true]);
    $person2 = Person::factory()->create(['first_name' => 'Marie', 'last_name' => 'Peters', 'is_active' => true]);

    OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'person_id'  => $person1->id,
    ]);
    OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'person_id'  => $person2->id,
    ]);

    $this->service->updatePartnerProductChecks($order->fresh());

    $checks = OrderCheck::where('order_id', $order->id)
        ->where('removable', false)
        ->pluck('name');

    expect($checks)->toContain('Partner product rapportage: Laboratoriumuitslag — Jan Jansen')
        ->and($checks)->toContain('Partner product rapportage: Laboratoriumuitslag — Marie Peters')
        ->and($checks->count())->toBe(2);
});

test('creates single check when order item has no person', function () {
    $order = Order::factory()->create();
    $product = Product::factory()->create();

    PartnerProduct::factory()->create([
        'product_id' => $product->id,
        'reporting'  => [ProductReports::RAD_MRI->value],
        'active'     => true,
    ]);

    OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'person_id'  => null,
    ]);

    $this->service->updatePartnerProductChecks($order->fresh());

    $checks = OrderCheck::where('order_id', $order->id)
        ->where('removable', false)
        ->pluck('name');

    expect($checks)->toContain('Partner product rapportage: Radiologie MRI')
        ->and($checks->count())->toBe(1);
});

test('removes obsolete checks when person is removed from order item', function () {
    $order = Order::factory()->create();
    $product = Product::factory()->create();

    PartnerProduct::factory()->create([
        'product_id' => $product->id,
        'reporting'  => [ProductReports::CARDIO_1->value],
        'active'     => true,
    ]);

    $person = Person::factory()->create(['first_name' => 'Klaas', 'last_name' => 'Vos', 'is_active' => true]);

    $item = OrderItem::factory()->create([
        'order_id'   => $order->id,
        'product_id' => $product->id,
        'person_id'  => $person->id,
    ]);

    $this->service->updatePartnerProductChecks($order->fresh());

    expect(OrderCheck::where('order_id', $order->id)->count())->toBe(1);

    // Remove person from order item
    $item->person_id = null;
    $item->saveQuietly();

    $this->service->updatePartnerProductChecks($order->fresh());

    $checks = OrderCheck::where('order_id', $order->id)->where('removable', false)->pluck('name');
    expect($checks)->toContain('Partner product rapportage: Cardiologie')
        ->and($checks->count())->toBe(1);
});

test('report upload succeeds without selecting checks', function () {
    $user = makeUser();
    $this->actingAs($user, 'user');

    $clinic = Clinic::factory()->create();
    $order = Order::factory()->create();

    $file = UploadedFile::fake()->create('rapport.pdf', 100, 'application/pdf');

    $response = $this->post(
        route('admin.orders.report-upload.store', $order->id),
        [
            'files'     => [$file],
            'clinic_id' => $clinic->id,
        ],
        ['Accept' => 'application/json']
    );

    $response->assertOk()
        ->assertJsonPath('message', 'Rapportage succesvol geüpload.');
});

test('report upload with checks marks them done', function () {
    $user = makeUser();
    $this->actingAs($user, 'user');

    $clinic = Clinic::factory()->create();
    $order = Order::factory()->create();

    $check = OrderCheck::create([
        'order_id'  => $order->id,
        'name'      => 'Partner product rapportage: Radiologie MRI — Test Person',
        'done'      => false,
        'removable' => false,
    ]);

    $file = UploadedFile::fake()->create('rapport.pdf', 100, 'application/pdf');

    $response = $this->post(
        route('admin.orders.report-upload.store', $order->id),
        [
            'files'     => [$file],
            'clinic_id' => $clinic->id,
            'check_ids' => [$check->id],
        ],
        ['Accept' => 'application/json']
    );

    $response->assertOk()
        ->assertJsonPath('message', 'Rapportage succesvol geüpload en checks afgevinkt.');

    expect($check->fresh()->done)->toBeTrue();
});
