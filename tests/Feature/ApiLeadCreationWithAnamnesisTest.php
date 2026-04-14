<?php

namespace Tests\Feature;

use App\Enums\ContactLabel;
use App\Models\Anamnesis;
use Database\Seeders\LeadChannelSeeder;
use Database\Seeders\TestSeeder;
use Webkul\Contact\Models\Person;
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

    // Set up test API key
    config(['api.keys' => ['test-api-key-123']]);
});

// Helper function to make API requests with authentication
function makeApiRequest($method, $uri, $data = [])
{
    return test()->withHeaders([
        'X-API-KEY'    => 'test-api-key-123',
        'Accept'       => 'application/json',
        'Content-Type' => 'application/json',
    ])->{$method}($uri, $data);
}

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
        'phone'           => '+31612345678',
        'company_name'    => 'Test Company',
        'lead_source_id'  => $source->id,
        'lead_channel_id' => $channel->id,
        'lead_type_id'    => $type->id,
        'tags'            => ['api', 'test'],
    ];

    // Act: Make API request to create lead
    $response = makeApiRequest('postJson', '/api/leads', $leadData);

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
        'first_name' => 'John',
        'last_name'  => 'Doe'.$uniqueId,
        'status'     => 0,
    ]);

    // Assert: Check emails were stored correctly
    $lead = Lead::find($leadId);
    expect($lead->emails)->toBeArray()
        ->and($lead->emails[0]['value'])->toBe('john.doe.'.$uniqueId.'@example.com')
        ->and($lead->emails[0]['is_default'])->toBe(true);

    // Create and attach a person to trigger anamnesis creation
    $person = Person::factory()->create(['user_id' => $lead->user_id, 'is_active' => true]);
    $lead->attachPersons([$person->id]);

    // Assert: Check anamnesis was created after person attachment
    $this->assertDatabaseHas('anamnesis', [
        'lead_id'   => $leadId,
        'name'      => 'Anamnesis voor '.$lead->name,
        'person_id' => $person->id,
    ]);

    // Assert: Check anamnesis relationship works
    $anamnesis = Anamnesis::where('lead_id', $leadId)->first();
    expect($anamnesis)->not->toBeNull()
        ->and($anamnesis->lead_id)->toBe($leadId)
        ->and($anamnesis->lead->id)->toBe($leadId)
        ->and($anamnesis->id)->toBeString() // UUID format
        ->and(strlen($anamnesis->id))->toBe(36);
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
        'lead_source_id'  => $source->id,
        'lead_channel_id' => $channel->id,
        'lead_type_id'    => $type->id,
    ];

    // Act: Make API request
    $response = makeApiRequest('postJson', '/api/leads', $leadData);

    // Assert: Should still work
    $response->assertStatus(201);
    $leadId = $response->json('data.id');

    $this->assertTrue(Anamnesis::count() == 0, 'Anamnesis should not be created without a person');
});

test('API lead creation fails gracefully with invalid data', function () {
    // Arrange: Count existing leads before the test
    $initialLeadCount = Lead::count();
    $initialAnamnesisCount = Anamnesis::count();

    // Get required IDs to avoid 500 errors
    $source = Source::first();
    $type = Type::first();
    $channel = Channel::first();

    // Use unique data to avoid conflicts
    $uniqueId = uniqid();
    $leadData = [
        'first_name' => 'InvalidTest'.$uniqueId,
        // Include required IDs to avoid 500 errors, but missing other required fields
        'lead_source_id'  => $source->id,
        'email'           => 'mm@mm.nl', // Invalid email format to trigger validation error
        'lead_type_id'    => $type->id,
        'lead_channel_id' => $channel->id,
        // Missing last_name, and other required fields to trigger validation errors
    ];

    // Act: Make API request
    $response = makeApiRequest('postJson', '/api/leads', $leadData);

    // Assert: Should fail validation
    $response->assertStatus(422); // Unprocessable Entity

    // Assert: Response should contain validation errors for missing required fields
    $response->assertJsonValidationErrors([
        'last_name',  // Required field that's missing
    ]);

    // Assert: Should NOT have errors for fields we provided
    $response->assertJsonMissingValidationErrors([
        'lead_source_id',  // We provided this
        'lead_channel_id', // We provided this
        'lead_type_id',    // We provided this
        'first_name',      // We provided this
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
            'lead_source_id'  => $source->id,
            'lead_channel_id' => $channel->id,
            'lead_type_id'    => $type->id,
        ];

        $response = makeApiRequest('postJson', '/api/leads', $leadData);

        $response->assertStatus(201, 'Failed to create lead for type: '.$type->name)
            ->assertJson([
                'message' => 'Lead created successfully.',
            ])
            ->assertJsonStructure([
                'message',
                'data' => ['id'],
            ]);
        $leadId = $response->json('data.id');
    }
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
        'lead_source_id'  => $source->id,
        'lead_channel_id' => $channel->id,
        'lead_type_id'    => $type->id,
    ];

    $response = makeApiRequest('postJson', '/api/leads', $leadData);

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

test('API lead creation validates email and phone array structure', function () {
    // Arrange: Get required IDs for lead creation
    $source = Source::first();
    $type = Type::first();
    $channel = Channel::first();

    $uniqueId = uniqid();

    // Test case 1: Valid email and phone structure should pass
    $validLeadData = [
        'first_name'      => 'John',
        'last_name'       => 'Doe'.$uniqueId,
        'lead_source_id'  => $source->id,
        'lead_type_id'    => $type->id,
        'lead_channel_id' => $channel->id,
        'emails'          => [
            ['value' => 'john.doe.'.$uniqueId.'@example.com', 'label' => ContactLabel::Eigen->value, 'is_default' => true],
        ],
        'phones'          => [
            ['value' => '+31612345678', 'label' => ContactLabel::Eigen->value, 'is_default' => true],
        ],
    ];

    $response = makeApiRequest('postJson', '/api/leads', $validLeadData);
    $response->assertStatus(201);

    // Test case 2: Email without label should be filled in with default label work
    $invalidEmailData = [
        'first_name'      => 'Jane',
        'last_name'       => 'Smith'.$uniqueId,
        'lead_source_id'  => $source->id,
        'lead_type_id'    => $type->id,
        'lead_channel_id' => $channel->id,
        'emails'          => [
            ['value' => 'jane.smith.'.$uniqueId.'@example.com', 'is_default' => true], // Missing label
        ],
    ];

    makeApiRequest('postJson', '/api/leads', $invalidEmailData)
        ->assertStatus(201);

    // Test case 3: Phone without label should be filled in with default label work
    $invalidPhoneData = [
        'first_name'      => 'Bob',
        'last_name'       => 'Johnson'.$uniqueId,
        'lead_source_id'  => $source->id,
        'lead_type_id'    => $type->id,
        'lead_channel_id' => $channel->id,
        'phones'          => [
            ['value' => '+31687654321', 'is_default' => true], // Missing label
        ],
    ];

    makeApiRequest('postJson', '/api/leads', $invalidPhoneData)
        ->assertStatus(201);

    // Test case 4: Invalid email label should fail
    $invalidLabelData = [
        'first_name'      => 'Alice',
        'last_name'       => 'Brown'.$uniqueId,
        'lead_source_id'  => $source->id,
        'lead_type_id'    => $type->id,
        'lead_channel_id' => $channel->id,
        'emails'          => [
            ['value' => 'alice.brown.'.$uniqueId.'@example.com', 'label' => 'invalid_label', 'is_default' => true],
        ],
    ];

    // TODO fix later
    //    $response = makeApiRequest('postJson', '/api/leads', $invalidLabelData);
    //    $response->assertStatus(400)
    //        ->assertJson([
    //            'message' => 'Lead creation failed.',
    //        ])
    //        ->assertJsonStructure([
    //            'message',
    //            'errors'
    //        ]);
});

test('API lead creation with anamnesis height and weight fields', function () {
    // Arrange: Get required IDs for lead creation
    $source = Source::first();
    $type = Type::first();
    $channel = Channel::first();

    // Create a person first
    $person = Person::factory()->create();

    $uniqueId = uniqid();
    $leadData = [
        'first_name'      => 'John',
        'last_name'       => 'Doe'.$uniqueId,
        'email'           => 'john.doe.'.$uniqueId.'@example.com',
        'phone'           => '+31612345678',
        'company_name'    => 'Test Company',
        'lead_source_id'  => $source->id,
        'lead_channel_id' => $channel->id,
        'lead_type_id'    => $type->id,
        'height'          => 175,
        'weight'          => 75,
        'metals'          => 0,
        'claustrophobia'  => 0,
        'allergies'       => 0,
        // Include person data during lead creation
        'person_ids'      => [$person->id],
    ];

    // Act: Make API request to create lead
    $response = makeApiRequest('postJson', '/api/leads', $leadData);

    // Assert: Check API response
    $response->assertStatus(201)
        ->assertJson([
            'message' => 'Lead created successfully.',
        ]);

    $leadId = $response->json('data.id');

    // Assert: Check anamnesis was created with height and weight
    $anamnesis = Anamnesis::where('lead_id', $leadId)->where('person_id', $person->id)->first();

    expect($anamnesis)->not->toBeNull()
        ->and($anamnesis->height)->toBe(175)
        ->and($anamnesis->weight)->toBe(75) // Cast as integer returns integer
        ->and($anamnesis->metals)->toBe(false)
        ->and($anamnesis->claustrophobia)->toBe(false)
        ->and($anamnesis->allergies)->toBe(false);
});

test('API lead creation without height and weight fields should work', function () {
    // Arrange: Get required IDs for lead creation
    $source = Source::first();
    $type = Type::first();
    $channel = Channel::first();

    // Create a person first to include during lead creation
    $person = Person::factory()->create();

    $uniqueId = uniqid();
    $leadData = [
        'first_name'      => 'Jane',
        'last_name'       => 'Smith'.$uniqueId,
        'email'           => 'jane.smith.'.$uniqueId.'@example.com',
        'lead_source_id'  => $source->id,
        'lead_channel_id' => $channel->id,
        'lead_type_id'    => $type->id,
        'metals'          => 1,
        'metals_notes'    => 'Some metal implants',
        'claustrophobia'  => 0,
        'allergies'       => 1,
        'allergies_notes' => 'Peanut allergy',
        // Include person data during lead creation
        'person_ids'      => [$person->id],
    ];

    // Act: Make API request to create lead
    $response = makeApiRequest('postJson', '/api/leads', $leadData);

    // Assert: Check API response
    $response->assertStatus(201);

    $leadId = $response->json('data.id');

    // Assert: Check anamnesis was created without height and weight (should be null)
    $anamnesis = Anamnesis::where('lead_id', $leadId)->where('person_id', $person->id)->first();
    expect($anamnesis)->not->toBeNull()
        ->and($anamnesis->height)->toBeNull()
        ->and($anamnesis->weight)->toBeNull()
        ->and($anamnesis->metals)->toBe(true)
        ->and($anamnesis->metals_notes)->toBe('Some metal implants')
        ->and($anamnesis->claustrophobia)->toBe(false)
        ->and($anamnesis->allergies)->toBe(true)
        ->and($anamnesis->allergies_notes)->toBe('Peanut allergy');
});
