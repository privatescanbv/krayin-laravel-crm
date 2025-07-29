<?php

namespace Tests\Unit;

use App\Enums\PipelineStageDefaultKeys;
use App\Observers\LeadObserver;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;

class WebhookDuplicationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_only_one_webhook_sent_when_creating_lead_that_needs_pipeline_update()
    {
        $this->createLeadAndUpdate(true);
        $this->createLeadAndUpdate(false);
        $this->createLeadAndUpdate(true, true, 2);
    }

    private function createLeadAndUpdate(bool $updateLead = false, $updateAndChangeLead=false, $intExpectedWebhookCalls = 1): void
    {
        // Count webhook calls
        $webhookCallCount = 0;
        $webhookService = Mockery::mock(WebhookService::class);
        $webhookService->shouldReceive('sendWebhook')
            ->andReturnUsing(function () use (&$webhookCallCount) {
                $webhookCallCount++;
                return true;
            });

        $activityRepository = app(ActivityRepository::class);
        $leadRepository = app(LeadRepository::class);

        $observer = new LeadObserver(
            webhookService: $webhookService,
            activityRepository: $activityRepository,
            leadRepository: $leadRepository
        );

        $leadInstance = Lead::factory()->create([
            'title' => 'Test Lead',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'lead_pipeline_id' => 1,
            'lead_pipeline_stage_id' => 1,
        ]);

        // Step 1: Call created method (this should NOT send webhook because pipeline will be updated)
        $observer->created($leadInstance);

        if($updateAndChangeLead) {
            $leadInstance->refresh();
            $leadInstance->lead_pipeline_stage_id = PipelineStageDefaultKeys::PIPELINE_FIRST_STAGE_PRIVATESCAN_ID->value + 1; // Change to a different stage
            $leadInstance->save();
        }
        if ($updateLead) {
            // no webhook should be sent yet, because pipeline has not been changed
            $observer->updated($leadInstance);
        }

        // Assert: Only 1 webhook should have been sent total (from updated, not from created)
        $this->assertEquals($intExpectedWebhookCalls, $webhookCallCount,
            'Expected exactly 1 webhook call total. Got ' . $webhookCallCount .
            '. The fix prevents webhook in created() when pipeline will be updated, ' .
            'update without change should not have any affect.'
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
