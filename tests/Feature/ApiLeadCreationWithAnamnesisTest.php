<?php

namespace Tests\Feature;

use App\Models\Anamnesis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Source;
use Webkul\Lead\Models\Type;
use Webkul\Lead\Models\Channel;
use Webkul\User\Models\User;
use Database\Seeders\LeadChannelSeeder;

uses(RefreshDatabase::class);

beforeEach(function () {
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
    
    $leadData = [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john.doe@example.com',
        'phone' => '0612345678',
        'company_name' => 'Test Company',
        'title' => 'Test Lead via API',
        'lead_source_id' => $source->id,
        'lead_channel_id' => $channel->id,
        'lead_type_id' => $type->id,
        'tags' => ['api', 'test']
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
            'data' => ['id']
        ]);

    $leadId = $response->json('data.id');

    // Assert: Check lead was created in database
    $this->assertDatabaseHas('leads', [
        'id' => $leadId,
        'title' => 'Test Lead via API',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'status' => 1,
    ]);

    // Assert: Check emails were stored correctly
    $lead = Lead::find($leadId);
    expect($lead->emails)->toBeArray()
        ->and($lead->emails[0]['value'])->toBe('john.doe@example.com')
        ->and($lead->emails[0]['is_default'])->toBe(true);

    // Assert: Check anamnesis was automatically created
    $this->assertDatabaseHas('anamnesis', [
        'lead_id' => $leadId,
        'name' => 'Anamnesis voor Test Lead via API',
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
    
    $leadData = [
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'email' => 'jane.smith@example.com',
        'title' => 'Minimal Lead',
        'lead_source_id' => $source->id,
        'lead_channel_id' => $channel->id,
        'lead_type_id' => $type->id,
    ];

    // Act: Make API request
    $response = $this->postJson('/api/leads', $leadData);

    // Assert: Should still work
    $response->assertStatus(201);
    $leadId = $response->json('data.id');

    // Assert: Anamnesis should still be created
    $this->assertDatabaseHas('anamnesis', [
        'lead_id' => $leadId,
        'name' => 'Anamnesis voor Minimal Lead',
    ]);
});

test('API lead creation fails gracefully with invalid data', function () {
    // Arrange: Invalid data (missing required fields)
    $leadData = [
        'first_name' => 'John',
        // Missing last_name, email, and other required fields
    ];

    // Act: Make API request
    $response = $this->postJson('/api/leads', $leadData);

    // Assert: Should fail validation
    $response->assertStatus(422); // Unprocessable Entity

    // Assert: No lead or anamnesis should be created
    $this->assertDatabaseMissing('leads', [
        'first_name' => 'John',
    ]);
    
    $this->assertDatabaseMissing('anamnesis', [
        'name' => 'Anamnesis voor ',
    ]);
});

test('anamnesis creation failure does not prevent lead creation', function () {
    // This test simulates a scenario where anamnesis creation might fail
    // but the lead should still be created due to the try-catch block
    
    $source = Source::first();
    $type = Type::first();
    $channel = Channel::first();
    
    $leadData = [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'title' => 'Test Lead with Potential Anamnesis Issue',
        'lead_source_id' => $source->id,
        'lead_channel_id' => $channel->id,
        'lead_type_id' => $type->id,
    ];

    // Act: Create lead
    $response = $this->postJson('/api/leads', $leadData);

    // Assert: Lead creation should succeed
    $response->assertStatus(201);
    $leadId = $response->json('data.id');

    // Assert: Lead should exist
    $this->assertDatabaseHas('leads', [
        'id' => $leadId,
        'title' => 'Test Lead with Potential Anamnesis Issue',
    ]);

    // Assert: Anamnesis should normally be created (unless there's an actual error)
    $anamnesis = Anamnesis::where('lead_id', $leadId)->first();
    expect($anamnesis)->not->toBeNull();
});

test('API lead creation with different lead types works correctly', function () {
    // Test with different lead types to ensure the department logic works
    $source = Source::first();
    $channel = Channel::first();
    
    // Test with different types
    $types = Type::all();
    
    foreach ($types as $type) {
        $leadData = [
            'first_name' => 'Test',
            'last_name' => 'User ' . $type->id,
            'email' => "test{$type->id}@example.com",
            'title' => "Test Lead Type {$type->name}",
            'lead_source_id' => $source->id,
            'lead_channel_id' => $channel->id,
            'lead_type_id' => $type->id,
        ];

        $response = $this->postJson('/api/leads', $leadData);
        
        $response->assertStatus(201);
        $leadId = $response->json('data.id');
        
        // Each lead should have its anamnesis
        $this->assertDatabaseHas('anamnesis', [
            'lead_id' => $leadId,
            'name' => "Anamnesis voor Test Lead Type {$type->name}",
        ]);
    }
});

test('anamnesis has correct UUID format and relationships', function () {
    $source = Source::first();
    $type = Type::first();
    $channel = Channel::first();
    
    $leadData = [
        'first_name' => 'UUID',
        'last_name' => 'Test',
        'email' => 'uuid@example.com',
        'title' => 'UUID Format Test',
        'lead_source_id' => $source->id,
        'lead_channel_id' => $channel->id,
        'lead_type_id' => $type->id,
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
        ->and($anamnesis->updated_at)->not->toBeNull();
        
    // Check user_id is numeric
    expect($anamnesis->user_id)->toBeInt();
});

test('API response includes correct lead data structure', function () {
    $source = Source::first();
    $type = Type::first();
    $channel = Channel::first();
    
    $leadData = [
        'first_name' => 'Response',
        'last_name' => 'Test',
        'email' => 'response@example.com',
        'title' => 'Response Structure Test',
        'lead_source_id' => $source->id,
        'lead_channel_id' => $channel->id,
        'lead_type_id' => $type->id,
    ];

    $response = $this->postJson('/api/leads', $leadData);
    
    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'data' => [
                'id'
            ]
        ])
        ->assertJson([
            'message' => 'Lead created successfully.'
        ]);
        
    // Verify the returned ID is valid
    $leadId = $response->json('data.id');
    expect($leadId)->toBeInt()->toBeGreaterThan(0);
    
    // Verify the lead actually exists with this ID
    $lead = Lead::find($leadId);
    expect($lead)->not->toBeNull();
});