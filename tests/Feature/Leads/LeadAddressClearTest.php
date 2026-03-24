<?php

use App\Models\Address;
use App\Repositories\AddressRepository;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Lead\Models\Lead;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->addressRepository = app(AddressRepository::class);
});

test('clearing all address fields deletes the address and clears address_id', function () {
    $lead = Lead::factory()->withAddress()->create();
    $originalAddressId = $lead->address_id;

    $this->assertNotNull($originalAddressId);
    $this->assertDatabaseHas('addresses', ['id' => $originalAddressId]);

    // Submit all empty address fields (as the form would send them)
    $this->addressRepository->upsertForEntity($lead, [
        'street'              => '',
        'house_number'        => '',
        'house_number_suffix' => '',
        'postal_code'         => '',
        'city'                => '',
        'state'               => '',
        'country'             => '',
    ]);

    $lead->refresh();

    $this->assertNull($lead->address_id);
    $this->assertDatabaseMissing('addresses', ['id' => $originalAddressId]);
});

test('clearing individual nullable field sets it to null in the database', function () {
    $address = Address::factory()->create([
        'street'       => 'Teststraat',
        'house_number' => '10',
        'postal_code'  => '1234AB',
        'city'         => 'Amsterdam',
        'country'      => 'Nederland',
    ]);
    $lead = Lead::factory()->create(['address_id' => $address->id]);

    // Clear street and country, keep house_number and postal_code
    $this->addressRepository->upsertForEntity($lead, [
        'street'       => '',
        'house_number' => '10',
        'postal_code'  => '1234AB',
        'city'         => '',
        'country'      => '',
    ]);

    $this->assertDatabaseHas('addresses', [
        'id'           => $address->id,
        'house_number' => '10',
        'postal_code'  => '1234AB',
        'street'       => null,
        'city'         => null,
        'country'      => null,
    ]);
});

test('submitting empty address data for lead without address does nothing', function () {
    $lead = Lead::factory()->create(['address_id' => null]);

    $this->assertNull($lead->address_id);

    $result = $this->addressRepository->upsertForEntity($lead, [
        'street'       => '',
        'house_number' => '',
        'postal_code'  => '',
    ]);

    $lead->refresh();

    $this->assertNull($result);
    $this->assertNull($lead->address_id);
});
