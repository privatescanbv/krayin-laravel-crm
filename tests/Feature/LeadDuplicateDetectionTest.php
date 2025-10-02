<?php

namespace Tests\Feature;

use App\Enums\ContactLabel;
use App\Enums\PipelineDefaultKeys;
use Database\Seeders\TestSeeder;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;
use Webkul\Lead\Repositories\LeadRepository;

beforeEach(function () {

    $this->seed(TestSeeder::class);
    // Disable observers during testing to avoid database dependency issues
    Lead::unsetEventDispatcher();
    $this->leadRepository = app(LeadRepository::class);
    $this->defaultStage = Stage::first();
});

// Helper function to create leads with proper stage
function createLeadWithStage($data = []) {
    // Only set default stage if not already provided
    if (!isset($data['lead_pipeline_stage_id'])) {
        $stage = Stage::first();
        $data['lead_pipeline_stage_id'] = $stage->id;
    }
    return Lead::factory()->create($data);
}

test('it detects duplicate leads by email', function () {
    // Create the first lead
    $lead1 = createLeadWithStage([
        'first_name' => 'Marcus',
        'last_name'  => 'Emailtest',
        'emails'     => [
            ['value' => 'shared.email@example.com', 'label' => ContactLabel::Eigen->value],
        ],
    ]);

    // Create a second lead with the same email but different name
    $lead2 = createLeadWithStage([
        'first_name' => 'Natasha',
        'last_name'  => 'Differentname',
        'emails'     => [
            ['value' => 'shared.email@example.com', 'label' => ContactLabel::Relatie->value],
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
    $lead1 = createLeadWithStage([
        'first_name' => 'Alexander',
        'last_name'  => 'Phonetest',
        'phones'     => [
            ['value' => '+1234567890', 'label' => ContactLabel::Relatie->value],
        ],
    ]);

    // Create a second lead with the same phone but different name
    $lead2 = createLeadWithStage([
        'first_name' => 'Bethany',
        'last_name'  => 'Differentname',
        'phones'     => [
            ['value' => '+1234567890', 'label' => ContactLabel::Eigen->value],
        ],
    ]);

    // Test duplicate detection
    $duplicates = $this->leadRepository->findPotentialDuplicates($lead1);

    $this->assertCount(1, $duplicates);
    $this->assertEquals($lead2->id, $duplicates->first()->id);
});

test('it detects duplicate leads by full name', function () {
    // Create the first lead
    $lead1 = createLeadWithStage([
        'first_name' => 'Gabriel',
        'last_name'  => 'Fullnametest',
    ]);

    // Create a second lead with the exact same full name
    $lead2 = createLeadWithStage([
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
    $lead = createLeadWithStage([
        'first_name' => 'Zephyr',
        'last_name'  => 'Quintessential',
        'emails'     => [
            ['value' => 'zephyr.quintessential.unique.test@example.com', 'label' => ContactLabel::Eigen->value],
        ],
        'phones'     => [
            ['value' => '+9999999999', 'label' => ContactLabel::Relatie->value],
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
    $lead = createLeadWithStage([
        'first_name' => 'Selftest',
        'last_name'  => 'Exclusion',
        'emails'     => [
            ['value' => 'selftest.exclusion@example.com', 'label' => ContactLabel::Eigen->value],
        ],
    ]);

    // Test that the lead doesn't find itself as a duplicate
    $duplicates = $this->leadRepository->findPotentialDuplicates($lead);

    $this->assertCount(0, $duplicates);
    $this->assertFalse($duplicates->contains('id', $lead->id));
});

test('it detects duplicate leads with same name and address information', function () {
    // Create the first lead with address
    $lead1 = createLeadWithStage([
        'first_name' => 'Sarah',
        'last_name'  => 'Addresstest',
        'emails'     => [
            ['value' => 'sarah.address1@example.com', 'label' => ContactLabel::Eigen->value],
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
    $lead2 = createLeadWithStage([
        'first_name' => 'Sarah',
        'last_name'  => 'Addresstest',
        'emails'     => [
            ['value' => 'sarah.address2@example.com', 'label' => ContactLabel::Relatie->value],
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

test('it ignores leads in won status as duplicates', function () {
    // Create a stage with 'won' code
    $wonStage = Stage::where(
        'code', 'won')->where(
            'lead_pipeline_id', PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value)
        ->first();

    // Create the first lead (active lead)
    $lead1 = createLeadWithStage([
        'first_name' => 'Marcus',
        'last_name'  => 'Wontest',
        'emails'     => [
            ['value' => 'marcus.newlead@example.com', 'label' => ContactLabel::Eigen->value],
        ],
        'created_at' => now(),
    ]);

    // Create a second lead with the same email but in 'Won' status
    $lead2 = createLeadWithStage([
        'first_name' => 'Marcus',
        'last_name'  => 'Wontest',
        'emails'     => [
            ['value' => 'marcus.won@example.com', 'label' => ContactLabel::Eigen->value],
        ],
        'lead_pipeline_stage_id' => $wonStage->id,
        'created_at'             => now()->subDays(5), // Within 2 weeks but won status
    ]);

    // Test duplicate detection - should NOT find the won lead as duplicate
    $duplicates = $this->leadRepository->findPotentialDuplicates($lead1);

    $this->assertCount(0, $duplicates);
    $this->assertFalse($this->leadRepository->hasPotentialDuplicates($lead1));
});

test('it ignores leads created more than 2 weeks apart as duplicates', function () {
    // Create the first lead (current lead)
    $lead1 = createLeadWithStage([
        'first_name' => 'John',
        'last_name'  => 'Timetest',
        'emails'     => [
            ['value' => 'john.time@example.com', 'label' => ContactLabel::Eigen->value],
        ],
        'created_at' => now(),
    ]);

    // Create a second lead with the same email but created 3 weeks ago (too old)
    $lead2 = createLeadWithStage([
        'first_name' => 'John',
        'last_name'  => 'Timetest',
        'emails'     => [
            ['value' => 'john.time@example.com', 'label' => 'work'],
        ],
        'created_at' => now()->subWeeks(3),
    ]);

    // Create a third lead created 16 days ago (just over 2 weeks, should be ignored)
    $lead3 = createLeadWithStage([
        'first_name' => 'John',
        'last_name'  => 'Timetest',
        'phones'     => [
            ['value' => '+1234567890', 'label' => ContactLabel::Relatie->value],
        ],
        'created_at' => now()->subDays(16),
    ]);

    // Test duplicate detection - should NOT find old leads as duplicates
    $duplicates = $this->leadRepository->findPotentialDuplicates($lead1);

    $this->assertCount(0, $duplicates);
    $this->assertFalse($this->leadRepository->hasPotentialDuplicates($lead1));
});

test('it finds leads created within 2 weeks as duplicates', function () {
    // Create the first lead
    $lead1 = createLeadWithStage([
        'first_name' => 'Sarah',
        'last_name'  => 'Recenttest',
        'emails'     => [
            ['value' => 'sarah.recent@example.com', 'label' => ContactLabel::Eigen->value],
        ],
        'created_at' => now(),
    ]);

    // Create a second lead with the same email created 1 week ago (within 2 weeks)
    $lead2 = createLeadWithStage([
        'first_name' => 'Sarah',
        'last_name'  => 'Recenttest',
        'emails'     => [
            ['value' => 'sarah.recent@example.com', 'label' => ContactLabel::Eigen->value],
        ],
        'created_at' => now()->subWeek(1),
    ]);

    // Create a third lead with same phone created 10 days ago (within 2 weeks)
    $lead3 = createLeadWithStage([
        'first_name' => 'Sarah',
        'last_name'  => 'Recenttest',
        'phones'     => [
            ['value' => '+9876543210', 'label' => ContactLabel::Relatie->value],
        ],
        'created_at' => now()->subDays(10),
    ]);

    // Test duplicate detection - should find recent leads as duplicates
    $duplicates = $this->leadRepository->findPotentialDuplicates($lead1);

    $this->assertCount(2, $duplicates);
    $this->assertTrue($this->leadRepository->hasPotentialDuplicates($lead1));

    // Verify we get the right leads
    $duplicateIds = $duplicates->pluck('id')->toArray();
    $this->assertContains($lead2->id, $duplicateIds);
    $this->assertContains($lead3->id, $duplicateIds);
});

test('it combines time and status filters correctly', function () {
    $wonStage = Stage::where(
        'code', 'won')->where(
            'lead_pipeline_id', 1)
        ->first();

    // Create the first lead
    $lead1 = createLeadWithStage([
        'first_name' => 'Alice',
        'last_name'  => 'Combinedtest',
        'emails'     => [
            ['value' => 'alice.combined@example.com', 'label' => ContactLabel::Eigen->value],
        ],
        'created_at' => now(),
    ]);

    // Create a second lead - recent but won status (should be ignored)
    $lead2 = createLeadWithStage([
        'first_name' => 'Alice',
        'last_name'  => 'Combinedtest',
        'emails'     => [
            ['value' => 'alice.combined@example.com', 'label' => ContactLabel::Eigen->value],
        ],
        'created_at'             => now()->subDays(5),
        'lead_pipeline_stage_id' => $wonStage->id,
    ]);

    // Create a third lead - not won status but too old (should be ignored)
    $lead3 = createLeadWithStage([
        'first_name' => 'Alice',
        'last_name'  => 'Combinedtest',
        'emails'     => [
            ['value' => 'alice.combined@example.com', 'label' => ContactLabel::Eigen->value],
        ],
        'created_at' => now()->subWeeks(3),
    ]);

    // Create a fourth lead - recent and not won (should be found as duplicate)
    $lead4 = createLeadWithStage([
        'first_name' => 'Alice',
        'last_name'  => 'Combinedtest',
        'emails'     => [
            ['value' => 'alice.combined@example.com', 'label' => ContactLabel::Eigen->value],
        ],
        'created_at' => now()->subDays(7),
    ]);

    // Test duplicate detection - should only find lead4 as duplicate
    $duplicates = $this->leadRepository->findPotentialDuplicates($lead1);

    $this->assertCount(1, $duplicates);
    $this->assertEquals($lead4->id, $duplicates->first()->id);
    $this->assertTrue($this->leadRepository->hasPotentialDuplicates($lead1));
});

test('it handles edge case of exactly 2 weeks difference', function () {
    // Create the first lead
    $lead1 = createLeadWithStage([
        'first_name' => 'Edge',
        'last_name'  => 'Case',
        'emails'     => [
            ['value' => 'edge.case@example.com', 'label' => ContactLabel::Eigen->value],
        ],
        'created_at' => now(),
    ]);

    // Create a second lead exactly 2 weeks ago (should be included)
    $lead2 = createLeadWithStage([
        'first_name' => 'Edge',
        'last_name'  => 'Case',
        'emails'     => [
            ['value' => 'edge.case@example.com', 'label' => ContactLabel::Eigen->value],
        ],
        'created_at' => now()->subWeeks(2),
    ]);

    // Create a third lead exactly 2 weeks and 1 day ago (should be excluded)
    $lead3 = createLeadWithStage([
        'first_name' => 'Edge',
        'last_name'  => 'Case',
        'emails'     => [
            ['value' => 'edge.case@example.com', 'label' => ContactLabel::Eigen->value],
        ],
        'created_at' => now()->subWeeks(2)->subDay(1),
    ]);

    // Test duplicate detection - should find lead2 but not lead3
    $duplicates = $this->leadRepository->findPotentialDuplicates($lead1);

    $this->assertCount(1, $duplicates);
    $this->assertEquals($lead2->id, $duplicates->first()->id);
});

test('it proves the old behavior vs new behavior with comprehensive scenario', function () {
    // This test proves the change by showing what would have been found before vs after

    $wonStage = Stage::where(
        'code', 'won')->where(
            'lead_pipeline_id', 1)
        ->first();

    // Get the default stage for active leads
    $defaultStage = Stage::where('lead_pipeline_id', 1)->where('code', '!=', 'won')->first();

    // Create the main lead (new inquiry)
    $mainLead = createLeadWithStage([
        'first_name' => 'John',
        'last_name'  => 'Comprehensive',
        'emails'     => [
            ['value' => 'john.comprehensive@example.com', 'label' => ContactLabel::Eigen->value],
        ],
        'phones'     => [
            ['value' => '+1234567890', 'label' => ContactLabel::Relatie->value],
        ],
        'created_at'             => now(),
        'lead_pipeline_stage_id' => $defaultStage->id,
    ]);

    // Scenario 1: Old lead from 6 months ago that was won (should be ignored - both filters apply)
    $oldWonLead = createLeadWithStage([
        'first_name' => 'John',
        'last_name'  => 'Comprehensive',
        'emails'     => [
            ['value' => 'john.comprehensive@example.com', 'label' => ContactLabel::Eigen->value],
        ],
        'created_at'             => now()->subMonths(6),
        'lead_pipeline_stage_id' => $wonStage->id,
    ]);

    // Scenario 2: Recent lead (1 week ago) that was won (should be ignored - won status filter)
    $recentWonLead = createLeadWithStage([
        'first_name' => 'John',
        'last_name'  => 'Comprehensive',
        'phones'     => [
            ['value' => '+1234567890', 'label' => ContactLabel::Relatie->value],
        ],
        'created_at'             => now()->subWeek(1),
        'lead_pipeline_stage_id' => $wonStage->id,
    ]);

    // Scenario 3: Old lead (3 weeks ago) that is still active (should be ignored - time filter)
    $oldActiveLead = createLeadWithStage([
        'first_name' => 'John',
        'last_name'  => 'Comprehensive',
        'emails'     => [
            ['value' => 'john.comprehensive@example.com', 'label' => ContactLabel::Eigen->value],
        ],
        'created_at'             => now()->subWeeks(3),
        'lead_pipeline_stage_id' => $defaultStage->id,
    ]);

    // Scenario 4: Recent lead (1 week ago) that is still active (should be found - passes both filters)
    $recentActiveLead = createLeadWithStage([
        'first_name' => 'John',
        'last_name'  => 'Comprehensive',
        'emails'     => [
            ['value' => 'john.comprehensive@example.com', 'label' => 'work'],
        ],
        'created_at'             => now()->subWeek(1),
        'lead_pipeline_stage_id' => $defaultStage->id,
    ]);

    // Test the new filtering logic
    $duplicates = $this->leadRepository->findPotentialDuplicates($mainLead);

    // Debug: Show what duplicates were found
    $duplicateIds = $duplicates->pluck('id')->toArray();
    $expectedIds = [$recentActiveLead->id];
    $this->assertCount(1, $duplicates, "Should only find 1 duplicate after applying time and status filters. Found: " . implode(',', $duplicateIds) . " Expected: " . implode(',', $expectedIds));
    $this->assertEquals($recentActiveLead->id, $duplicates->first()->id, 'Should find the recent active lead');

    // Verify the filtered out leads are not in results
    $duplicateIds = $duplicates->pluck('id')->toArray();
    $this->assertNotContains($oldWonLead->id, $duplicateIds, 'Should not find old won lead');
    $this->assertNotContains($recentWonLead->id, $duplicateIds, 'Should not find recent won lead');
    $this->assertNotContains($oldActiveLead->id, $duplicateIds, 'Should not find old active lead');

    $this->assertTrue($this->leadRepository->hasPotentialDuplicates($mainLead));
});
