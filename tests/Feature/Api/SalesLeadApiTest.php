<?php

namespace Tests\Feature;

use Database\Seeders\TestSeeder;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    config(['api.keys' => ['valid-api-key-123']]);

    // Ensure a pipeline with id=3 exists with a stage, because the controller uses pipeline_id=3
    $pipeline = Pipeline::find(3);
    if (! $pipeline) {
        // Try to create with explicit id=3
        $pipeline = new Pipeline([
            'id'          => 3,
            'name'        => 'API Workflow',
            'is_default'  => 1,
            'rotten_days' => 30,
            'type'        => 'workflow',
        ]);
        $pipeline->save();
    }

    $stage = Stage::where('lead_pipeline_id', $pipeline->id)->orderByDesc('sort_order')->first();
    if (! $stage) {
        $stage = Stage::create([
            'name'             => 'API Stage',
            'code'             => 'api-stage',
            'lead_pipeline_id' => $pipeline->id,
            'sort_order'       => 10,
        ]);
    }
});

function apiHeaders(): array
{
    return [
        'X-API-KEY' => 'valid-api-key-123',
        'Accept'    => 'application/json',
    ];
}

test('posting sales-lead without name defaults to lead name', function () {
    // Create a lead with a resolvable name
    $lead = Lead::factory()->withPersonalData()->create();

    $response = $this->withHeaders(apiHeaders())
        ->postJson('/api/sales-leads', [
            'lead_id' => $lead->id,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', $lead->name);

    $this->assertDatabaseHas('salesleads', [
        'lead_id' => $lead->id,
        'name'    => $lead->name,
    ]);
});
