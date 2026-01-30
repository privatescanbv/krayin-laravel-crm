<?php

namespace Tests\Feature;

use Database\Seeders\TestSeeder;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

test('test_address_is_saved_when_creating_lead', function () {

    // Arrange
    $user = User::factory()->create();

    $leadData = [
        'emails'           => [['value' => 'test1@example.com', 'label' => 'Work']],
        'phones'           => [['value' => '111111111', 'label' => 'Mobile']],
        'entity_type'      => 'leads',
        'department_id'    => 1,
        'address'          => [
            'street'              => 'Hoofdstraat',
            'house_number'        => '123',
            'house_number_suffix' => 'A',
            'postal_code'         => '1234 AB',
            'city'                => 'Amsterdam',
            'state'               => 'Noord-Holland',
            'country'             => 'Nederland',
        ],
    ];

    // Act
    $leadRepository = app(LeadRepository::class);
    $lead = $leadRepository->create($leadData);

    // Assert - lead has address_id set
    $this->assertNotNull($lead->address_id);
    $this->assertDatabaseHas('leads', [
        'id'            => $lead->id,
        'department_id' => 1,
        'address_id'    => $lead->address_id,
    ]);

    $this->assertDatabaseHas('addresses', [
        'id'                  => $lead->address_id,
        'street'              => 'Hoofdstraat',
        'house_number'        => '123',
        'house_number_suffix' => 'A',
        'postal_code'         => '1234AB',
        'city'                => 'Amsterdam',
        'state'               => 'Noord-Holland',
        'country'             => 'Nederland',
    ]);

    // Verify relationship
    $this->assertNotNull($lead->address);
    $this->assertEquals('Hoofdstraat 123 A, 1234 AB Amsterdam, Noord-Holland, Nederland', $lead->address->full_address);
});

test('test_address_is_updated_when_updating_lead', function () {

    // Arrange
    $user = User::factory()->create();
    $leadRepository = app(LeadRepository::class);

    $leadData = [
        'emails'           => [['value' => 'test2@example.com', 'label' => 'Work']],
        'phones'           => [['value' => '222222222', 'label' => 'Mobile']],
        'entity_type'      => 'leads',
        'address'          => [
            'street'       => 'Oude Straat',
            'house_number' => '456',
            'postal_code'  => '5678 CD',
            'city'         => 'Rotterdam',
            'country'      => 'Nederland',
        ],
    ];

    $lead = $leadRepository->create($leadData);

    $updateData = [
        'emails'           => [['value' => 'updated2@example.com', 'label' => 'Work']],
        'phones'           => [['value' => '333333333', 'label' => 'Mobile']],
        'entity_type'      => 'leads',
        'address'          => [
            'street'              => 'Nieuwe Straat',
            'house_number'        => '789',
            'house_number_suffix' => 'B',
            'postal_code'         => '9012 EF',
            'city'                => 'Den Haag',
            'state'               => 'Zuid-Holland',
            'country'             => 'Nederland',
        ],
    ];

    // Act
    $updatedLead = $leadRepository->update($updateData, $lead->id);

    // Assert - lead still has the same address_id
    $this->assertNotNull($updatedLead->address_id);
    $this->assertDatabaseHas('leads', [
        'id'         => $lead->id,
        'address_id' => $updatedLead->address_id,
    ]);

    $this->assertDatabaseHas('addresses', [
        'id'                  => $updatedLead->address_id,
        'street'              => 'Nieuwe Straat',
        'house_number'        => '789',
        'house_number_suffix' => 'B',
        'postal_code'         => '9012EF',
        'city'                => 'Den Haag',
        'state'               => 'Zuid-Holland',
        'country'             => 'Nederland',
    ]);

    // Verify relationship
    $this->assertNotNull($updatedLead->address);
    $this->assertEquals('Nieuwe Straat 789 B, 9012 EF Den Haag, Zuid-Holland, Nederland', $updatedLead->address->full_address);
});

test('test_lead_factory_with_address', function () {

    // Act
    $lead = Lead::factory()->withAddress()->create();

    // Assert
    $this->assertNotNull($lead->address_id);
    $this->assertNotNull($lead->address);
    $this->assertDatabaseHas('addresses', [
        'id' => $lead->address_id,
    ]);
});
