<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

uses(RefreshDatabase::class);

test('updating lead with empty user_id sets it to null', function () {
    $lead = Lead::factory()->create();

    expect($lead->user_id)->not->toBeNull();

    $updated = app(\Webkul\Lead\Repositories\LeadRepository::class)
        ->update(['user_id' => ''], $lead->id);

    expect($updated->user_id)->toBeNull();
    $this->assertDatabaseHas('leads', [
        'id' => $lead->id,
        'user_id' => null,
    ]);
});

test('it syncs persons even when user_id is cleared during update', function () {
    $lead = Lead::factory()->create();
    $personA = Person::factory()->create();
    $personB = Person::factory()->create();

    $payload = [
        'user_id' => '',
        'person_ids' => [$personA->id, $personB->id],
    ];

    $updated = app(\Webkul\Lead\Repositories\LeadRepository::class)
        ->update($payload, $lead->id);

    expect($updated->user_id)->toBeNull();
    expect($updated->fresh()->persons)->toHaveCount(2);
    expect($updated->fresh()->persons->pluck('id')->contains($personA->id))->toBeTrue();
    expect($updated->fresh()->persons->pluck('id')->contains($personB->id))->toBeTrue();
});

