<?php

namespace Tests\Feature;

use Database\Seeders\TestSeeder;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\User\Models\User;

// beforeEach(function () {
//
//    $this->seed(TestSeeder::class);
// });
test('test_address_is_saved_when_creating_person', function () {

    // Arrange
    $user = User::factory()->create();

    $personData = [
        'first_name'            => 'Test Person',
        'emails'                => [['value' => 'test1@example.com', 'label' => 'Work']],
        'phones'                => [['value' => '111111111', 'label' => 'Mobile']],
        'entity_type'           => 'persons',
        'user_id'               => $user->id, // Ensure user_id is set
        'address'               => [
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
    $personRepository = app(PersonRepository::class);
    $person = $personRepository->create($personData);

    // Assert
    $this->assertDatabaseHas('persons', [
        'id'         => $person->id,
        'first_name' => 'Test Person',
    ]);

    $this->assertDatabaseHas('addresses', [
        'person_id'           => $person->id,
        'street'              => 'Hoofdstraat',
        'house_number'        => '123',
        'house_number_suffix' => 'A',
        'postal_code'         => '1234AB',
        'city'                => 'Amsterdam',
        'state'               => 'Noord-Holland',
        'country'             => 'Nederland',
    ]);

    // Verify relationship
    $this->assertNotNull($person->address);
    $this->assertEquals('Hoofdstraat 123 A, 1234 AB Amsterdam, Noord-Holland, Nederland', $person->address->full_address);
});

test('test_address_is_updated_when_updating_person', function () {

    // Arrange
    $user = User::factory()->create();
    $personRepository = app(PersonRepository::class);

    $personData = [
        'first_name'            => 'Test Person',
        'emails'                => [['value' => 'test2@example.com', 'label' => 'Work']],
        'phones'                => [['value' => '222222222', 'label' => 'Mobile']],
        'entity_type'           => 'persons',
        'user_id'               => $user->id, // Ensure user_id is set
        'address'               => [
            'street'       => 'Oude Straat',
            'house_number' => '456',
            'postal_code'  => '5678 CD',
            'city'         => 'Rotterdam',
            'country'      => 'Nederland',
        ],
    ];

    $person = $personRepository->create($personData);

    $updateData = [
        'first_name'            => 'Updated Person',
        'emails'                => [['value' => 'updated2@example.com', 'label' => 'Work']],
        'phones'                => [['value' => '333333333', 'label' => 'Mobile']],
        'entity_type'           => 'persons',
        'address'               => [
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
    $updatedPerson = $personRepository->update($updateData, $person->id);

    // Assert
    $this->assertDatabaseHas('persons', [
        'id'         => $person->id,
        'first_name' => 'Updated Person',
    ]);

    $this->assertDatabaseHas('addresses', [
        'person_id'           => $person->id,
        'street'              => 'Nieuwe Straat',
        'house_number'        => '789',
        'house_number_suffix' => 'B',
        'postal_code'         => '9012EF',
        'city'                => 'Den Haag',
        'state'               => 'Zuid-Holland',
        'country'             => 'Nederland',
    ]);

    // Verify relationship
    $this->assertNotNull($updatedPerson->address);
    $this->assertEquals('Nieuwe Straat 789 B, 9012 EF Den Haag, Zuid-Holland, Nederland', $updatedPerson->address->full_address);
});

test('test_person_factory_with_address', function () {

    // Act
    $person = Person::factory()->withAddress()->create();

    // Assert
    $this->assertNotNull($person->address);
    $this->assertDatabaseHas('addresses', [
        'person_id' => $person->id,
    ]);
});
