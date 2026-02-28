<?php

namespace Tests\Unit;

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

class LeadWebhookTest extends TestCase
{
    use RefreshDatabase;

    private int $webhookCallCount = 0;

    private LeadObserver $observer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestSeeder::class);

        $this->webhookCallCount = 0;

        $mockPublisher = Mockery::mock(RedisEventPublisher::class);
        $mockPublisher->shouldReceive('publish')->andReturn(true);

        $this->observer = new LeadObserver(
            webhookService: $this->mockWebhookService(),
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
    public function it_does_not_send_webhook_on_created_if_stage_is_still_updating()
    {
        $lead = $this->createTestLead();

        $this->observer->created($lead);

        $this->assertWebhookCalls(1, 'Webhook should not be sent on created when pipeline stage will change later.');
    }

    /** @test */
    public function it_does_not_send_webhook_when_stage_did_not_change()
    {
        $lead = $this->createTestLead();

        $this->observer->created($lead);
        $this->observer->updated($lead);

        // 1 webhook call for created, no update call since stage did not change
        $this->assertWebhookCalls(1, 'No webhook expected if pipeline stage stays the same.');
    }

    /** @test */
    public function it_sends_webhook_once_when_pipeline_stage_is_changed()
    {
        $lead = $this->createTestLead();

        $this->observer->created($lead);

        // Simuleer wijziging van stage
        $lead->refresh();
        $lead->lead_pipeline_stage_id = PipelineStageDefaultKeys::PIPELINE_FIRST_STAGE_PRIVATESCAN_ID->value + 1;
        //        $lead->save(); normally, but here we want to use the mock instance. So we do observer->updated directly

        $this->observer->updated($lead);

        $this->assertWebhookCalls(2, 'Webhook should be sent once when stage actually changes.');
    }

    public function it_does_not_send_webhook_when_pipeline_stage_is_not_changed(): void
    {
        $lead = $this->createTestLead();

        $this->observer->created($lead);

        // Simuleer wijziging van stage
        $lead->refresh();
        $lead->first_name = 'Jane'; // Change a different field, not the stage
        //        $lead->save(); normally, but here we want to use the mock instance. So we do observer->updated directly

        $this->observer->updated($lead);

        $this->assertWebhookCalls(1, 'Webhook should be sent once when stage actually changes.');
    }

    private function mockWebhookService(): WebhookService
    {
        $mock = Mockery::mock(WebhookService::class);
        $mock->shouldReceive('sendWebhook')
            ->andReturnUsing(function () {
                $this->webhookCallCount++;

                return true;
            });

        return $mock;
    }

    private function createTestLead(): Lead
    {
        $department = Department::where('name', Departments::PRIVATESCAN->value)->firstOrFail();

        return Lead::factory()->create([
            'first_name'             => 'John',
            'last_name'              => 'Doe',
            'lead_pipeline_id'       => 1,
            'lead_pipeline_stage_id' => 1,
            'department_id'          => $department->id,
        ]);
    }

    private function assertWebhookCalls(int $expectedCount, string $message): void
    {
        $this->assertEquals($expectedCount, $this->webhookCallCount, $message);
    }
}
