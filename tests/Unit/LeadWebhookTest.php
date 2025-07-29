<?php

namespace Tests\Unit;

use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStageDefaultKeys;
use App\Enums\WebhookType;
use App\Models\Department;
use App\Observers\LeadObserver;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;

class LeadWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected $webhookService;
    protected $activityRepository;
    protected $leadRepository;
    protected $observer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->webhookService = Mockery::mock(WebhookService::class);
        $this->activityRepository = Mockery::mock(ActivityRepository::class);
        $this->leadRepository = Mockery::mock(LeadRepository::class);
        
        $this->observer = new LeadObserver(
            $this->webhookService,
            $this->activityRepository,
            $this->leadRepository
        );

        // Mock DB facade for the created_by update - allow any calls
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('update')->andReturn(1);
        
        // Mock Auth facade - allow any calls
        Auth::shouldReceive('check')->andReturn(false);
        Auth::shouldReceive('id')->andReturn(null);
    }

    /** @test */
    public function test_webhook_not_sent_on_create_when_pipeline_will_be_updated()
    {
        // Create a simple mock object with just the properties we need
        $department = new class {
            public $name = 'Hernia';
        };
        
        $lead = new class {
            public $id = 1;
            public $department;
            public $created_by = null;
            public $stage = null;
            public $lead_pipeline_id;
            public $source = null;
            public $updateCalled = false;
            public $updateData = null;
            
            public function __construct($department, $pipelineId) {
                $this->department = $department;
                $this->lead_pipeline_id = $pipelineId;
            }
            
            public function load($relation) {
                return $this;
            }
            
            public function update($data) {
                $this->updateCalled = true;
                $this->updateData = $data;
            }
        };
        
        $leadInstance = new $lead($department, PipelineDefaultKeys::PIPELINE_TECHNICAL_ID->value);
        
        // Mock the leadRepository to return the same lead
        $this->leadRepository->shouldReceive('findOrFail')->with(1)->andReturn($leadInstance);

        // Webhook should NOT be sent because pipeline will be updated
        $this->webhookService->shouldNotReceive('sendWebhook');

        // Call the created method
        $this->observer->created($leadInstance);
        
        // Assert that update was called (pipeline was updated)
        $this->assertTrue($leadInstance->updateCalled, 'Pipeline update should have been called');
        $this->assertEquals([
            'lead_pipeline_id' => PipelineDefaultKeys::PIPELINE_HERNIA_ID->value,
            'lead_pipeline_stage_id' => PipelineStageDefaultKeys::PIPELINE_FIRST_STAGE_HERNIA_ID->value,
        ], $leadInstance->updateData);
    }

    /** @test */
    public function test_webhook_sent_on_create_when_pipeline_wont_be_updated()
    {
        // Create simple mock objects
        $stage = new class {
            public $code = 'initial_stage';
        };
        
        $department = new class {
            public $name = 'Hernia';
        };
        
        $lead = new class {
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
            
            public function load($relation) {
                return $this;
            }
        };
        
        $leadInstance = new $lead($department, $stage, PipelineDefaultKeys::PIPELINE_HERNIA_ID->value);

        // Webhook SHOULD be sent because pipeline won't be updated
        $this->webhookService->shouldReceive('sendWebhook')
            ->once()
            ->with(
                Mockery::type('array'),
                WebhookType::LEAD_PIPELINE_STAGE_CHANGE,
                'LeadObserver@created'
            )
            ->andReturn(true);

        // Call the created method
        $this->observer->created($leadInstance);
    }

    /** @test */
    public function test_webhook_sent_on_update_when_stage_changed()
    {
        // Create simple mock objects
        $stage = new class {
            public $code = 'new_stage';
        };
        
        $activities = new class {
            public function attach($id) {
                return true;
            }
        };
        
        $activity = new class {
            public $id = 1;
        };
        
        $lead = new class {
            public $id = 1;
            public $stage;
            public $department = null;
            public $source = null;
            public $first_name = null;
            public $last_name = null;
            public $maiden_name = null;
            public $description = null;
            private $originalValues = [
                'lead_pipeline_stage_id' => 1,
                'first_name' => null,
                'last_name' => null,
                'maiden_name' => null,
                'description' => null,
            ];
            private $changedFields = ['lead_pipeline_stage_id'];
            
            public function __construct($stage) {
                $this->stage = $stage;
            }
            
            public function load($relation) {
                return $this;
            }
            
            public function wasChanged($field) {
                return in_array($field, $this->changedFields);
            }
            
            public function getOriginal($field) {
                return $this->originalValues[$field] ?? null;
            }
            
            public function activities() {
                return new class {
                    public function attach($id) {
                        return true;
                    }
                };
            }
        };
        
        $leadInstance = new $lead($stage);

        // Mock activity repository for logFixedFieldsActivity
        $this->activityRepository->shouldReceive('create')->andReturn($activity);

        // Webhook should be sent for stage change
        $this->webhookService->shouldReceive('sendWebhook')
            ->once()
            ->with(
                Mockery::type('array'),
                WebhookType::LEAD_PIPELINE_STAGE_CHANGE,
                'LeadObserver@updated'
            )
            ->andReturn(true);

        // Call the updated method
        $this->observer->updated($leadInstance);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}