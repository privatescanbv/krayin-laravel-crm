<?php

use App\Enums\LostReason;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\DB;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->actingAs(User::factory()->create(), 'user');
});

// ── helpers ──────────────────────────────────────────────────────────────────

function lostReasonActivity(SalesLead $salesLead): ?object
{
    return DB::table('activities')
        ->where('sales_lead_id', $salesLead->id)
        ->where('type', 'system')
        ->where('title', 'Reden verlies gewijzigd')
        ->latest('id')
        ->first();
}

// ── 1: enum → null (exact Sentry scenario) ───────────────────────────────────

test('lost_reason: enum → null logs old label and does not throw', function () {
    // SalesLead starts with a cast LostReason enum value
    $salesLead = SalesLead::factory()->create(['lost_reason' => LostReason::DoesNotPay]);

    // Updating to null; getOriginal('lost_reason') returns the LostReason enum instance
    expect(fn () => $salesLead->update(['lost_reason' => null]))->not->toThrow(\Throwable::class);

    $activity = lostReasonActivity($salesLead);
    expect($activity)->not->toBeNull();

    $additional = json_decode($activity->additional, true);
    expect($additional['old']['label'])->toBe(LostReason::DoesNotPay->label())
        ->and($additional['new']['label'])->toBe('-')
        ->and($additional['old']['value'])->toBe(LostReason::DoesNotPay->value) // string, not enum
        ->and($additional['new']['value'])->toBeNull();
});

// ── 2: null → enum ───────────────────────────────────────────────────────────

test('lost_reason: null → enum logs new label', function () {
    $salesLead = SalesLead::factory()->create(['lost_reason' => null]);

    $salesLead->update(['lost_reason' => LostReason::Price]);

    $activity = lostReasonActivity($salesLead);
    expect($activity)->not->toBeNull();

    $additional = json_decode($activity->additional, true);
    expect($additional['new']['label'])->toBe(LostReason::Price->label())
        ->and($additional['old']['label'])->toBe('-')
        ->and($additional['new']['value'])->toBe(LostReason::Price->value); // string, not enum
});

// ── 3: enum → different enum ──────────────────────────────────────────────────

test('lost_reason: enum → different enum logs both labels correctly', function () {
    $salesLead = SalesLead::factory()->create(['lost_reason' => LostReason::Price]);

    $salesLead->update(['lost_reason' => LostReason::DoesNotPay]);

    $activity = lostReasonActivity($salesLead);
    expect($activity)->not->toBeNull();

    $additional = json_decode($activity->additional, true);
    expect($additional['old']['label'])->toBe(LostReason::Price->label())
        ->and($additional['new']['label'])->toBe(LostReason::DoesNotPay->label())
        ->and($additional['old']['value'])->toBe(LostReason::Price->value)
        ->and($additional['new']['value'])->toBe(LostReason::DoesNotPay->value);
});

// ── 4: additional.value is always JSON-serialisable (string, not object) ──────

test('lost_reason activity additional values are JSON-serialisable strings', function () {
    $salesLead = SalesLead::factory()->create(['lost_reason' => LostReason::DoesNotPay]);
    $salesLead->update(['lost_reason' => LostReason::Price]);

    $activity = lostReasonActivity($salesLead);
    expect($activity)->not->toBeNull();

    // additional column must decode without error and values must be scalars
    $additional = json_decode($activity->additional, true);
    expect($additional)->toBeArray()
        ->and($additional['old']['value'])->toBeString()
        ->and($additional['new']['value'])->toBeString();
});
