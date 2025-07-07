<?php

namespace Tests\Feature;

use App\Models\Address;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Source;
use Webkul\Lead\Models\Type;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

test('test_address_is_saved_when_creating_lead', function () {

    // Arrange
    $user = User::factory()->create();
    $source = Source::first() ?? Source::create(['name' => 'Website']);
    $type = Type::first() ?? Type::create(['name' => 'New Lead']);

    $leadData = [
        'title'          => 'Test Lead',
        'lead_source_id' => $source->id,
        'lead_type_id'   => $type->id,
        'user_id'        => $user->id,
        'entity_type'    => 'leads',
        'address'        => [
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

    // Assert
    $this->assertDatabaseHas('leads', [
        'id'    => $lead->id,
        'title' => 'Test Lead',
    ]);

    $this->assertDatabaseHas('addresses', [
        'lead_id'             => $lead->id,
        'street'              => 'Hoofdstraat',
        'house_number'        => '123',
        'house_number_suffix' => 'A',
        'postal_code'         => '1234 AB',
        'city'                => 'Amsterdam',
        'state'               => 'Noord-Holland',
        'country'             => 'Nederland',
    ]);

    // Verify relationship
    $this->assertNotNull($lead->address);
    $this->assertEquals('Hoofdstraat 123 A, 1234 AB Amsterdam, Noord-Holland, Nederland', $lead->address->full_address);
});

test('test_address_validation_requires_lead_id_or_person_id', function () {
    // Test dat aanmaken van een adres zonder lead_id of person_id faalt
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Either lead_id or person_id must be provided');

    Address::create([
        'street'       => 'Hoofdstraat',
        'house_number' => '123',
        'city'         => 'Amsterdam',
    ]);
});

test(' test_address_validation_prevents_both_lead_id_and_person_id', function () {
    // Test dat aanmaken van een adres met zowel lead_id als person_id faalt
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Cannot set both lead_id and person_id');

    Address::create([
        'lead_id'      => 1,
        'person_id'    => 1,
        'street'       => 'Hoofdstraat',
        'house_number' => '123',
        'city'         => 'Amsterdam',
    ]);
});
