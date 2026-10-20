<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Order;
use App\Models\OrderPayment;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

test('migration flags only refunds created together with an automatic refund task', function () {
    $order = Order::factory()->create();
    $refund = fn () => OrderPayment::create([
        'order_id' => $order->id,
        'amount'   => 550,
        'type'     => PaymentType::REFUND,
        'method'   => PaymentMethod::BANK,
        'currency' => 'EUR',
    ]);

    $auto = $refund();
    DB::table('order_payments')->where('id', $auto->id)->update(['created_at' => '2026-10-20 15:47:00']);
    DB::table('activities')->insert([
        'type'       => 'task',
        'title'      => 'Klant terugbetalen',
        'comment'    => 'Automatisch aangemaakt: order item op verloren gezet terwijl klant al € 550,00 had betaald.',
        'order_id'   => $order->id,
        'is_done'    => 0,
        'created_at' => '2026-10-20 15:47:01',
        'updated_at' => '2026-10-20 15:47:01',
    ]);

    $manual = $refund();
    DB::table('order_payments')->where('id', $manual->id)->update(['created_at' => '2026-10-21 10:00:00']);

    $migration = require database_path('migrations/2026_10_20_160000_add_auto_created_to_order_payments_table.php');
    $migration->down();
    $migration->up();

    expect((bool) DB::table('order_payments')->where('id', $auto->id)->value('auto_created'))->toBeTrue()
        ->and((bool) DB::table('order_payments')->where('id', $manual->id)->value('auto_created'))->toBeFalse();
});
