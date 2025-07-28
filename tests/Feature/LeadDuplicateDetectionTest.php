<?php

namespace Tests\Feature;

use App\Models\Address;
use Database\Seeders\TestSeeder;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;

beforeEach(function () {

    $this->seed(TestSeeder::class);
    // Disable observers during testing to avoid database dependency issues
    Lead::unsetEventDispatcher();
    $this->leadRepository = app(LeadRepository::class);
});

test('it detects duplicate leads by email', function () {
    // Create the first lead
    $lead1 = Lead::factory()->create([
        'title'      => 'Email Test Lead 1',
        'first_name' => 'Marcus',
        'last_name'  => 'Emailtest',
        'emails'     => [
            ['value' => 'shared.email@example.com', 'label' => 'work'],
        ],
    ]);

    // Create a second lead with the same email but different name
    $lead2 = Lead::factory()->create([
        'title'      => 'Email Test Lead 2',
        'first_name' => 'Natasha',
        'last_name'  => 'Differentname',
        'emails'     => [
            ['value' => 'shared.email@example.com', 'label' => 'home'],
        ],
    ]);

    // Test duplicate detection
    $duplicates = $this->leadRepository->findPotentialDuplicates($lead1);

    $this->assertCount(1, $duplicates);
    $this->assertEquals($lead2->id, $duplicates->first()->id);
    $this->assertTrue($lead1->hasPotentialDuplicates());
    $this->assertEquals(1, $lead1->getPotentialDuplicatesCount());
});

test('it detects duplicate leads by phone', function () {
    // Create the first lead
    $lead1 = Lead::factory()->create([
        'title'      => 'Phone Test Lead 1',
        'first_name' => 'Alexander',
        'last_name'  => 'Phonetest',
        'phones'     => [
            ['value' => '+1234567890', 'label' => 'mobile'],
        ],
    ]);

    // Create a second lead with the same phone but different name
    $lead2 = Lead::factory()->create([
        'title'      => 'Phone Test Lead 2',
        'first_name' => 'Bethany',
        'last_name'  => 'Differentname',
        'phones'     => [
            ['value' => '+1234567890', 'label' => 'work'],
        ],
    ]);

    // Test duplicate detection
    $duplicates = $this->leadRepository->findPotentialDuplicates($lead1);

    $this->assertCount(1, $duplicates);
    $this->assertEquals($lead2->id, $duplicates->first()->id);
});

test('it detects duplicate leads by name similarity', function () {
    // Create the first lead
    $lead1 = Lead::factory()->create([
        'title'      => 'Name Similarity Test Lead 1',
        'first_name' => 'John',
        'last_name'  => 'Similaritytest',
    ]);

    // Create a second lead with similar name (nickname variation)
    $lead2 = Lead::factory()->create([
        'title'      => 'Name Similarity Test Lead 2',
        'first_name' => 'Johnny',
        'last_name'  => 'Similaritytest',
    ]);

    // Test duplicate detection
    $duplicates = $this->leadRepository->findPotentialDuplicates($lead1);

    $this->assertCount(1, $duplicates);
    $this->assertEquals($lead2->id, $duplicates->first()->id);
});

test('it detects duplicate leads by full name', function () {
    // Create the first lead
    $lead1 = Lead::factory()->create([
        'title'      => 'Full Name Test Lead 1',
        'first_name' => 'Gabriel',
        'last_name'  => 'Fullnametest',
    ]);

    // Create a second lead with the exact same full name
    $lead2 = Lead::factory()->create([
        'title'      => 'Full Name Test Lead 2',
        'first_name' => 'Gabriel',
        'last_name'  => 'Fullnametest',
    ]);

    // Test duplicate detection
    $duplicates = $this->leadRepository->findPotentialDuplicates($lead1);

    $this->assertCount(1, $duplicates);
    $this->assertEquals($lead2->id, $duplicates->first()->id);
});

/** @test */
test('it returns empty collection when no duplicates exist', function () {
    // Create a lead with very unique data that shouldn't match anything
    $lead = Lead::factory()->create([
        'title'      => 'Absolutely Unique Lead XYZ123',
        'first_name' => 'Zephyr',
        'last_name'  => 'Quintessential',
        'emails'     => [
            ['value' => 'zephyr.quintessential.unique.test@example.com', 'label' => 'work'],
        ],
        'phones'     => [
            ['value' => '+9999999999', 'label' => 'mobile'],
        ],
    ]);

    // Test no duplicates
    $duplicates = $this->leadRepository->findPotentialDuplicates($lead);

    $this->assertCount(0, $duplicates);
    $this->assertFalse($lead->hasPotentialDuplicates());
    $this->assertEquals(0, $lead->getPotentialDuplicatesCount());
});

test('it excludes self from duplicate detection', function () {
    // Create a lead
    $lead = Lead::factory()->create([
        'title'      => 'Self Exclusion Test Lead',
        'first_name' => 'Selftest',
        'last_name'  => 'Exclusion',
        'emails'     => [
            ['value' => 'selftest.exclusion@example.com', 'label' => 'work'],
        ],
    ]);

    // Test that the lead doesn't find itself as a duplicate
    $duplicates = $this->leadRepository->findPotentialDuplicates($lead);

    $this->assertCount(0, $duplicates);
    $this->assertFalse($duplicates->contains('id', $lead->id));
});

test('it detects duplicate leads with same name and address information', function () {
    // Create the first lead with address
    $lead1 = Lead::factory()->create([
        'title'      => 'Address Test Lead 1',
        'first_name' => 'Sarah',
        'last_name'  => 'Addresstest',
        'emails'     => [
            ['value' => 'sarah.address1@example.com', 'label' => 'work'],
        ],
    ]);

    // Create address for first lead
    $lead1->address()->create([
        'street'              => 'Main Street',
        'house_number'        => '123',
        'house_number_suffix' => 'A',
        'postal_code'         => '1234AB',
        'city'                => 'Test City',
        'state'               => 'Test State',
        'country'             => 'Netherlands',
    ]);

    // Create a second lead with same name but different email and same address details
    $lead2 = Lead::factory()->create([
        'title'      => 'Address Test Lead 2',
        'first_name' => 'Sarah',
        'last_name'  => 'Addresstest',
        'emails'     => [
            ['value' => 'sarah.address2@example.com', 'label' => 'home'],
        ],
    ]);

    // Create identical address for second lead
    $lead2->address()->create([
        'street'              => 'Main Street',
        'house_number'        => '123',
        'house_number_suffix' => 'A',
        'postal_code'         => '1234AB',
        'city'                => 'Test City',
        'state'               => 'Test State',
        'country'             => 'Netherlands',
    ]);

    // Test duplicate detection - should find duplicate based on name match
    $duplicates = $this->leadRepository->findPotentialDuplicates($lead1);

    $this->assertCount(1, $duplicates);
    $this->assertEquals($lead2->id, $duplicates->first()->id);

    // Verify both leads have the same address data
    $this->assertEquals($lead1->address->street, $lead2->address->street);
    $this->assertEquals($lead1->address->house_number, $lead2->address->house_number);
    $this->assertEquals($lead1->address->postal_code, $lead2->address->postal_code);
    $this->assertEquals($lead1->address->city, $lead2->address->city);
    $this->assertEquals($lead1->address->full_address, $lead2->address->full_address);

    // Test that address merge functionality works correctly
    $this->assertTrue($lead1->hasPotentialDuplicates());
    $this->assertEquals(1, $lead1->getPotentialDuplicatesCount());
});
