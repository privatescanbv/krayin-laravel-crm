<?php

namespace Tests\Unit;

use App\Models\Address;
use App\Services\LeadStatusTransitionValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use ReflectionClass;
use Tests\TestCase;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;

class LeadStatusTransitionValidatorTest extends TestCase
{
    use RefreshDatabase;

    private Lead $lead;

    private Person $person;

    private Stage $wonStage;

    private Stage $lostStage;

    private Stage $otherStage;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset validator state
        LeadStatusTransitionValidator::reset();

        // Create pipeline and stages
        $pipeline = Pipeline::create([
            'name'        => 'Test Pipeline',
            'rotten_days' => 30,
        ]);

        $this->wonStage = Stage::create([
            'code'             => 'won',
            'name'             => 'Gewonnen',
            'probability'      => 100,
            'sort_order'       => 1,
            'lead_pipeline_id' => $pipeline->id,
        ]);

        $this->lostStage = Stage::create([
            'code'             => 'lost',
            'name'             => 'Verloren',
            'probability'      => 0,
            'sort_order'       => 2,
            'lead_pipeline_id' => $pipeline->id,
        ]);

        $this->otherStage = Stage::create([
            'code'             => 'nieuwe-aanvraag-kwalificeren',
            'name'             => 'Nieuwe aanvraag kwalificeren',
            'probability'      => 10,
            'sort_order'       => 0,
            'lead_pipeline_id' => $pipeline->id,
        ]);

        // Create test lead
        $this->lead = Lead::create([
            'first_name'             => 'John',
            'last_name'              => 'Doe',
            'lastname_prefix'        => 'van',
            'emails'                 => [['value' => 'john.doe@example.com', 'is_default' => true]],
            'phones'                 => [['value' => '0612345678', 'is_default' => true]],
            'lead_pipeline_stage_id' => $this->otherStage->id,
            'lead_pipeline_id'       => $pipeline->id,
        ]);

        // Create test person with matching data
        $this->person = Person::create([
            'first_name'      => 'John',
            'last_name'       => 'Doe',
            'lastname_prefix' => 'van',
            'emails'          => [['value' => 'john.doe@example.com', 'is_default' => true]],
            'phones'          => [['value' => '0612345678', 'is_default' => true]],
        ]);
    }

    /** @test */
    public function it_allows_transition_to_won_when_lead_has_exactly_one_person_with_100_percent_match()
    {
        // Attach exactly one person to the lead
        $this->lead->attachPersons([$this->person->id]);

        // This should not throw an exception
        LeadStatusTransitionValidator::validateTransition($this->lead, $this->wonStage->id);

        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /** @test */
    public function it_allows_transition_to_lost_when_lead_has_exactly_one_person_with_100_percent_match()
    {
        // Attach exactly one person to the lead
        $this->lead->attachPersons([$this->person->id]);

        // This should not throw an exception
        LeadStatusTransitionValidator::validateTransition($this->lead, $this->lostStage->id);

        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /** @test */
    public function it_prevents_transition_to_won_when_lead_has_no_persons()
    {
        // Lead has no persons attached

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('precies 1 persoon aan gekoppeld is');

        LeadStatusTransitionValidator::validateTransition($this->lead, $this->wonStage->id);
    }

    /** @test */
    public function it_prevents_transition_to_lost_when_lead_has_no_persons()
    {
        // Lead has no persons attached

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('precies 1 persoon aan gekoppeld is');

        LeadStatusTransitionValidator::validateTransition($this->lead, $this->lostStage->id);
    }

    /** @test */
    public function it_prevents_transition_to_won_when_lead_has_multiple_persons()
    {
        // Create a second person
        $person2 = Person::create([
            'first_name' => 'Jane',
            'last_name'  => 'Smith',
            'emails'     => [['value' => 'jane.smith@example.com', 'is_default' => true]],
        ]);

        // Attach multiple persons to the lead
        $this->lead->attachPersons([$this->person->id, $person2->id]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('precies 1 persoon aan gekoppeld is');

        LeadStatusTransitionValidator::validateTransition($this->lead, $this->wonStage->id);
    }

    /** @test */
    public function it_prevents_transition_to_lost_when_lead_has_multiple_persons()
    {
        // Create a second person
        $person2 = Person::create([
            'first_name' => 'Jane',
            'last_name'  => 'Smith',
            'emails'     => [['value' => 'jane.smith@example.com', 'is_default' => true]],
        ]);

        // Attach multiple persons to the lead
        $this->lead->attachPersons([$this->person->id, $person2->id]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('precies 1 persoon aan gekoppeld is');

        LeadStatusTransitionValidator::validateTransition($this->lead, $this->lostStage->id);
    }

    /** @test */
    public function it_prevents_transition_to_won_when_match_score_is_less_than_100_percent()
    {
        // Create a person with different data (lower match score)
        $personWithDifferentData = Person::create([
            'first_name' => 'John',
            'last_name'  => 'Smith', // Different last name
            'emails'     => [['value' => 'john.smith@example.com', 'is_default' => true]], // Different email
        ]);

        // Attach the person with different data
        $this->lead->attachPersons([$personWithDifferentData->id]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('contact match score 100% is');

        LeadStatusTransitionValidator::validateTransition($this->lead, $this->wonStage->id);
    }

    /** @test */
    public function it_prevents_transition_to_lost_when_match_score_is_less_than_100_percent()
    {
        // Create a person with different data (lower match score)
        $personWithDifferentData = Person::create([
            'first_name' => 'John',
            'last_name'  => 'Smith', // Different last name
            'emails'     => [['value' => 'john.smith@example.com', 'is_default' => true]], // Different email
        ]);

        // Attach the person with different data
        $this->lead->attachPersons([$personWithDifferentData->id]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('contact match score 100% is');

        LeadStatusTransitionValidator::validateTransition($this->lead, $this->lostStage->id);
    }

    /** @test */
    public function it_allows_transition_to_other_stages_without_validation()
    {
        // Create another stage that's not won/lost
        $anotherStage = Stage::create([
            'code'             => 'klant-adviseren',
            'name'             => 'Klant adviseren',
            'probability'      => 50,
            'sort_order'       => 3,
            'lead_pipeline_id' => $this->lead->lead_pipeline_id,
        ]);

        // This should not throw an exception even without persons
        LeadStatusTransitionValidator::validateTransition($this->lead, $anotherStage->id);

        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /** @test */
    public function it_calculates_match_score_correctly_for_perfect_match()
    {
        // Attach the person with matching data
        $this->lead->attachPersons([$this->person->id]);

        // Use reflection to test the private method
        $reflection = new ReflectionClass(LeadStatusTransitionValidator::class);
        $method = $reflection->getMethod('calculateMatchScore');
        $method->setAccessible(true);

        $score = $method->invokeArgs(null, [$this->lead, $this->person]);

        $this->assertEquals(100.0, $score, 'Perfect match should result in 100% score');
    }

    /** @test */
    public function it_calculates_match_score_correctly_for_partial_match()
    {
        // Create a person with partially matching data
        $personWithPartialMatch = Person::create([
            'first_name'      => 'John', // Matches
            'last_name'       => 'Doe', // Matches
            'lastname_prefix' => 'van', // Matches
            'emails'          => [['value' => 'different@example.com', 'is_default' => true]], // Different email
            'phones'          => [['value' => '0698765432', 'is_default' => true]], // Different phone
        ]);

        // Attach the person with partial match
        $this->lead->attachPersons([$personWithPartialMatch->id]);

        // Use reflection to test the private method
        $reflection = new ReflectionClass(LeadStatusTransitionValidator::class);
        $method = $reflection->getMethod('calculateMatchScore');
        $method->setAccessible(true);

        $score = $method->invokeArgs(null, [$this->lead, $personWithPartialMatch]);

        $this->assertLessThan(100.0, $score, 'Partial match should result in less than 100% score');
        $this->assertGreaterThan(0.0, $score, 'Partial match should result in more than 0% score');
    }

    /** @test */
    public function it_handles_empty_fields_correctly_in_match_calculation()
    {
        // Create a lead with minimal data
        $minimalLead = Lead::create([
            'first_name'             => 'John',
            'last_name'              => 'Doe',
            'lead_pipeline_stage_id' => $this->otherStage->id,
            'lead_pipeline_id'       => $this->lead->lead_pipeline_id,
        ]);

        // Create a person with matching minimal data
        $minimalPerson = Person::create([
            'first_name' => 'John',
            'last_name'  => 'Doe',
        ]);

        // Attach the person
        $minimalLead->attachPersons([$minimalPerson->id]);

        // This should NOT throw an exception because it's a perfect match
        // (name fields match + empty email/phone/address fields match = 100% score)
        LeadStatusTransitionValidator::validateTransition($minimalLead, $this->wonStage->id);

        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /** @test */
    public function it_prevents_transition_when_lead_has_data_but_person_has_empty_fields()
    {
        // Create a lead with email data
        $leadWithEmail = Lead::create([
            'first_name'             => 'John',
            'last_name'              => 'Doe',
            'emails'                 => [['value' => 'john.doe@example.com', 'is_default' => true]],
            'lead_pipeline_stage_id' => $this->otherStage->id,
            'lead_pipeline_id'       => $this->lead->lead_pipeline_id,
        ]);

        // Create a person with matching names but no email
        $personWithoutEmail = Person::create([
            'first_name' => 'John',
            'last_name'  => 'Doe',
        ]);

        // Attach the person
        $leadWithEmail->attachPersons([$personWithoutEmail->id]);

        // This should throw an exception because match score will be less than 100%
        // (name fields match but email mismatch = ~90% score)
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('contact match score 100% is');

        LeadStatusTransitionValidator::validateTransition($leadWithEmail, $this->wonStage->id);
    }

    /** @test */
    public function it_handles_date_of_birth_matching_correctly()
    {
        // Create lead with date of birth and complete matching data
        $leadWithDob = Lead::create([
            'first_name'             => 'John',
            'last_name'              => 'Doe',
            'date_of_birth'          => '1990-01-01',
            'emails'                 => [['value' => 'john.doe@example.com', 'is_default' => true]],
            'phones'                 => [['value' => '0612345678', 'is_default' => true]],
            'lead_pipeline_stage_id' => $this->otherStage->id,
            'lead_pipeline_id'       => $this->lead->lead_pipeline_id,
        ]);

        // Create person with matching date of birth and complete data
        $personWithDob = Person::create([
            'first_name'    => 'John',
            'last_name'     => 'Doe',
            'date_of_birth' => '1990-01-01',
            'emails'        => [['value' => 'john.doe@example.com', 'is_default' => true]],
            'phones'        => [['value' => '0612345678', 'is_default' => true]],
        ]);

        // Attach the person
        $leadWithDob->attachPersons([$personWithDob->id]);

        // This should not throw an exception (complete match = 100% score)
        LeadStatusTransitionValidator::validateTransition($leadWithDob, $this->wonStage->id);

        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /** @test */
    public function it_handles_different_date_of_birth_correctly()
    {
        // Create lead with date of birth
        $leadWithDob = Lead::create([
            'first_name'             => 'John',
            'last_name'              => 'Doe',
            'date_of_birth'          => '1990-01-01',
            'lead_pipeline_stage_id' => $this->otherStage->id,
            'lead_pipeline_id'       => $this->lead->lead_pipeline_id,
        ]);

        // Create person with different date of birth
        $personWithDifferentDob = Person::create([
            'first_name'    => 'John',
            'last_name'     => 'Doe',
            'date_of_birth' => '1991-01-01',
        ]);

        // Attach the person
        $leadWithDob->attachPersons([$personWithDifferentDob->id]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('contact match score 100% is');

        LeadStatusTransitionValidator::validateTransition($leadWithDob, $this->wonStage->id);
    }

    /** @test */
    public function it_handles_address_matching_correctly()
    {
        // Create lead with address and complete matching data
        $leadWithAddress = Lead::create([
            'first_name'             => 'John',
            'last_name'              => 'Doe',
            'emails'                 => [['value' => 'john.doe@example.com', 'is_default' => true]],
            'phones'                 => [['value' => '0612345678', 'is_default' => true]],
            'lead_pipeline_stage_id' => $this->otherStage->id,
            'lead_pipeline_id'       => $this->lead->lead_pipeline_id,
        ]);

        // Create person with matching address and complete data
        $personWithAddress = Person::create([
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'emails'     => [['value' => 'john.doe@example.com', 'is_default' => true]],
            'phones'     => [['value' => '0612345678', 'is_default' => true]],
        ]);

        // Create addresses for both
        $leadAddress = Address::create([
            'lead_id'      => $leadWithAddress->id,
            'street'       => 'Main Street',
            'house_number' => '123',
            'city'         => 'Amsterdam',
            'postal_code'  => '1000AA',
            'country'      => 'Netherlands',
        ]);

        $personAddress = Address::create([
            'person_id'    => $personWithAddress->id,
            'street'       => 'Main Street',
            'house_number' => '123',
            'city'         => 'Amsterdam',
            'postal_code'  => '1000AA',
            'country'      => 'Netherlands',
        ]);

        // Attach the person
        $leadWithAddress->attachPersons([$personWithAddress->id]);

        // This should not throw an exception (complete match = 100% score)
        LeadStatusTransitionValidator::validateTransition($leadWithAddress, $this->wonStage->id);

        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /** @test */
    public function it_handles_phone_number_normalization_correctly()
    {
        // Create lead with phone number and complete matching data
        $leadWithPhone = Lead::create([
            'first_name'             => 'John',
            'last_name'              => 'Doe',
            'emails'                 => [['value' => 'john.doe@example.com', 'is_default' => true]],
            'phones'                 => [['value' => '+31612345678', 'is_default' => true]],
            'lead_pipeline_stage_id' => $this->otherStage->id,
            'lead_pipeline_id'       => $this->lead->lead_pipeline_id,
        ]);

        // Create person with normalized phone number and complete data
        $personWithPhone = Person::create([
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'emails'     => [['value' => 'john.doe@example.com', 'is_default' => true]],
            'phones'     => [['value' => '0612345678', 'is_default' => true]],
        ]);

        // Attach the person
        $leadWithPhone->attachPersons([$personWithPhone->id]);

        // This should not throw an exception (complete match = 100% score)
        LeadStatusTransitionValidator::validateTransition($leadWithPhone, $this->wonStage->id);

        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    /** @test */
    public function it_allows_transition_with_perfect_name_match_when_other_fields_are_empty()
    {
        // Create a lead with only name fields (no email/phone/address)
        $leadWithNamesOnly = Lead::create([
            'first_name'             => 'John',
            'last_name'              => 'Doe',
            'lastname_prefix'        => 'van',
            'married_name'           => 'Smith',
            'initials'               => 'J.D.',
            'date_of_birth'          => '1990-01-01',
            'lead_pipeline_stage_id' => $this->otherStage->id,
            'lead_pipeline_id'       => $this->lead->lead_pipeline_id,
        ]);

        // Create a person with matching name fields (no email/phone/address)
        $personWithNamesOnly = Person::create([
            'first_name'      => 'John',
            'last_name'       => 'Doe',
            'lastname_prefix' => 'van',
            'married_name'    => 'Smith',
            'initials'        => 'J.D.',
            'date_of_birth'   => '1990-01-01',
        ]);

        // Attach the person
        $leadWithNamesOnly->attachPersons([$personWithNamesOnly->id]);

        // This should not throw an exception (perfect name match = 100% score)
        LeadStatusTransitionValidator::validateTransition($leadWithNamesOnly, $this->wonStage->id);

        $this->assertTrue(true); // If we get here, no exception was thrown
    }
}
