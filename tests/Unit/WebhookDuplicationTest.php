<?php

namespace Tests\Unit;

use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStageDefaultKeys;
use App\Enums\WebhookType;
use App\Observers\LeadObserver;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Lead\Repositories\LeadRepository;

class WebhookDuplicationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_only_one_webhook_sent_when_creating_lead_that_needs_pipeline_update()
    {
        // Mock facades to avoid database calls
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('update')->andReturn(1);
        Auth::shouldReceive('check')->andReturn(false);
        Auth::shouldReceive('id')->andReturn(null);

        // Count webhook calls
        $webhookCallCount = 0;
        $webhookService = Mockery::mock(WebhookService::class);
        $webhookService->shouldReceive('sendWebhook')
            ->andReturnUsing(function() use (&$webhookCallCount) {
                $webhookCallCount++;
                return true;
            });

        $activityRepository = Mockery::mock(ActivityRepository::class);
        $leadRepository = Mockery::mock(LeadRepository::class);

        $observer = new LeadObserver($webhookService, $activityRepository, $leadRepository);

        // Create a lead that will trigger pipeline update (Hernia department with technical pipeline)
        $department = new class { public $name = 'Hernia'; };
        $stage = new class { public $code = 'initial'; };
        
        $leadClass = new class {
            public $id = 1;
            public $department;
            public $created_by = null;
            public $stage;
            public $lead_pipeline_id;
            public $source = null;
            public $updateCalled = false;
            
            public function __construct($department, $stage, $pipelineId) {
                $this->department = $department;
                $this->stage = $stage;
                $this->lead_pipeline_id = $pipelineId;
            }
            
            public function load($relation) { return $this; }
            public function update($data) { $this->updateCalled = true; }
            public function wasChanged($field) { return $field === 'lead_pipeline_stage_id'; }
            public function getOriginal($field) { return 1; }
            public function activities() {
                return new class {
                    public function attach($id) { return true; }
                };
            }
        };

        $leadInstance = new $leadClass($department, $stage, PipelineDefaultKeys::PIPELINE_TECHNICAL_ID->value);

        // Mock leadRepository for the update call
        $leadRepository->shouldReceive('findOrFail')->with(1)->andReturn($leadInstance);
        
        // Mock activity repository (won't be called in created, but might be called in updated)
        $activity = new class { public $id = 1; };
        $activityRepository->shouldReceive('create')->andReturn($activity);

        // Step 1: Call created method (this should NOT send webhook because pipeline will be updated)
        $observer->created($leadInstance);
        
        // Verify pipeline was updated
        $this->assertTrue($leadInstance->updateCalled, 'Pipeline should have been updated');
        
        // Step 2: Call updated method (this SHOULD send webhook because stage changed)
        $observer->updated($leadInstance);

        // Assert: Only 1 webhook should have been sent total (from updated, not from created)
        $this->assertEquals(1, $webhookCallCount, 
            'Expected exactly 1 webhook call total. Got ' . $webhookCallCount . 
            '. The fix prevents webhook in created() when pipeline will be updated, ' .
            'allowing only the updated() method to send the webhook.'
        );
    }

    /** @test */
    public function test_webhook_sent_when_creating_lead_that_doesnt_need_pipeline_update()
    {
        // Mock facades
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('update')->andReturn(1);
        Auth::shouldReceive('check')->andReturn(false);
        Auth::shouldReceive('id')->andReturn(null);

        // Count webhook calls
        $webhookCallCount = 0;
        $webhookService = Mockery::mock(WebhookService::class);
        $webhookService->shouldReceive('sendWebhook')
            ->andReturnUsing(function() use (&$webhookCallCount) {
                $webhookCallCount++;
                return true;
            });

        $activityRepository = Mockery::mock(ActivityRepository::class);
        $leadRepository = Mockery::mock(LeadRepository::class);

        $observer = new LeadObserver($webhookService, $activityRepository, $leadRepository);

        // Create a lead that won't trigger pipeline update (Hernia department with correct pipeline)
        $department = new class { public $name = 'Hernia'; };
        $stage = new class { public $code = 'initial'; };
        
        $leadClass = new class {
            public $id = 1;
            public $department;
            public $created_by = null;
            public $stage;
            public $lead_pipeline_id;
            public $source = null;
            
            public function __construct($department, $stage, $pipelineId) {
                $this->department = $department;
                $this->stage = $stage;
                $this->lead_pipeline_id = $pipelineId;
            }
            
            public function load($relation) { return $this; }
        };

        $leadInstance = new $leadClass($department, $stage, PipelineDefaultKeys::PIPELINE_HERNIA_ID->value);

        // Call created method (this SHOULD send webhook because pipeline won't be updated)
        $observer->created($leadInstance);

        // Assert: 1 webhook should have been sent
        $this->assertEquals(1, $webhookCallCount, 
            'Expected exactly 1 webhook call when pipeline does not need updating. Got ' . $webhookCallCount
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}