<?php

use App\Models\Order;
use App\Models\SalesLead;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;

test('it re-points a sales whose lead was deleted to the live lead of the same person', function () {
    $pipeline = Pipeline::create(['name' => 'Repair pipeline', 'is_default' => 0, 'rotten_days' => 30]);
    $open = Stage::create([
        'name'             => 'Open',
        'code'             => 'open',
        'lead_pipeline_id' => $pipeline->id,
        'sort_order'       => 1,
        'is_won'           => false,
        'is_lost'          => false,
    ]);
    $lost = Stage::create([
        'name'             => 'Lost',
        'code'             => 'lost',
        'lead_pipeline_id' => $pipeline->id,
        'sort_order'       => 2,
        'is_won'           => false,
        'is_lost'          => true,
    ]);

    $person = Person::factory()->create();
    $deletedLead = Lead::factory()->create();
    $liveLead = Lead::factory()->create();
    $deletedLead->attachPersons([$person->id]);
    $liveLead->attachPersons([$person->id]);

    $sales = SalesLead::factory()->create([
        'lead_id'           => $deletedLead->id,
        'pipeline_stage_id' => $open->id,
        'closed_at'         => null,
    ]);
    $sales->attachPersons([$person->id]);

    Order::factory()->create([
        'sales_lead_id'     => $sales->id,
        'pipeline_stage_id' => $lost->id,
        'closed_at'         => '2026-07-23',
    ]);

    $deletedLead->delete();

    $this->artisan('leads:repair-orphaned-sales')->assertSuccessful();

    $sales->refresh();

    expect($sales->lead_id)->toBe($liveLead->id)
        ->and($sales->pipeline_stage_id)->toBe($lost->id)
        ->and($sales->closed_at->toDateString())->toBe('2026-07-23');
});

test('dry run leaves an orphaned sales unchanged', function () {
    $person = Person::factory()->create();
    $deletedLead = Lead::factory()->create();
    $liveLead = Lead::factory()->create();
    $deletedLead->attachPersons([$person->id]);
    $liveLead->attachPersons([$person->id]);

    $sales = SalesLead::factory()->create(['lead_id' => $deletedLead->id]);
    $sales->attachPersons([$person->id]);
    $deletedLead->delete();

    $this->artisan('leads:repair-orphaned-sales', ['--dry-run' => true])->assertSuccessful();

    expect($sales->fresh()->lead_id)->toBe($deletedLead->id);
});
