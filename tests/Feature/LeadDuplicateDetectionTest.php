<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->leadRepository = app(LeadRepository::class);
});

test('it detects duplicate leads by email', function () {
    // Create the first lead
    $lead1 = Lead::factory()->create([
        'title'      => 'Lead 1',
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'emails'     => [
            ['value' => 'john.doe@example.com', 'label' => 'work'],
        ],
    ]);

    // Create a second lead with the same email
    $lead2 = Lead::factory()->create([
        'title'      => 'Lead 2',
        'first_name' => 'Jane',
        'last_name'  => 'Smith',
        'emails'     => [
            ['value' => 'john.doe@example.com', 'label' => 'home'],
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
        'title'      => 'Lead 1',
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'phones'     => [
            ['value' => '+1234567890', 'label' => 'mobile'],
        ],
    ]);

    // Create a second lead with the same phone
    $lead2 = Lead::factory()->create([
        'title'      => 'Lead 2',
        'first_name' => 'Jane',
        'last_name'  => 'Smith',
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
        'title'      => 'Lead 1',
        'first_name' => 'John',
        'last_name'  => 'Doe',
    ]);

    // Create a second lead with similar name
    $lead2 = Lead::factory()->create([
        'title'      => 'Lead 2',
        'first_name' => 'Johnny',
        'last_name'  => 'Doe',
    ]);

    // Test duplicate detection
    $duplicates = $this->leadRepository->findPotentialDuplicates($lead1);

    $this->assertCount(1, $duplicates);
    $this->assertEquals($lead2->id, $duplicates->first()->id);
});

test('it detects duplicate leads by full name', function () {
    // Create the first lead
    $lead1 = Lead::factory()->create([
        'title'      => 'Lead 1',
        'first_name' => 'John',
        'last_name'  => 'Doe',
    ]);

    // Create a second lead with the same full name
    $lead2 = Lead::factory()->create([
        'title'      => 'Lead 2',
        'first_name' => 'John',
        'last_name'  => 'Doe',
    ]);

    // Test duplicate detection
    $duplicates = $this->leadRepository->findPotentialDuplicates($lead1);

    $this->assertCount(1, $duplicates);
    $this->assertEquals($lead2->id, $duplicates->first()->id);
});

/** @test */
test('it returns empty collection when no duplicates exist()', function () {
    // Create a unique lead
    $lead = Lead::factory()->create([
        'title'      => 'Unique Lead',
        'first_name' => 'Unique',
        'last_name'  => 'Person',
        'emails'     => [
            ['value' => 'unique@example.com', 'label' => 'work'],
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
        'title'      => 'Test Lead',
        'first_name' => 'Test',
        'last_name'  => 'User',
        'emails'     => [
            ['value' => 'test@example.com', 'label' => 'work'],
        ],
    ]);

    // Test that the lead doesn't find itself as a duplicate
    $duplicates = $this->leadRepository->findPotentialDuplicates($lead);

    $this->assertCount(0, $duplicates);
    $this->assertFalse($duplicates->contains('id', $lead->id));
});
