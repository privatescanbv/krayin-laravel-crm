<?php

namespace Tests\Feature;

use App\Models\SalesLead;
use App\Services\LeadAndSalesService;
use Database\Seeders\TestSeeder;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

test('findOpenByPerson returns open sales and its lead for person', function () {
    $person = Person::factory()->create();

    // Create lead and attach person
    $lead = Lead::factory()->create();
    $lead->attachPersons([$person->id]);

    // Use an open stage (not won/lost)
    $stage = Stage::where('is_won', false)
        ->where('is_lost', false)
        ->first() ?? Stage::factory()->create([
            'is_won'  => false,
            'is_lost' => false,
        ]);

    // Create open sales lead linked to lead and person
    $salesLead = SalesLead::factory()
        ->withLead($lead)
        ->create([
            'pipeline_stage_id' => $stage->id,
        ]);
    $salesLead->attachPersons([$person->id]);

    $service = app(LeadAndSalesService::class);

    $result = $service->findOpenByPerson($person->id);

    expect($result['sales'])->not->toBeNull();
    expect($result['sales']->id)->toBe($salesLead->id);
    expect($result['lead'])->not->toBeNull();
    expect($result['lead']->id)->toBe($lead->id);
});

test('findOpenByPerson returns open lead when no open sales for person', function () {
    $person = Person::factory()->create();

    // Create lead and attach person
    $lead = Lead::factory()->create();
    $lead->attachPersons([$person->id]);

    // Ensure lead stage is open (not won/lost)
    $stage = $lead->stage;
    $stage->is_won = false;
    $stage->is_lost = false;
    $stage->save();

    $service = app(LeadAndSalesService::class);

    $result = $service->findOpenByPerson($person->id);

    expect($result['sales'])->toBeNull();
    expect($result['lead'])->not->toBeNull();
    expect($result['lead']->id)->toBe($lead->id);
});

test('findOpenByPerson returns nulls when no open lead or sales for person', function () {
    $person = Person::factory()->create();

    $service = app(LeadAndSalesService::class);

    $result = $service->findOpenByPerson($person->id);

    expect($result['sales'])->toBeNull();
    expect($result['lead'])->toBeNull();
});
