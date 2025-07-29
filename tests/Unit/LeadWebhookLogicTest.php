<?php

namespace Tests\Unit;

use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStageDefaultKeys;
use App\Models\Department;
use App\Observers\LeadObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;
use Webkul\Lead\Models\Lead;

class LeadWebhookLogicTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_will_pipeline_be_updated_returns_true_when_pipeline_differs()
    {
        // Create a mock lead with department that should trigger pipeline update
        $lead = new class {
            public $lead_pipeline_id;
            public $department;
            
            public function __construct()
            {
                $this->lead_pipeline_id = PipelineDefaultKeys::PIPELINE_TECHNICAL_ID->value;
                $this->department = new class {
                    public $name = 'Hernia';
                };
            }
        };

        // Use reflection to test the private method
        $observer = $this->app->make(LeadObserver::class);
        $reflection = new ReflectionClass($observer);
        $method = $reflection->getMethod('willPipelineBeUpdated');
        $method->setAccessible(true);

        $result = $method->invoke($observer, $lead);

        $this->assertTrue($result, 'Should return true when pipeline needs to be updated');
    }

    /** @test */
    public function test_will_pipeline_be_updated_returns_false_when_pipeline_matches()
    {
        // Create a mock lead with correct pipeline already set
        $lead = new class {
            public $lead_pipeline_id;
            public $department;
            
            public function __construct()
            {
                $this->lead_pipeline_id = PipelineDefaultKeys::PIPELINE_HERNIA_ID->value;
                $this->department = new class {
                    public $name = 'Hernia';
                };
            }
        };

        // Use reflection to test the private method
        $observer = $this->app->make(LeadObserver::class);
        $reflection = new ReflectionClass($observer);
        $method = $reflection->getMethod('willPipelineBeUpdated');
        $method->setAccessible(true);

        $result = $method->invoke($observer, $lead);

        $this->assertFalse($result, 'Should return false when pipeline is already correct');
    }

    /** @test */
    public function test_will_pipeline_be_updated_returns_false_when_no_department()
    {
        // Create a mock lead without department
        $lead = new class {
            public $lead_pipeline_id;
            public $department = null;
            
            public function __construct()
            {
                $this->lead_pipeline_id = PipelineDefaultKeys::PIPELINE_TECHNICAL_ID->value;
            }
        };

        // Use reflection to test the private method
        $observer = $this->app->make(LeadObserver::class);
        $reflection = new ReflectionClass($observer);
        $method = $reflection->getMethod('willPipelineBeUpdated');
        $method->setAccessible(true);

        $result = $method->invoke($observer, $lead);

        $this->assertFalse($result, 'Should return false when department is null');
    }

    /** @test */
    public function test_will_pipeline_be_updated_handles_privatescan_department()
    {
        // Create a mock lead with Privatescan department (not Hernia)
        $lead = new class {
            public $lead_pipeline_id;
            public $department;
            
            public function __construct()
            {
                $this->lead_pipeline_id = PipelineDefaultKeys::PIPELINE_TECHNICAL_ID->value;
                $this->department = new class {
                    public $name = 'Privatescan';
                };
            }
        };

        // Use reflection to test the private method
        $observer = $this->app->make(LeadObserver::class);
        $reflection = new ReflectionClass($observer);
        $method = $reflection->getMethod('willPipelineBeUpdated');
        $method->setAccessible(true);

        $result = $method->invoke($observer, $lead);

        $this->assertTrue($result, 'Should return true when technical pipeline needs to be updated to privatescan');
    }

    /** @test */
    public function test_will_pipeline_be_updated_handles_privatescan_department_already_correct()
    {
        // Create a mock lead with Privatescan department and correct pipeline
        $lead = new class {
            public $lead_pipeline_id;
            public $department;
            
            public function __construct()
            {
                $this->lead_pipeline_id = PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value;
                $this->department = new class {
                    public $name = 'Privatescan';
                };
            }
        };

        // Use reflection to test the private method
        $observer = $this->app->make(LeadObserver::class);
        $reflection = new ReflectionClass($observer);
        $method = $reflection->getMethod('willPipelineBeUpdated');
        $method->setAccessible(true);

        $result = $method->invoke($observer, $lead);

        $this->assertFalse($result, 'Should return false when privatescan pipeline is already correct');
    }
}