<?php

namespace Tests\Unit;

use App\Models\SalesLead;
use App\Repositories\SalesLeadRepository;
use Database\Seeders\TestSeeder;
use Tests\TestCase;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\User\Models\User;

class ResolveEmailVariablesTest extends TestCase
{
    private LeadRepository $leadRepository;

    private SalesLeadRepository $salesLeadRepository;

    private Pipeline $pipeline;

    private Stage $stage;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TestSeeder::class);

        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'user');

        // Ensure we have a pipeline and stage
        $this->pipeline = Pipeline::first();
        $this->stage = Stage::first();
        if (! $this->pipeline || ! $this->stage) {
            throw new \Exception('Pipeline or Stage not found. Ensure TestSeeder provisions them.');
        }

        $this->leadRepository = app(LeadRepository::class);
        $this->salesLeadRepository = app(SalesLeadRepository::class);
    }

    /** @test */
    public function it_resolves_email_variables_for_lead_with_contact_person()
    {
        $person = Person::factory()->create([
            'first_name' => 'John',
            'last_name'  => 'Doe',
        ]);

        $lead = Lead::factory()->create([
            'contact_person_id'      => $person->id,
            'last_name'              => 'LeadLastName',
            'lead_pipeline_id'       => $this->pipeline->id,
            'lead_pipeline_stage_id' => $this->stage->id,
            'user_id'                => $this->user->id,
        ]);

        $variables = $this->leadRepository->resolveEmailVariablesById($lead->id);

        $this->assertIsArray($variables);
        $this->assertArrayHasKey('lastname', $variables);
        $this->assertEquals('Doe', $variables['lastname']);
    }

    /** @test */
    public function it_resolves_email_variables_for_lead_without_contact_person_but_with_linked_person()
    {
        $person = Person::factory()->create([
            'first_name' => 'Jane',
            'last_name'  => 'Smith',
        ]);

        $lead = Lead::factory()->create([
            'contact_person_id'      => null,
            'last_name'              => 'LeadLastName',
            'lead_pipeline_id'       => $this->pipeline->id,
            'lead_pipeline_stage_id' => $this->stage->id,
            'user_id'                => $this->user->id,
        ]);

        // Attach a person to the lead
        $lead->persons()->attach($person->id);

        // Refresh to ensure relations are loaded
        $lead->refresh();

        $variables = $this->leadRepository->resolveEmailVariablesById($lead->id);

        $this->assertIsArray($variables);
        $this->assertArrayHasKey('lastname', $variables);
        $this->assertEquals('Smith', $variables['lastname']);
    }

    /** @test */
    public function it_resolves_email_variables_for_lead_without_any_person_fallback_to_lead_last_name()
    {
        $lead = Lead::factory()->create([
            'contact_person_id'      => null,
            'last_name'              => 'LeadLastName',
            'lead_pipeline_id'       => $this->pipeline->id,
            'lead_pipeline_stage_id' => $this->stage->id,
            'user_id'                => $this->user->id,
        ]);

        $variables = $this->leadRepository->resolveEmailVariablesById($lead->id);

        $this->assertIsArray($variables);
        $this->assertArrayHasKey('lastname', $variables);
        $this->assertEquals('LeadLastName', $variables['lastname']);
    }

    /** @test */
    public function it_resolves_email_variables_for_lead_with_contact_person_preferred_over_linked_person()
    {
        $contactPerson = Person::factory()->create([
            'first_name' => 'Contact',
            'last_name'  => 'Person',
        ]);

        $linkedPerson = Person::factory()->create([
            'first_name' => 'Linked',
            'last_name'  => 'OtherPerson',
        ]);

        $lead = Lead::factory()->create([
            'contact_person_id'      => $contactPerson->id,
            'last_name'              => 'LeadLastName',
            'lead_pipeline_id'       => $this->pipeline->id,
            'lead_pipeline_stage_id' => $this->stage->id,
            'user_id'                => $this->user->id,
        ]);

        // Attach a different person to the lead
        $lead->persons()->attach($linkedPerson->id);

        // Refresh to ensure relations are loaded
        $lead->refresh();

        $variables = $this->leadRepository->resolveEmailVariablesById($lead->id);

        $this->assertIsArray($variables);
        $this->assertArrayHasKey('lastname', $variables);
        // Contact person should be preferred
        $this->assertEquals('Person', $variables['lastname']);
    }

    /** @test */
    public function it_resolves_email_variables_for_sales_lead_with_contact_person()
    {
        $person = Person::factory()->create([
            'first_name' => 'John',
            'last_name'  => 'Doe',
        ]);

        $salesLead = SalesLead::factory()->create([
            'contact_person_id' => $person->id,
        ]);

        $variables = $this->salesLeadRepository->resolveEmailVariablesById($salesLead->id);

        $this->assertIsArray($variables);
        $this->assertArrayHasKey('lastname', $variables);
        $this->assertEquals('Doe', $variables['lastname']);
    }

    /** @test */
    public function it_resolves_email_variables_for_sales_lead_without_contact_person_but_with_linked_person()
    {
        $person = Person::factory()->create([
            'first_name' => 'Jane',
            'last_name'  => 'Smith',
        ]);

        $salesLead = SalesLead::factory()->create([
            'contact_person_id' => null,
        ]);

        // Attach a person to the sales lead
        $salesLead->persons()->attach($person->id);

        // Refresh to ensure relations are loaded
        $salesLead->refresh();

        $variables = $this->salesLeadRepository->resolveEmailVariablesById($salesLead->id);

        $this->assertIsArray($variables);
        $this->assertArrayHasKey('lastname', $variables);
        $this->assertEquals('Smith', $variables['lastname']);
    }

    /** @test */
    public function it_resolves_email_variables_for_sales_lead_with_contact_person_preferred_over_linked_person()
    {
        $contactPerson = Person::factory()->create([
            'first_name' => 'Contact',
            'last_name'  => 'Person',
        ]);

        $linkedPerson = Person::factory()->create([
            'first_name' => 'Linked',
            'last_name'  => 'OtherPerson',
        ]);

        $salesLead = SalesLead::factory()->create([
            'contact_person_id' => $contactPerson->id,
        ]);

        // Attach a different person to the sales lead
        $salesLead->persons()->attach($linkedPerson->id);

        // Refresh to ensure relations are loaded
        $salesLead->refresh();

        $variables = $this->salesLeadRepository->resolveEmailVariablesById($salesLead->id);

        $this->assertIsArray($variables);
        $this->assertArrayHasKey('lastname', $variables);
        // Contact person should be preferred
        $this->assertEquals('Person', $variables['lastname']);
    }

    /** @test */
    public function it_throws_exception_when_lead_not_found()
    {
        $this->expectException(\TypeError::class);

        $this->leadRepository->resolveEmailVariablesById(99999);
    }

    /** @test */
    public function it_throws_exception_when_sales_lead_not_found()
    {
        $this->expectException(\TypeError::class);

        $this->salesLeadRepository->resolveEmailVariablesById(99999);
    }
}
