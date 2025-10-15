<?php

use App\Enums\Departments;
use App\Models\Address;
use App\Models\Department;
use Database\Seeders\TestSeeder;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    test()->user = User::factory()->active()->create();
});

// Helper to get required pipeline/stage data
function createLeadSyncPipelineData(): array
{
    $pipeline = Pipeline::firstOrCreate([
        'name'        => 'Test Pipeline',
        'is_default'  => 1,
        'rotten_days' => 30,
    ]);

    $stage = Stage::firstOrCreate([
        'name'             => 'New',
        'code'             => 'new',
        'lead_pipeline_id' => $pipeline->id,
        'sort_order'       => 1,
    ]);

    return ['pipelineId' => $pipeline->id, 'stageId' => $stage->id];
}

test('redirects to sync page when lead has 1 person with match score < 100', function () {
    $data = createLeadSyncPipelineData();
    
    // Create a person with different data than the lead
    $person = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'emails'     => [['value' => 'john@example.com', 'label' => 'eigen']],
        'user_id'    => test()->user->id,
    ]);

    $department = Department::where('name', Departments::PRIVATESCAN->value)->firstOrFail();
    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id,
        'department_id'          => $department->id,
        'first_name'             => 'John',
        'last_name'              => 'Smith', // Different last name for partial match
        'emails'                 => [['value' => 'john@example.com', 'label' => 'eigen']],
    ]);

    // Attach the person to the lead
    $lead->attachPersons([$person->id]);

    $response = test()
        ->actingAs(test()->user, 'user')
        ->put(route('admin.leads.update', $lead->id), [
            'first_name' => 'John',
            'last_name'  => 'Smith',
            'emails'     => [['value' => 'john@example.com', 'label' => 'eigen']],
            'department_id' => $department->id,
        ]);

    // Debug: Check if validation failed
    if ($response->status() === 422) {
        dump('Validation failed with errors:', $response->json('errors'));
        dump('Response status:', $response->status());
        dump('Response content:', $response->getContent());
        dump('Request data:', [
            'first_name' => 'John',
            'last_name'  => 'Smith',
            'emails'     => [['value' => 'john@example.com', 'label' => 'eigen']],
            'department_id' => $department->id,
        ]);
    }

    // Should redirect to sync page
    $response->assertRedirect(route('admin.contacts.persons.edit_with_lead', [
        'personId' => $person->id,
        'leadId'   => $lead->id,
    ]));
});

test('does not redirect to sync page when lead has 0 persons', function () {
    $data = createLeadSyncPipelineData();
    
    $department = Department::where('name', Departments::PRIVATESCAN->value)->firstOrFail();
    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id,
        'department_id'          => $department->id,
        'first_name'             => 'John',
        'last_name'              => 'Doe',
        'emails'                 => [['value' => 'john@example.com', 'label' => 'eigen']],
    ]);

    $response = test()
        ->actingAs(test()->user, 'user')
        ->put(route('admin.leads.update', $lead->id), [
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'emails'     => [['value' => 'john@example.com', 'label' => 'eigen']],
            'department_id' => $department->id,
        ]);

    // Should redirect to lead view page
    $response->assertRedirect(route('admin.leads.view', $lead->id));
});

test('does not redirect to sync page when lead has 2+ persons', function () {
    $data = createLeadSyncPipelineData();
    
    // Create two persons
    $person1 = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'user_id'    => test()->user->id,
    ]);

    $person2 = Person::factory()->create([
        'first_name' => 'Jane',
        'last_name'  => 'Smith',
        'user_id'    => test()->user->id,
    ]);

    $department = Department::where('name', Departments::PRIVATESCAN->value)->firstOrFail();
    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id,
        'department_id'          => $department->id,
        'first_name'             => 'John',
        'last_name'              => 'Doe',
        'emails'                 => [['value' => 'john@example.com', 'label' => 'eigen']],
    ]);

    // Attach both persons to the lead
    $lead->attachPersons([$person1->id, $person2->id]);

    $response = test()
        ->actingAs(test()->user, 'user')
        ->put(route('admin.leads.update', $lead->id), [
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'emails'     => [['value' => 'john@example.com', 'label' => 'eigen']],
            'department_id' => $department->id,
        ]);

    // Should redirect to lead view page
    $response->assertRedirect(route('admin.leads.view', $lead->id));
});

test('does not redirect to sync page when match score is 100', function () {
    $data = createLeadSyncPipelineData();
    
    // Create a person with matching data
    $person = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'emails'     => [['value' => 'john@example.com', 'label' => 'eigen']],
        'phones'     => [['value' => '123456789', 'label' => 'eigen']],
        'date_of_birth' => '1985-05-15',
        'user_id'    => test()->user->id,
    ]);

    $department = Department::where('name', Departments::PRIVATESCAN->value)->firstOrFail();
    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id,
        'department_id'          => $department->id,
        'first_name'             => 'John',
        'last_name'              => 'Doe',
        'emails'                 => [['value' => 'john@example.com', 'label' => 'eigen']],
        'date_of_birth'          => '1985-05-15',
    ]);

    // Add matching addresses for perfect score
    Address::create([
        'lead_id'      => $lead->id,
        'street'       => 'Test Street',
        'house_number' => '123',
        'city'         => 'Test City',
        'postal_code'  => '1234AB',
        'country'      => 'Nederland',
    ]);

    Address::create([
        'person_id'    => $person->id,
        'street'       => 'Test Street',
        'house_number' => '123',
        'city'         => 'Test City',
        'postal_code'  => '1234AB',
        'country'      => 'Nederland',
    ]);

    // Attach the person to the lead
    $lead->attachPersons([$person->id]);

    $response = test()
        ->actingAs(test()->user, 'user')
        ->put(route('admin.leads.update', $lead->id), [
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'emails'     => [['value' => 'john@example.com', 'label' => 'eigen']],
            'department_id' => $department->id,
        ]);

    // Debug the response
    if ($response->status() !== 302) {
        dump('Response status: ' . $response->status());
        dump('Response content: ' . $response->getContent());
    }

    // Should redirect to lead view page (not sync page)
    $response->assertRedirect(route('admin.leads.view', $lead->id));
});

test('handles AJAX requests correctly for sync redirect', function () {
    $data = createLeadSyncPipelineData();
    
    // Create a person with different data than the lead
    $person = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'emails'     => [['value' => 'john@example.com', 'label' => 'eigen']],
        'user_id'    => test()->user->id,
    ]);

    $department = Department::where('name', Departments::PRIVATESCAN->value)->firstOrFail();
    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $data['pipelineId'],
        'lead_pipeline_stage_id' => $data['stageId'],
        'user_id'                => test()->user->id,
        'department_id'          => $department->id,
        'first_name'             => 'John',
        'last_name'              => 'Smith', // Different last name for partial match
        'emails'                 => [['value' => 'john@example.com', 'label' => 'eigen']],
    ]);

    // Attach the person to the lead
    $lead->attachPersons([$person->id]);

    $response = test()
        ->actingAs(test()->user, 'user')
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->putJson(route('admin.leads.update', $lead->id), [
            'first_name' => 'John',
            'last_name'  => 'Smith',
            'emails'     => [['value' => 'john@example.com', 'label' => 'eigen']],
            'department_id' => $department->id,
        ]);

    // Debug the response
    if ($response->status() !== 200) {
        dump('Response status: ' . $response->status());
        dump('Response content: ' . $response->getContent());
    }

    // Should return JSON with redirect to sync page
    $response->assertOk();
    $response->assertJson([
        'redirect' => route('admin.contacts.persons.edit_with_lead', [
            'personId' => $person->id,
            'leadId'   => $lead->id,
        ]),
    ]);
});