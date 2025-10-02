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

    public function test_find_duplicates_by_json_field_works_with_emails()
    {
        // Create stage
        $stage = Stage::first();

        // Create first lead with email
        $lead1 = Lead::factory()->create([
            'first_name' => 'Test',
            'last_name'  => 'User1',
            'emails'     => [
                ['value' => 'test@example.com', 'label' => ContactLabel::Eigen->value],
            ],
            'lead_pipeline_stage_id' => $stage->id,
        ]);

        // Create second lead with same email
        $lead2 = Lead::factory()->create([
            'first_name' => 'Test',
            'last_name'  => 'User2',
            'emails'     => [
                ['value' => 'test@example.com', 'label' => ContactLabel::Relatie->value],
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
    }
}
