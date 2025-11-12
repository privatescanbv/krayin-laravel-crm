<?php

use App\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->leadRepository = app(LeadRepository::class);
});

test('it merges address from duplicate lead to primary lead', function () {
    // Create primary lead without address
    $primaryLead = Lead::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
    ]);

    // Create duplicate lead with address
    $duplicateLead = Lead::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
    ]);

    // Create address for duplicate lead
    $duplicateAddress = Address::create([
        'lead_id'             => $duplicateLead->id,
        'street'              => 'Test Street',
        'house_number'        => '123',
        'house_number_suffix' => 'A',
        'postal_code'         => '1234AB',
        'city'                => 'Test City',
        'state'               => 'Test State',
        'country'             => 'Test Country',
    ]);

    // Verify initial state
    $this->assertNull($primaryLead->fresh()->address);
    $this->assertNotNull($duplicateLead->fresh()->address);

    // Perform merge with address from duplicate lead
    $fieldMappings = [
        'address' => $duplicateLead->id,
    ];

    $mergedLead = $this->leadRepository->mergeLeads(
        $primaryLead->id,
        [$duplicateLead->id],
        $fieldMappings
    );

    // Verify that primary lead now has the address from duplicate lead
    $this->assertNotNull($mergedLead->address);
    $this->assertEquals('Test Street', $mergedLead->address->street);
    $this->assertEquals('123', $mergedLead->address->house_number);
    $this->assertEquals('A', $mergedLead->address->house_number_suffix);
    $this->assertEquals('1234AB', $mergedLead->address->postal_code);
    $this->assertEquals('Test City', $mergedLead->address->city);
    $this->assertEquals('Test State', $mergedLead->address->state);
    $this->assertEquals('Test Country', $mergedLead->address->country);

    // Verify full_address accessor works
    $this->assertEquals('Test Street 123 A, 1234 AB Test City, Test State, Test Country', $mergedLead->address->full_address);

    // Verify duplicate lead is deleted
    $this->assertNull(Lead::find($duplicateLead->id));
});

test('it overwrites existing address when merging', function () {
    // Create primary lead with existing address
    $primaryLead = Lead::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
    ]);

    Address::create([
        'lead_id'      => $primaryLead->id,
        'street'       => 'Old Street',
        'house_number' => '456',
        'postal_code'  => '5678CD',
        'city'         => 'Old City',
        'country'      => 'Old Country',
    ]);

    // Create duplicate lead with different address
    $duplicateLead = Lead::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
    ]);

    Address::create([
        'lead_id'             => $duplicateLead->id,
        'street'              => 'New Street',
        'house_number'        => '789',
        'house_number_suffix' => 'B',
        'postal_code'         => '9012EF',
        'city'                => 'New City',
        'state'               => 'New State',
        'country'             => 'New Country',
    ]);

    // Perform merge choosing address from duplicate lead
    $fieldMappings = [
        'address' => $duplicateLead->id,
    ];

    $mergedLead = $this->leadRepository->mergeLeads(
        $primaryLead->id,
        [$duplicateLead->id],
        $fieldMappings
    );

    // Verify that primary lead's address is replaced with duplicate's address
    $this->assertNotNull($mergedLead->address);
    $this->assertEquals('New Street', $mergedLead->address->street);
    $this->assertEquals('789', $mergedLead->address->house_number);
    $this->assertEquals('B', $mergedLead->address->house_number_suffix);
    $this->assertEquals('9012EF', $mergedLead->address->postal_code);
    $this->assertEquals('New City', $mergedLead->address->city);
    $this->assertEquals('New State', $mergedLead->address->state);
    $this->assertEquals('New Country', $mergedLead->address->country);
});

test('it keeps primary address when not specified in field mappings', function () {
    // Create primary lead with address
    $primaryLead = Lead::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
    ]);

    Address::create([
        'lead_id'      => $primaryLead->id,
        'street'       => 'Primary Street',
        'house_number' => '111',
        'postal_code'  => '1111AA',
        'city'         => 'Primary City',
        'country'      => 'Primary Country',
    ]);

    // Create duplicate lead with different address
    $duplicateLead = Lead::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
    ]);

    Address::create([
        'lead_id'      => $duplicateLead->id,
        'street'       => 'Duplicate Street',
        'house_number' => '222',
        'postal_code'  => '2222BB',
        'city'         => 'Duplicate City',
        'country'      => 'Duplicate Country',
    ]);

    // Perform merge without specifying address in field mappings
    $fieldMappings = [
    ];

    $mergedLead = $this->leadRepository->mergeLeads(
        $primaryLead->id,
        [$duplicateLead->id],
        $fieldMappings
    );

    // Verify that primary lead keeps its original address
    $this->assertNotNull($mergedLead->address);
    $this->assertEquals('Primary Street', $mergedLead->address->street);
    $this->assertEquals('111', $mergedLead->address->house_number);
    $this->assertEquals('1111AA', $mergedLead->address->postal_code);
    $this->assertEquals('Primary City', $mergedLead->address->city);
    $this->assertEquals('Primary Country', $mergedLead->address->country);
});
