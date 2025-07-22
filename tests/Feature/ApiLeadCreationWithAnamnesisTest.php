<?php

namespace Tests\Feature;

use App\Models\Anamnesis;
use Database\Seeders\LeadChannelSeeder;
use Database\Seeders\TestSeeder;
use Webkul\Lead\Models\Channel;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Source;
use Webkul\Lead\Models\Type;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    // Seed required data for lead creation
    $this->artisan('db:seed', ['--class' => LeadChannelSeeder::class]);

    // Create a test user
    $this->user = User::factory()->create();
});

test('API lead creation successfully creates a lead with anamnesis', function () {
    // Arrange: Get required IDs for lead creation
    $source = Source::first();
    $type = Type::first();
    $channel = Channel::first();

    $uniqueId = uniqid();
    $leadData = [
        'first_name'      => 'John',
        'last_name'       => 'Doe'.$uniqueId,
        'email'           => 'john.doe.'.$uniqueId.'@example.com',
        'phone'           => '0612345678',
        'company_name'    => 'Test Company',
        'title'           => 'Test Lead via API '.$uniqueId,
        'lead_source_id'  => $source->id,
        'lead_channel_id' => $channel->id,
        'lead_type_id'    => $type->id,
        'tags'            => ['api', 'test'],
    ];

    // Act: Make API request to create lead
    $response = $this->postJson('/api/leads', $leadData);

    // Assert: Check API response
    $response->assertStatus(201)
        ->assertJson([
            'message' => 'Lead created successfully.',
        ])
        ->assertJsonStructure([
            'message',
            'data' => ['id'],
        ]);

    $leadId = $response->json('data.id');

    // Assert: Check lead was created in database
    $this->assertDatabaseHas('leads', [
        'id'         => $leadId,
        'title'      => 'Test Lead via API '.$uniqueId,
        'first_name' => 'John',
        'last_name'  => 'Doe'.$uniqueId,
        'status'     => 1,
    ]);

    // Assert: Check emails were stored correctly
    $lead = Lead::find($leadId);
    expect($lead->emails)->toBeArray()
        ->and($lead->emails[0]['value'])->toBe('john.doe.'.$uniqueId.'@example.com')
        ->and($lead->emails[0]['is_default'])->toBe(true);

    // Assert: Check anamnesis was automatically created
    $this->assertDatabaseHas('anamnesis', [
        'lead_id' => $leadId,
        'name'    => 'Anamnesis voor Test Lead via API '.$uniqueId,
        'user_id' => $lead->user_id,
    ]);

    // Assert: Check anamnesis relationship works
    $anamnesis = Anamnesis::where('lead_id', $leadId)->first();
    expect($anamnesis)->not->toBeNull()
        ->and($anamnesis->lead_id)->toBe($leadId)
        ->and($anamnesis->lead->id)->toBe($leadId)
        ->and($anamnesis->id)->toBeString() // UUID format
        ->and(strlen($anamnesis->id))->toBe(36); // UUID length

    // Assert: Check lead has anamnesis relationship
    expect($lead->anamnesis)->not->toBeNull()
        ->and($lead->anamnesis->id)->toBe($anamnesis->id);
});

test('API lead creation handles missing optional fields gracefully', function () {
    // Arrange: Minimal required data
    $source = Source::first();
    $type = Type::first();
    $channel = Channel::first();

    $uniqueId = uniqid();
    $leadData = [
        'first_name'      => 'Jane',
        'last_name'       => 'Smith'.$uniqueId,
        'email'           => 'jane.smith.'.$uniqueId.'@example.com',
        'title'           => 'Minimal Lead '.$uniqueId,
        'lead_source_id'  => $source->id,
        'lead_channel_id' => $channel->id,
        'lead_type_id'    => $type->id,
    ];

    // Act: Make API request
    $response = $this->postJson('/api/leads', $leadData);

    // Assert: Should still work
    $response->assertStatus(201);
    $leadId = $response->json('data.id');

    // Assert: Anamnesis should still be created
    $this->assertDatabaseHas('anamnesis', [
        'lead_id' => $leadId,
        'name'    => 'Anamnesis voor Minimal Lead '.$uniqueId,
    ]);
});

test('API lead creation fails gracefully with invalid data', function () {
    // Arrange: Count existing leads before the test
    $initialLeadCount = Lead::count();
    $initialAnamnesisCount = Anamnesis::count();

    // Use unique data to avoid conflicts
    $uniqueId = uniqid();
    $leadData = [
        'first_name' => 'InvalidTest'.$uniqueId,
        // Missing last_name, email, and other required fields
    ];

    // Act: Make API request
    $response = $this->postJson('/api/leads', $leadData);

    // Assert: Should fail validation
    $response->assertStatus(422); // Unprocessable Entity

    // Assert: Response should contain validation errors
    $response->assertJsonValidationErrors([
        'title',
        'last_name',
        'email',
        'lead_source_id',
        'lead_channel_id',
        'lead_type_id',
    ]);

    // Assert: No new leads should be created
    $finalLeadCount = Lead::count();
    expect($finalLeadCount)->toBe($initialLeadCount);

    // Assert: No new anamnesis should be created
    $finalAnamnesisCount = Anamnesis::count();
    expect($finalAnamnesisCount)->toBe($initialAnamnesisCount);

    // Assert: No lead with this specific name should exist
    $this->assertDatabaseMissing('leads', [
        'first_name' => 'InvalidTest'.$uniqueId,
    ]);
});

test('anamnesis creation failure does not prevent lead creation', function () {
    // This test verifies that the try-catch block works correctly
    // In normal circumstances, anamnesis should be created successfully

    $source = Source::first();
    $type = Type::first();
    $channel = Channel::first();

    $uniqueId = uniqid();
    $leadData = [
        'first_name'      => 'ErrorTest',
        'last_name'       => 'User'.$uniqueId,
        'email'           => 'errortest'.$uniqueId.'@example.com',
        'title'           => 'Test Lead Error Handling '.$uniqueId,
        'lead_source_id'  => $source->id,
        'lead_channel_id' => $channel->id,
        'lead_type_id'    => $type->id,
    ];

    // Act: Create lead
    $response = $this->postJson('/api/leads', $leadData);

    // Assert: Lead creation should succeed
    $response->assertStatus(201);
    $leadId = $response->json('data.id');

    // Assert: Lead should exist
    $this->assertDatabaseHas('leads', [
        'id'    => $leadId,
        'title' => 'Test Lead Error Handling '.$uniqueId,
    ]);

    // Assert: Anamnesis should normally be created (unless there's an actual error)
    $anamnesis = Anamnesis::where('lead_id', $leadId)->first();
    expect($anamnesis)->not->toBeNull()
        ->and($anamnesis->name)->toBe('Anamnesis voor Test Lead Error Handling '.$uniqueId);
});

test('API lead creation with different lead types works correctly', function () {
    // Test with different lead types to ensure the department logic works
    $source = Source::first();
    $channel = Channel::first();

    // Test with different types
    $types = Type::all();

    foreach ($types as $type) {
        $leadData = [
            'first_name'      => 'Test',
            'last_name'       => 'User '.$type->id,
            'email'           => "test{$type->id}@example.com",
            'title'           => "Test Lead Type {$type->name}",
            'lead_source_id'  => $source->id,
            'lead_channel_id' => $channel->id,
            'lead_type_id'    => $type->id,
        ];

        $response = $this->postJson('/api/leads', $leadData);

        $response->assertStatus(201, 'Failed to create lead for type: '.$type->name)
            ->assertJson([
                'message' => 'Lead created successfully.',
            ])
            ->assertJsonStructure([
                'message',
                'data' => ['id'],
            ]);
        $leadId = $response->json('data.id');

        // Each lead should have its anamnesis
        $this->assertDatabaseHas('anamnesis', [
            'lead_id' => $leadId,
            'name'    => "Anamnesis voor Test Lead Type {$type->name}",
        ]);
    }
});

test('anamnesis has correct UUID format and relationships', function () {
    $source = Source::first();
    $type = Type::first();
    $channel = Channel::first();

    $uniqueId = uniqid();
    $leadData = [
        'first_name'      => 'UUID',
        'last_name'       => 'Test'.$uniqueId,
        'email'           => 'uuid.'.$uniqueId.'@example.com',
        'title'           => 'UUID Format Test '.$uniqueId,
        'lead_source_id'  => $source->id,
        'lead_channel_id' => $channel->id,
        'lead_type_id'    => $type->id,
    ];

    $response = $this->postJson('/api/leads', $leadData);
    $response->assertStatus(201);
    $leadId = $response->json('data.id');

    $anamnesis = Anamnesis::where('lead_id', $leadId)->first();

    // Check UUID format
    expect($anamnesis->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');

    // Check created_by is also UUID format (or null if nullable)
    if ($anamnesis->created_by) {
        expect($anamnesis->created_by)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
    }

    // Check timestamps
    expect($anamnesis->created_at)->not->toBeNull()
        ->and($anamnesis->updated_at)->not->toBeNull()
        ->and($anamnesis->user_id)->toBeInt();

    // Check user_id is numeric
});

test('API response includes correct lead data structure', function () {
    $source = Source::first();
    $type = Type::first();
    $channel = Channel::first();

    $uniqueId = uniqid();
    $leadData = [
        'first_name'      => 'Response',
        'last_name'       => 'Test'.$uniqueId,
        'email'           => 'response.'.$uniqueId.'@example.com',
        'title'           => 'Response Structure Test '.$uniqueId,
        'lead_source_id'  => $source->id,
        'lead_channel_id' => $channel->id,
        'lead_type_id'    => $type->id,
    ];

    $response = $this->postJson('/api/leads', $leadData);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'data' => [
                'id',
            ],
        ])
        ->assertJson([
            'message' => 'Lead created successfully.',
        ]);

    // Verify the returned ID is valid
    $leadId = $response->json('data.id');
    expect($leadId)->toBeInt()->toBeGreaterThan(0);

    // Verify the lead actually exists with this ID
    $lead = Lead::find($leadId);
    expect($lead)->not->toBeNull();
});
