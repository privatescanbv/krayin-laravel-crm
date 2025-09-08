<?php

namespace Tests\Feature;

use App\Services\LeadStatusTransitionValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\Contact\Models\Person;

class LeadStatusTransitionValidationTest extends TestCase
{
    use RefreshDatabase;

    private Lead $lead;
    private Stage $startStage;
    private Stage $followUpStage;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test pipeline
        $pipeline = Pipeline::create([
            'name' => 'Test Pipeline',
            'is_default' => 1,
            'type' => 'lead',
        ]);

        // Create test stages
        $this->startStage = Stage::create([
            'code' => 'klant-adviseren-start',
            'name' => 'Klant adviseren',
            'probability' => 100,
            'sort_order' => 1,
            'lead_pipeline_id' => $pipeline->id,
        ]);

        $this->followUpStage = Stage::create([
            'code' => 'klant-adviseren-opvolgen',
            'name' => 'Klant adviseren opvolgen',
            'probability' => 100,
            'sort_order' => 2,
            'lead_pipeline_id' => $pipeline->id,
        ]);

        // Create a test lead
        $this->lead = Lead::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'lead_pipeline_id' => $pipeline->id,
            'lead_pipeline_stage_id' => $this->startStage->id,
        ]);

        // Configure the validation rule
        LeadStatusTransitionValidator::addTransitionRule(
            'klant-adviseren-start',
            'klant-adviseren-opvolgen',
            [
                'min_persons' => 1,
                'message' => 'Voor de status "Klant adviseren opvolgen" moet minimaal 1 persoon aan de lead gekoppeld zijn.',
            ]
        );
    }

    /** @test */
    public function it_blocks_transition_when_no_persons_are_attached()
    {
        // Lead has no persons attached
        $this->assertEquals(0, $this->lead->persons_count);

        // Attempt to transition should fail
        $this->expectException(ValidationException::class);
        
        LeadStatusTransitionValidator::validateTransition($this->lead, $this->followUpStage->id);
    }

    /** @test */
    public function it_allows_transition_when_persons_are_attached()
    {
        // Create and attach a person to the lead
        $person = Person::create([
            'name' => 'Jane Doe',
            'emails' => [['value' => 'jane@example.com', 'is_default' => true]],
        ]);

        $this->lead->attachPersons([$person->id]);
        
        // Refresh the lead to get updated persons_count
        $this->lead->refresh();
        
        $this->assertEquals(1, $this->lead->persons_count);

        // Transition should succeed
        $this->expectNotToPerformAssertions();
        
        LeadStatusTransitionValidator::validateTransition($this->lead, $this->followUpStage->id);
    }

    /** @test */
    public function it_allows_transition_when_multiple_persons_are_attached()
    {
        // Create and attach multiple persons to the lead
        $person1 = Person::create([
            'name' => 'Jane Doe',
            'emails' => [['value' => 'jane@example.com', 'is_default' => true]],
        ]);

        $person2 = Person::create([
            'name' => 'Bob Smith',
            'emails' => [['value' => 'bob@example.com', 'is_default' => true]],
        ]);

        $this->lead->attachPersons([$person1->id, $person2->id]);
        
        // Refresh the lead to get updated persons_count
        $this->lead->refresh();
        
        $this->assertEquals(2, $this->lead->persons_count);

        // Transition should succeed
        $this->expectNotToPerformAssertions();
        
        LeadStatusTransitionValidator::validateTransition($this->lead, $this->followUpStage->id);
    }

    /** @test */
    public function it_ignores_validation_for_transitions_without_rules()
    {
        // Create a stage without validation rules
        $otherStage = Stage::create([
            'code' => 'other-stage',
            'name' => 'Other Stage',
            'probability' => 100,
            'sort_order' => 3,
            'lead_pipeline_id' => $this->lead->pipeline->id,
        ]);

        // Transition should succeed even without persons
        $this->expectNotToPerformAssertions();
        
        LeadStatusTransitionValidator::validateTransition($this->lead, $otherStage->id);
    }

    /** @test */
    public function it_works_with_lead_model_update_method()
    {
        // Lead has no persons attached
        $this->assertEquals(0, $this->lead->persons_count);

        // Attempt to update stage should fail
        $this->expectException(ValidationException::class);
        
        $this->lead->update(['lead_pipeline_stage_id' => $this->followUpStage->id]);
    }

    /** @test */
    public function it_works_with_lead_model_update_stage_method()
    {
        // Lead has no persons attached
        $this->assertEquals(0, $this->lead->persons_count);

        // Attempt to update stage should fail
        $this->expectException(ValidationException::class);
        
        $this->lead->updateStage($this->followUpStage->id);
    }

    /** @test */
    public function it_can_add_and_remove_transition_rules()
    {
        // Remove the existing rule
        LeadStatusTransitionValidator::removeTransitionRule(
            'klant-adviseren-start',
            'klant-adviseren-opvolgen'
        );

        // Now transition should succeed even without persons
        $this->expectNotToPerformAssertions();
        
        LeadStatusTransitionValidator::validateTransition($this->lead, $this->followUpStage->id);

        // Add the rule back
        LeadStatusTransitionValidator::addTransitionRule(
            'klant-adviseren-start',
            'klant-adviseren-opvolgen',
            [
                'min_persons' => 1,
                'message' => 'Test message',
            ]
        );

        // Now transition should fail again
        $this->expectException(ValidationException::class);
        
        LeadStatusTransitionValidator::validateTransition($this->lead, $this->followUpStage->id);
    }
}