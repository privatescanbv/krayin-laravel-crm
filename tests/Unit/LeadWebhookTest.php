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

        // Mock DB facade for the created_by update
        DB::shouldReceive('table')->with('leads')->andReturnSelf();
        DB::shouldReceive('where')->with('id', Mockery::any())->andReturnSelf();
        DB::shouldReceive('update')->with(Mockery::any())->andReturn(1);
        
        // Mock Auth facade
        Auth::shouldReceive('check')->andReturn(false);
        Auth::shouldReceive('id')->andReturn(null);
    }

    /** @test */
    public function test_webhook_not_sent_on_create_when_pipeline_will_be_updated()
    {
        // Create a mock lead that will trigger pipeline update
        $lead = Mockery::mock(Lead::class);
        $department = Mockery::mock(Department::class);
        $department->shouldReceive('getAttribute')->with('name')->andReturn('Hernia');
        $department->name = 'Hernia'; // Add property access
        
        $lead->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $lead->shouldReceive('getAttribute')->with('department')->andReturn($department);
        $lead->shouldReceive('getAttribute')->with('created_by')->andReturn(null);
        $lead->shouldReceive('getAttribute')->with('stage')->andReturn(null);
        $lead->shouldReceive('getAttribute')->with('lead_pipeline_id')
            ->andReturn(PipelineDefaultKeys::PIPELINE_TECHNICAL_ID->value); // Different from expected Hernia pipeline
        
        // Add property access for direct property calls
        $lead->id = 1;
        $lead->department = $department;
        $lead->stage = null;
        $lead->lead_pipeline_id = PipelineDefaultKeys::PIPELINE_TECHNICAL_ID->value;
        
        // Mock the leadRepository to return the same lead
        $this->leadRepository->shouldReceive('findOrFail')->with(1)->andReturn($lead);
        
        // The lead should be updated with new pipeline
        $lead->shouldReceive('update')->with([
            'lead_pipeline_id' => PipelineDefaultKeys::PIPELINE_HERNIA_ID->value,
            'lead_pipeline_stage_id' => PipelineStageDefaultKeys::PIPELINE_FIRST_STAGE_HERNIA_ID->value,
        ])->once();

        // Webhook should NOT be sent because pipeline will be updated
        $this->webhookService->shouldNotReceive('sendWebhook');

        // Call the created method
        $this->observer->created($lead);
        
        // Assert that the test ran (to avoid "no assertions" error)
        $this->assertTrue(true, 'Webhook was correctly not sent when pipeline will be updated');
    }

    /** @test */
    public function test_webhook_sent_on_create_when_pipeline_wont_be_updated()
    {
        // Create a mock lead that won't trigger pipeline update
        $lead = Mockery::mock(Lead::class);
        $department = Mockery::mock(Department::class);
        $stage = Mockery::mock();
        $stage->shouldReceive('getAttribute')->with('code')->andReturn('initial_stage');
        $stage->code = 'initial_stage'; // Add property access
        
        $department->shouldReceive('getAttribute')->with('name')->andReturn('Hernia');
        $department->name = 'Hernia'; // Add property access
        
        $lead->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $lead->shouldReceive('getAttribute')->with('department')->andReturn($department);
        $lead->shouldReceive('getAttribute')->with('created_by')->andReturn(null);
        $lead->shouldReceive('getAttribute')->with('stage')->andReturn($stage);
        $lead->shouldReceive('getAttribute')->with('lead_pipeline_id')
            ->andReturn(PipelineDefaultKeys::PIPELINE_HERNIA_ID->value); // Same as expected pipeline
        
        // Add property access for direct property calls
        $lead->id = 1;
        $lead->department = $department;
        $lead->stage = $stage;
        $lead->lead_pipeline_id = PipelineDefaultKeys::PIPELINE_HERNIA_ID->value;
        
        $lead->shouldReceive('load')->with('source')->andReturn($lead);
        $lead->shouldReceive('getAttribute')->with('source')->andReturn(null);
        $lead->source = null; // Add property access

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
        $this->observer->created($lead);
    }

    /** @test */
    public function test_webhook_sent_on_update_when_stage_changed()
    {
        // Create a mock lead with changed stage
        $lead = Mockery::mock(Lead::class);
        $stage = Mockery::mock();
        $stage->shouldReceive('getAttribute')->with('code')->andReturn('new_stage');
        $stage->code = 'new_stage'; // Add property access
        
        $lead->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $lead->shouldReceive('getAttribute')->with('stage')->andReturn($stage);
        $lead->shouldReceive('getAttribute')->with('department')->andReturn(null);
        $lead->shouldReceive('wasChanged')->with('lead_pipeline_stage_id')->andReturn(true);
        $lead->shouldReceive('load')->with('source')->andReturn($lead);
        $lead->shouldReceive('getAttribute')->with('source')->andReturn(null);

        // Add property access for direct property calls
        $lead->id = 1;
        $lead->stage = $stage;
        $lead->department = null;
        $lead->source = null;

        // Mock activity repository for logFixedFieldsActivity
        $activities = Mockery::mock();
        $activities->shouldReceive('attach')->with(Mockery::any())->andReturn(true);
        
        $activity = Mockery::mock();
        $activity->shouldReceive('getAttribute')->with('id')->andReturn(1);
        
        $this->activityRepository->shouldReceive('create')->andReturn($activity);
        $lead->shouldReceive('activities')->andReturn($activities);
        
        // Mock getOriginal calls for logFixedFieldsActivity - including lead_pipeline_stage_id
        $lead->shouldReceive('getOriginal')->with('lead_pipeline_stage_id')->andReturn(1);
        $lead->shouldReceive('getOriginal')->with('first_name')->andReturn(null);
        $lead->shouldReceive('getOriginal')->with('last_name')->andReturn(null);
        $lead->shouldReceive('getOriginal')->with('maiden_name')->andReturn(null);
        $lead->shouldReceive('getOriginal')->with('description')->andReturn(null);
        
        // Mock current field values
        $lead->shouldReceive('getAttribute')->with('first_name')->andReturn(null);
        $lead->shouldReceive('getAttribute')->with('last_name')->andReturn(null);
        $lead->shouldReceive('getAttribute')->with('maiden_name')->andReturn(null);
        $lead->shouldReceive('getAttribute')->with('description')->andReturn(null);
        
        // Add property access for direct field access
        $lead->first_name = null;
        $lead->last_name = null;
        $lead->maiden_name = null;
        $lead->description = null;
        
        $lead->shouldReceive('wasChanged')->with('first_name')->andReturn(false);
        $lead->shouldReceive('wasChanged')->with('last_name')->andReturn(false);
        $lead->shouldReceive('wasChanged')->with('maiden_name')->andReturn(false);
        $lead->shouldReceive('wasChanged')->with('description')->andReturn(false);

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
        $this->observer->updated($lead);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}