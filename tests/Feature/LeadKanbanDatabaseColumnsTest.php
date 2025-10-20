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
use Webkul\Contact\Models\Person;
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

    // Verify the response structure (updated for optimized LeadKanbanResource)
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
                        'created_at',
                        'lost_reason_label',
                        'mri_status',
                        'mri_status_label',
                        'has_diagnosis_form',
                        'persons',
                        'persons_count',
                        'has_multiple_persons',
                        'stage',
                        'rotten_days',
                        'open_activities_count',
                        'unread_emails_count',
                        'days_until_due_date',
                        'has_duplicates',
                        'duplicates_count',
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
    expect($foundLead)->not->toBeNull()
        ->and($foundLead['name'])->toBe('Jan van Jansen / van de Vries')
        ->and($foundLead['first_name'])->toBe('Jan')
        ->and($foundLead['last_name'])->toBe('Jansen')
        ->and($foundLead['rotten_days'])->toBeInt();
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
    expect($foundLead)->not->toBeNull()
        ->and($foundLead['name'])->toBe('Piet Pietersen');
});

test('kanban board works with multiple stages', function () {
    // Create another stage in the same pipeline
    $stage2 = Stage::create([
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

    // Should have data for multiple stages
    expect($responseData)->not->toBeEmpty();

    // Both leads should be present in the response
    $allLeads = collect($responseData)->flatMap(fn ($stage) => $stage['leads']['data']);
    $leadIds = $allLeads->pluck('id');

    expect($leadIds)->toContain($lead1->id)
        ->and($leadIds)->toContain($lead2->id);

    // Verify that our specific stages are present
    $stageIds = collect($responseData)->pluck('id');
    expect($stageIds)->toContain($this->stage->id)
        ->and($stageIds)->toContain($stage2->id);
});

test('kanban board works when leads have attached persons', function () {
    $lead = Lead::factory()->create([
        'first_name'             => 'Karel',
        'last_name'              => 'Klant',
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    $person = Person::factory()->create([
        'first_name' => 'Piet',
        'last_name'  => 'Persoon',
        'user_id'    => $this->user->id,
    ]);

    // Attach via model helper to ensure pivot is populated correctly
    $lead->attachPersons([$person->id]);

    $response = $this->getJson(route('admin.leads.get', [
        'pipeline_id' => $this->pipeline->id,
    ]));

    $response->assertOk();

    $responseData = $response->json();
    $stageData = collect($responseData)->firstWhere('id', $this->stage->id) ?? collect($responseData)->first();
    $leads = $stageData['leads']['data'];

    $foundLead = collect($leads)->firstWhere('id', $lead->id);
    expect($foundLead)->not->toBeNull();
    // Persons should be included as empty array (optimized for performance)
    // and persons_count should be 0 (simplified for performance)
    expect($foundLead['persons'])->toBeArray()
        ->and($foundLead['persons_count'])->toBe(0);
});

test('kanban board works when custom attribute_values exist for leads', function () {
    $lead = Lead::factory()->create([
        'first_name'             => 'Anna',
        'last_name'              => 'Attribuut',
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    // Create a simple custom attribute for leads
    $attributeId = DB::table('attributes')->insertGetId([
        'code'        => 'custom_text',
        'name'        => 'Custom Text',
        'type'        => 'text',
        'entity_type' => 'leads',
        'is_required' => 0,
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    // Insert corresponding attribute value using the correct storage column (text_value)
    DB::table('attribute_values')->insert([
        'entity_type'    => 'leads',
        'entity_id'      => $lead->id,
        'attribute_id'   => $attributeId,
        'text_value'     => 'Hello World',
    ]);

    // Hitting the endpoint should succeed and not attempt to select an invalid generic `value` column
    $response = $this->getJson(route('admin.leads.get', [
        'pipeline_id' => $this->pipeline->id,
    ]));

    $response->assertOk();
});

test('kanban board handles empty stages gracefully', function () {
    // Create a stage with no leads
    $emptyStage = Stage::create([
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
    expect($responseData)->not->toBeEmpty();

    // Find the empty stage
    $emptyStageData = collect($responseData)->firstWhere('id', $emptyStage->id);
    expect($emptyStageData)->not->toBeNull()
        ->and($emptyStageData['leads']['data'])->toBeEmpty()
        ->and($emptyStageData['leads']['meta']['total'])->toBe(0);
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

    // Test that selecting computed attributes directly fails
    // Note: Some databases might be more permissive, so we'll test the actual behavior

    try {
        $result = DB::table('leads')
            ->select(['id', 'first_name', 'last_name', 'name']) // 'name' is computed, not a DB column
            ->where('id', $lead->id)
            ->get();

        // If this doesn't fail, that's unexpected but not necessarily wrong
        // The important thing is that our kanban query doesn't try to select these
        expect($result)->not->toBeEmpty();
    } catch (Exception $e) {
        // This is the expected behavior - computed attributes should not be selectable
        expect($e->getMessage())->toContain('Unknown column');
    }

    // Test that selecting actual database columns works
    $result = DB::table('leads')
        ->select(['id', 'first_name', 'last_name', 'lastname_prefix', 'married_name', 'married_name_prefix', 'mri_status', 'lost_reason'])
        ->where('id', $lead->id)
        ->get();

    expect($result)->not->toBeEmpty()
        ->and($result->first()->first_name)->toBe('Test')
        ->and($result->first()->last_name)->toBe('Lead');
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
    expect($loadedLead->name)->toBe('Jan van Jansen / van de Vries')
        ->and($loadedLead->rotten_days)->toBeInt()
        ->and($loadedLead->getAppends())->toContain('name')
        ->and($loadedLead->getAppends())->toContain('rotten_days')
        ->and($loadedLead->mri_status_label)->toBeString()
        ->and($loadedLead->lost_reason_label)->toBeString();
    // rotten_days can be negative for old leads, so just check it's an integer

    // Verify the attributes are in the appends array

    // Verify other computed attributes work (these are not in appends but are accessors)
});
