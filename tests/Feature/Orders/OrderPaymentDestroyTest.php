<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Order;
use App\Models\OrderPayment;
use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'user');
    $this->withoutMiddleware(Authenticate::class);
});

test('verwijderen van een betaling logt een system activity op de order', function () {
    $order = Order::factory()->create();
    $payment = OrderPayment::create([
        'order_id' => $order->id,
        'amount'   => 550,
        'type'     => PaymentType::REFUND,
        'method'   => PaymentMethod::BANK,
        'paid_at'  => null,
        'currency' => 'EUR',
    ]);

    $this->deleteJson(route('admin.orders.payments.destroy', ['orderId' => $order->id, 'paymentId' => $payment->id]))
        ->assertOk();

    $this->assertDatabaseMissing('order_payments', ['id' => $payment->id]);
    $this->assertDatabaseHas('activities', [
        'order_id' => $order->id,
        'type'     => 'system',
        'title'    => 'Terugbetaling van € 550,00 verwijderd',
        'comment'  => 'Methode: '.PaymentMethod::BANK->label().', datum: nog niet betaald',
        'user_id'  => $this->user->id,
    ]);
});

test('verwijderen van een betaling van een andere order geeft 404 en logt niets', function () {
    $order = Order::factory()->create();
    $other = Order::factory()->create();
    $payment = OrderPayment::create([
        'order_id' => $other->id,
        'amount'   => 100,
        'type'     => PaymentType::ADVANCE,
        'method'   => PaymentMethod::BANK,
        'paid_at'  => now(),
        'currency' => 'EUR',
    ]);

    $this->deleteJson(route('admin.orders.payments.destroy', ['orderId' => $order->id, 'paymentId' => $payment->id]))
        ->assertNotFound();

    $this->assertDatabaseHas('order_payments', ['id' => $payment->id]);
    $this->assertDatabaseMissing('activities', ['type' => 'system', 'order_id' => $order->id]);
});
