<?php

namespace Tests\Unit;

use App\Enums\ContactLabel;
use Database\Seeders\TestSeeder;
use Tests\TestCase;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;
use Webkul\Lead\Repositories\LeadRepository;

class LeadDuplicateDetectionUnitTest extends TestCase
{
    private LeadRepository $leadRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestSeeder::class);
        $this->leadRepository = app(LeadRepository::class);
    }

    public function test_findDuplicatesByJsonField_works_with_emails()
    {
        // Create stage
        $stage = Stage::first();
        
        // Create first lead with email
        $lead1 = Lead::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User1',
            'emails' => [
                ['value' => 'test@example.com', 'label' => ContactLabel::Eigen->value],
            ],
            'lead_pipeline_stage_id' => $stage->id,
        ]);

        // Create second lead with same email
        $lead2 = Lead::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User2',
            'emails' => [
                ['value' => 'test@example.com', 'label' => ContactLabel::Relatie->value],
            ],
            'lead_pipeline_stage_id' => $stage->id,
        ]);

        // Create third lead with different email
        $lead3 = Lead::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User3',
            'emails' => [
                ['value' => 'different@example.com', 'label' => ContactLabel::Eigen->value],
            ],
            'lead_pipeline_stage_id' => $stage->id,
        ]);

        // Test the method directly using reflection
        $reflection = new \ReflectionClass($this->leadRepository);
        $method = $reflection->getMethod('findDuplicatesByJsonField');
        $method->setAccessible(true);

        // Test finding duplicates for lead1
        $duplicates = $method->invoke($this->leadRepository, $lead1, 'emails');

        // Should find lead2 as duplicate
        $this->assertCount(1, $duplicates);
        $this->assertEquals($lead2->id, $duplicates->first()->id);

        // Test finding duplicates for lead2
        $duplicates2 = $method->invoke($this->leadRepository, $lead2, 'emails');

        // Should find lead1 as duplicate
        $this->assertCount(1, $duplicates2);
        $this->assertEquals($lead1->id, $duplicates2->first()->id);

        // Test finding duplicates for lead3
        $duplicates3 = $method->invoke($this->leadRepository, $lead3, 'emails');

        // Should find no duplicates
        $this->assertCount(0, $duplicates3);
    }

    public function test_findDuplicatesByJsonField_works_with_phones()
    {
        // Create stage
        $stage = Stage::first();
        
        // Create first lead with phone
        $lead1 = Lead::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User1',
            'phones' => [
                ['value' => '+1234567890', 'label' => ContactLabel::Eigen->value],
            ],
            'lead_pipeline_stage_id' => $stage->id,
        ]);

        // Create second lead with same phone
        $lead2 = Lead::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User2',
            'phones' => [
                ['value' => '+1234567890', 'label' => ContactLabel::Relatie->value],
            ],
            'lead_pipeline_stage_id' => $stage->id,
        ]);

        // Test the method directly using reflection
        $reflection = new \ReflectionClass($this->leadRepository);
        $method = $reflection->getMethod('findDuplicatesByJsonField');
        $method->setAccessible(true);

        // Test finding duplicates for lead1
        $duplicates = $method->invoke($this->leadRepository, $lead1, 'phones');

        // Should find lead2 as duplicate
        $this->assertCount(1, $duplicates);
        $this->assertEquals($lead2->id, $duplicates->first()->id);
    }

    public function test_findDuplicatesByJsonField_handles_empty_data()
    {
        // Create stage
        $stage = Stage::first();
        
        // Create lead with empty emails
        $lead = Lead::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'emails' => [],
            'lead_pipeline_stage_id' => $stage->id,
        ]);

        // Test the method directly using reflection
        $reflection = new \ReflectionClass($this->leadRepository);
        $method = $reflection->getMethod('findDuplicatesByJsonField');
        $method->setAccessible(true);

        // Test finding duplicates
        $duplicates = $method->invoke($this->leadRepository, $lead, 'emails');

        // Should return empty collection
        $this->assertCount(0, $duplicates);
    }

    public function test_findDuplicatesByJsonField_handles_null_data()
    {
        // Create stage
        $stage = Stage::first();
        
        // Create lead with null emails
        $lead = Lead::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'emails' => null,
            'lead_pipeline_stage_id' => $stage->id,
        ]);

        // Test the method directly using reflection
        $reflection = new \ReflectionClass($this->leadRepository);
        $method = $reflection->getMethod('findDuplicatesByJsonField');
        $method->setAccessible(true);

        // Test finding duplicates
        $duplicates = $method->invoke($this->leadRepository, $lead, 'emails');

        // Should return empty collection
        $this->assertCount(0, $duplicates);
    }
}