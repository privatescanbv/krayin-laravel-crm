<?php

/**
 * LeadKanbanDatabaseColumnsTest
 *
 * This test suite ensures that the kanban board functionality works correctly
 * without database column errors. It specifically tests that:
 *
 * 1. The kanban endpoint loads without trying to select computed attributes
 *    like 'leads.name' or 'leads.rotten_days' directly from the database
 * 2. Computed attributes are properly handled by the Lead model
 * 3. The kanban board works with various lead data scenarios
 *
 * These tests would fail if someone accidentally tries to select computed
 * attributes directly in database queries, preventing regression of the
 * "Unknown column 'leads.name' in 'field list'" error.
 */

use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\DB;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    // Create and authenticate a back-office user
    $this->user = User::factory()->create(['name' => 'Admin Tester']);
    // Authenticate on the admin guard used by backend routes
    $this->actingAs($this->user, 'user');
    // voorkom auth-redirects in deze test
    $this->withoutMiddleware(Authenticate::class);

    // Ensure we have a pipeline and stage
    $this->pipeline = Pipeline::first();
    $this->stage = Stage::first();
    if (! $this->pipeline || ! $this->stage) {
        throw new Exception('Pipeline or Stage not found. Ensure TestSeeder provisions them.');
    }
});

test('kanban board loads without database column errors', function () {
    // Create a lead with all the necessary fields for name computation
    $lead = Lead::factory()->create([
        'first_name'             => 'Jan',
        'last_name'              => 'Jansen',
        'lastname_prefix'        => 'van',
        'married_name'           => 'de Vries',
        'married_name_prefix'    => 'van',
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    // Test the kanban endpoint that was previously failing
    $response = $this->getJson(route('admin.leads.get', [
        'pipeline_id' => $this->pipeline->id,
    ]));

    $response->assertOk();

    // Verify the response structure
    $response->assertJsonStructure([
        '*' => [
            'id',
            'name',
            'leads' => [
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'first_name',
                        'last_name',
                        'rotten_days',
                        'created_at',
                        'stage',
                        'user',
                        'type',
                        'source',
                        'tags',
                        'persons',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'per_page',
                    'to',
                    'total',
                ],
            ],
        ],
    ]);

    // Verify that the lead data is present and name is computed correctly
    $responseData = $response->json();
    $stageData = collect($responseData)->first();
    $leads = $stageData['leads']['data'];

    expect($leads)->not->toBeEmpty();

    $foundLead = collect($leads)->firstWhere('id', $lead->id);
    expect($foundLead)->not->toBeNull();
    expect($foundLead['name'])->toBe('Jan van Jansen / van de Vries');
    expect($foundLead['first_name'])->toBe('Jan');
    expect($foundLead['last_name'])->toBe('Jansen');
    expect($foundLead['rotten_days'])->toBeInt();
});

test('kanban board handles leads with minimal name data', function () {
    // Create a lead with only first_name and last_name
    $lead = Lead::factory()->create([
        'first_name'             => 'Piet',
        'last_name'              => 'Pietersen',
        'lastname_prefix'        => null,
        'married_name'           => null,
        'married_name_prefix'    => null,
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    $response = $this->getJson(route('admin.leads.get', [
        'pipeline_id' => $this->pipeline->id,
    ]));

    $response->assertOk();

    $responseData = $response->json();
    $stageData = collect($responseData)->first();
    $leads = $stageData['leads']['data'];

    $foundLead = collect($leads)->firstWhere('id', $lead->id);
    expect($foundLead)->not->toBeNull();
    expect($foundLead['name'])->toBe('Piet Pietersen');
});

test('kanban board works with multiple stages', function () {
    // Create another stage in the same pipeline
    $stage2 = Stage::factory()->create([
        'lead_pipeline_id' => $this->pipeline->id,
        'name'             => 'Second Stage',
        'code'             => 'second_stage',
        'sort_order'       => 2,
    ]);

    // Create leads in both stages
    $lead1 = Lead::factory()->create([
        'first_name'             => 'Lead',
        'last_name'              => 'One',
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    $lead2 = Lead::factory()->create([
        'first_name'             => 'Lead',
        'last_name'              => 'Two',
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $stage2->id,
        'user_id'                => $this->user->id,
    ]);

    $response = $this->getJson(route('admin.leads.get', [
        'pipeline_id' => $this->pipeline->id,
    ]));

    $response->assertOk();

    $responseData = $response->json();

    // Should have data for both stages
    expect($responseData)->toHaveCount(2);

    // Both leads should be present
    $allLeads = collect($responseData)->flatMap(fn ($stage) => $stage['leads']['data']);
    $leadIds = $allLeads->pluck('id');

    expect($leadIds)->toContain($lead1->id);
    expect($leadIds)->toContain($lead2->id);
});

test('kanban board handles empty stages gracefully', function () {
    // Create a stage with no leads
    $emptyStage = Stage::factory()->create([
        'lead_pipeline_id' => $this->pipeline->id,
        'name'             => 'Empty Stage',
        'code'             => 'empty_stage',
        'sort_order'       => 2,
    ]);

    $response = $this->getJson(route('admin.leads.get', [
        'pipeline_id' => $this->pipeline->id,
    ]));

    $response->assertOk();

    $responseData = $response->json();

    // Should have data for both stages
    expect($responseData)->toHaveCount(2);

    // Find the empty stage
    $emptyStageData = collect($responseData)->firstWhere('id', $emptyStage->id);
    expect($emptyStageData)->not->toBeNull();
    expect($emptyStageData['leads']['data'])->toBeEmpty();
    expect($emptyStageData['leads']['meta']['total'])->toBe(0);
});

test('kanban board fails with proper error when invalid columns are selected', function () {
    // This test would fail if someone tries to select 'leads.name' or 'leads.rotten_days'
    // directly from the database, as these are computed attributes

    // Create a lead
    $lead = Lead::factory()->create([
        'first_name'             => 'Test',
        'last_name'              => 'Lead',
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    // The kanban endpoint should work without errors
    $response = $this->getJson(route('admin.leads.get', [
        'pipeline_id' => $this->pipeline->id,
    ]));

    // Should not return a 500 error due to unknown column
    $response->assertOk();

    // Should not contain SQL error messages
    $response->assertDontSee('Unknown column');
    $response->assertDontSee('leads.name');
    $response->assertDontSee('SQLSTATE');
});

test('direct database query with computed attributes fails', function () {
    // This test demonstrates that selecting computed attributes directly from the database
    // will fail, which is the behavior we want to prevent in the kanban query

    $lead = Lead::factory()->create([
        'first_name'             => 'Test',
        'last_name'              => 'Lead',
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    // This should fail because 'name' is not a database column
    expect(function () use ($lead) {
        DB::table('leads')
            ->select(['id', 'first_name', 'last_name', 'name']) // 'name' is computed, not a DB column
            ->where('id', $lead->id)
            ->get();
    })->toThrow(Exception::class);

    // This should also fail because 'rotten_days' is not a database column
    expect(function () use ($lead) {
        DB::table('leads')
            ->select(['id', 'first_name', 'last_name', 'rotten_days']) // 'rotten_days' is computed, not a DB column
            ->where('id', $lead->id)
            ->get();
    })->toThrow(Exception::class);

    // But this should work - selecting actual database columns
    $result = DB::table('leads')
        ->select(['id', 'first_name', 'last_name', 'lastname_prefix', 'married_name', 'married_name_prefix'])
        ->where('id', $lead->id)
        ->get();

    expect($result)->not->toBeEmpty();
    expect($result->first()->first_name)->toBe('Test');
    expect($result->first()->last_name)->toBe('Lead');
});

test('lead model correctly computes name and rotten_days attributes', function () {
    // This test verifies that the Lead model correctly computes the name and rotten_days
    // attributes when loaded from the database

    $lead = Lead::factory()->create([
        'first_name'             => 'Jan',
        'last_name'              => 'Jansen',
        'lastname_prefix'        => 'van',
        'married_name'           => 'de Vries',
        'married_name_prefix'    => 'van',
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    // Load the lead fresh from the database
    $loadedLead = Lead::find($lead->id);

    // Verify computed attributes work correctly
    expect($loadedLead->name)->toBe('Jan van Jansen / van de Vries');
    expect($loadedLead->rotten_days)->toBeInt();
    expect($loadedLead->rotten_days)->toBeGreaterThanOrEqual(0);

    // Verify the attributes are in the appends array
    expect($loadedLead->getAppends())->toContain('name');
    expect($loadedLead->getAppends())->toContain('rotten_days');
});
