<?php

namespace Tests\Feature\DomainEvents;

use App\Actions\Leads\LeadToLostAction;
use App\Enums\Departments;
use App\Enums\PipelineStageDefaultKeys;
use App\Models\Department;
use App\Observers\LeadObserver;
use App\Repositories\SalesLeadRepository;
use App\Services\DomainEvents\RedisEventPublisher;
use App\Services\WebhookService;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;

class LeadPipelineStageChangedTest extends TestCase
{
    use RefreshDatabase;

    private LeadObserver $observer;

    private array $publishedEvents = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestSeeder::class);

        $this->publishedEvents = [];

        $mockPublisher = Mockery::mock(RedisEventPublisher::class);
        $mockPublisher->shouldReceive('publish')
            ->andReturnUsing(function (array $event) {
                $this->publishedEvents[] = $event;

                return true;
            });

        $mockWebhookService = Mockery::mock(WebhookService::class);
        $mockWebhookService->shouldReceive('sendWebhook')->andReturn(true);

        $this->observer = new LeadObserver(
            webhookService: $mockWebhookService,
            activityRepository: app(ActivityRepository::class),
            leadRepository: app(LeadRepository::class),
            salesLeadRepository: app(SalesLeadRepository::class),
            leadToLostAction: app(LeadToLostAction::class),
            redisEventPublisher: $mockPublisher,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_publishes_event_when_lead_pipeline_stage_changes(): void
    {
        $lead = $this->createTestLead();

        $oldStageId = $lead->lead_pipeline_stage_id;
        $newStageId = PipelineStageDefaultKeys::PIPELINE_FIRST_STAGE_PRIVATESCAN_ID->value + 1;

        $lead->refresh();
        $lead->lead_pipeline_stage_id = $newStageId;

        $this->observer->updated($lead);

        $this->assertCount(1, $this->publishedEvents);

        $event = $this->publishedEvents[0];
        $this->assertSame('PipelineStageChanged', $event['eventType']);
        $this->assertSame('Lead', $event['aggregateType']);
        $this->assertSame($lead->getKey(), $event['aggregateId']);
        $this->assertNotNull($event['payload']['oldStage']);
        $this->assertNotNull($event['payload']['newStage']);
    }

    /** @test */
    public function it_sets_correct_old_and_new_stage_codes_in_payload(): void
    {
        $lead = $this->createTestLead();

        $oldStageId = PipelineStageDefaultKeys::PIPELINE_FIRST_STAGE_PRIVATESCAN_ID->value;
        $newStageId = $oldStageId + 1;

        $lead->refresh();
        // Simulate the lead having the old stage stored in originals, new stage as current
        $lead->lead_pipeline_stage_id = $newStageId;

        $this->observer->updated($lead);

        $this->assertCount(1, $this->publishedEvents);
        $payload = $this->publishedEvents[0]['payload'];

        $this->assertSame($oldStageId, $payload['oldStage']['id']);
        $this->assertSame($newStageId, $payload['newStage']['id']);
    }

    /** @test */
    public function it_does_not_publish_event_when_non_stage_field_changes(): void
    {
        $lead = $this->createTestLead();
        $lead->refresh();

        // Change a non-stage field
        $lead->first_name = 'Changed';

        $this->observer->updated($lead);

        $this->assertCount(0, $this->publishedEvents);
    }

    private function createTestLead(): Lead
    {
        $department = Department::where('name', Departments::PRIVATESCAN->value)->firstOrFail();

        return Lead::factory()->create([
            'first_name'             => 'Maria',
            'last_name'              => 'Jansen',
            'lead_pipeline_id'       => 1,
            'lead_pipeline_stage_id' => PipelineStageDefaultKeys::PIPELINE_FIRST_STAGE_PRIVATESCAN_ID->value,
            'department_id'          => $department->id,
        ]);
    }
}
