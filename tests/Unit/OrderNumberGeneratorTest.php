<?php

use App\Services\OrderNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('generates sequential order numbers per year', function () {
    /** @var OrderNumberGenerator $generator */
    $generator = app(OrderNumberGenerator::class);

    expect($generator->next(2026))->toBe('202600001');
    expect($generator->next(2026))->toBe('202600002');
    expect($generator->next(2027))->toBe('202700001');

    $seq2026 = DB::table('order_number_sequences')->where('year', 2026)->first();
    $seq2027 = DB::table('order_number_sequences')->where('year', 2027)->first();

    expect((int) ($seq2026->last_number ?? 0))->toBe(2);
    expect((int) ($seq2027->last_number ?? 0))->toBe(1);
});
