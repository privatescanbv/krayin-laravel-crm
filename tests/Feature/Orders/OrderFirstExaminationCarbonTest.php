<?php

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('returns null when no examination date and no resource slots', function () {
    $order = Order::factory()->create([
        'first_examination_at'   => null,
        'first_examination_time' => null,
    ]);

    expect($order->firstExaminationCarbon())->toBeNull();
});

test('returns date at midnight when date is set but time is null', function () {
    $order = Order::factory()->create([
        'first_examination_at'   => '2026-05-21',
        'first_examination_time' => null,
    ]);

    $result = $order->firstExaminationCarbon();

    expect($result)->not->toBeNull()
        ->and($result->format('Y-m-d H:i'))->toBe('2026-05-21 00:00');
});

test('returns correct datetime when date and time override are both set', function () {
    $order = Order::factory()->create([
        'first_examination_at'   => '2026-05-21',
        'first_examination_time' => '10:30',
    ]);

    $result = $order->firstExaminationCarbon();

    expect($result)->not->toBeNull()
        ->and($result->format('Y-m-d H:i'))->toBe('2026-05-21 10:30');
});

test('does not crash when first_examination_time is an empty string', function () {
    // Regression: empty string bypassed the null-coalescing fallback and produced
    // "2026-05-21 :00" which Carbon cannot parse (NEWCRM_PRIVATESCAN-1N).
    $order = Order::factory()->create([
        'first_examination_at'   => '2026-05-21',
        'first_examination_time' => '',
    ]);

    // Force the raw empty string into the DB, bypassing any model mutator
    DB::table('orders')->where('id', $order->id)->update(['first_examination_time' => '']);

    $result = $order->fresh()->firstExaminationCarbon();

    expect($result)->not->toBeNull()
        ->and($result->format('Y-m-d H:i'))->toBe('2026-05-21 00:00');
});
