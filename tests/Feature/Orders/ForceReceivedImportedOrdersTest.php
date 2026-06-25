<?php

use App\Enums\PurchasePriceType;
use App\Models\Order;
use App\Models\OrderItem;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

function runForceReceivedImported(array $args = []): int
{
    return Artisan::call('orders:force-received-imported', $args);
}

function createImportedOrderItemWithInvoicePrice(
    array $orderOverrides = [],
    bool $forceReceived = false,
): OrderItem {
    $order = Order::factory()->create(array_merge([
        'external_id'  => 'sugar-order-'.uniqid(),
        'order_number' => 202502001,
        'created_at'   => '2026-03-15 10:00:00',
    ], $orderOverrides));

    $item = OrderItem::factory()->for($order)->create([
        'name' => 'TB1 Business Class',
    ]);

    $item->invoicePurchasePrice()->create([
        'type'                      => PurchasePriceType::INVOICE,
        'force_received'            => $forceReceived,
        'purchase_price'            => 100,
        'purchase_price_misc'       => 100,
        'purchase_price_doctor'     => 0,
        'purchase_price_cardiology' => 0,
        'purchase_price_clinic'     => 0,
        'purchase_price_radiology'  => 0,
    ]);

    return $item;
}

test('force-received command toont preview voor orderregels zonder force_received', function () {
    $item = createImportedOrderItemWithInvoicePrice(['order_number' => 202502011]);

    $exitCode = runForceReceivedImported(['--order-nums' => '202502011']);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('202502011')
        ->and($output)->toContain((string) $item->id)
        ->and($output)->toContain('force_received → ja')
        ->and($output)->toContain('--apply');

    expect($item->fresh()->invoicePurchasePrice->force_received)->toBeFalse();
});

test('force-received command past force_received toe met --apply', function () {
    $item = createImportedOrderItemWithInvoicePrice(['order_number' => 202502099]);

    $exitCode = runForceReceivedImported([
        '--order-nums' => '202502099',
        '--apply'      => true,
    ]);

    expect($exitCode)->toBe(0)
        ->and($item->fresh()->invoicePurchasePrice->force_received)->toBeTrue();
});

test('force-received command slaat orders over na --until datum', function () {
    createImportedOrderItemWithInvoicePrice([
        'order_number' => 202504001,
        'created_at'   => '2026-04-01 10:00:00',
    ]);

    $exitCode = runForceReceivedImported([
        '--order-nums' => '202504001',
        '--until'      => '2026-03-31',
    ]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Geen geïmporteerde orders gevonden');
});

test('force-received command meldt geen wijzigingen wanneer force_received al gezet is', function () {
    createImportedOrderItemWithInvoicePrice(['order_number' => 202502088], forceReceived: true);

    $exitCode = runForceReceivedImported(['--order-nums' => '202502088']);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Geen orderregels gevonden die force_received nodig hebben.');
});

test('force-received command negeert orders zonder external_id', function () {
    $order = Order::factory()->create([
        'external_id'  => null,
        'order_number' => 202502077,
        'created_at'   => '2026-03-01 10:00:00',
    ]);
    $item = OrderItem::factory()->for($order)->create();
    $item->invoicePurchasePrice()->create([
        'type'                => PurchasePriceType::INVOICE,
        'force_received'      => false,
        'purchase_price'      => 50,
        'purchase_price_misc' => 50,
    ]);

    $exitCode = runForceReceivedImported(['--order-nums' => '202502077']);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Geen geïmporteerde orders gevonden');
});
