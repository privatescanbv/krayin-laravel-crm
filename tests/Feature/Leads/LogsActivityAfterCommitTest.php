<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Webkul\Activity\Models\Activity;
use Webkul\Lead\Models\Lead;

uses(RefreshDatabase::class);

test('lead update writes the system activity only after the transaction commits', function () {
    $lead = Lead::factory()->create();
    Activity::query()->delete();

    $insideTransaction = null;

    DB::transaction(function () use ($lead, &$insideTransaction) {
        $lead->update(['gender' => 'Man']);

        $insideTransaction = Activity::where('lead_id', $lead->id)->count();
    });

    expect($insideTransaction)->toBe(0)
        ->and(Activity::where('lead_id', $lead->id)->count())->toBeGreaterThan(0);
});

test('lead update outside a transaction still logs the activity', function () {
    $lead = Lead::factory()->create();
    Activity::query()->delete();

    $lead->update(['gender' => 'Man']);

    expect(Activity::where('lead_id', $lead->id)->count())->toBeGreaterThan(0);
});

test('a rolled back lead update leaves no activity behind', function () {
    $lead = Lead::factory()->create();
    Activity::query()->delete();

    try {
        DB::transaction(function () use ($lead) {
            $lead->update(['gender' => 'Man']);

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    expect(Activity::where('lead_id', $lead->id)->count())->toBe(0);
});
