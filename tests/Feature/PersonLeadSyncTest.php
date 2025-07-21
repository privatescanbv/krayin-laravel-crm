<?php

use Database\Seeders\LeadChannelSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Installer\Database\Seeders\Lead\PipelineSeeder;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => PipelineSeeder::class]);

    $this->personRepository = app(PersonRepository::class);
    $this->leadRepository = app(LeadRepository::class);

    // Create a test user and authenticate
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'user');

    // Ensure we have pipeline and stage
    $this->pipelineId = 1;
    $this->stageId = 1;

    if (! DB::table('lead_pipelines')->where('id', 1)->first()) {
        throw new ModelNotFoundException('lead_pipelines not found');
    }
    if (! DB::table('lead_pipeline_stages')->where('id', 1)->first()) {
        throw new ModelNotFoundException('lead_pipelines not found');
    }
});

// Helper method to ensure authentication before HTTP requests
function authenticatedRequest($method, $uri, $data = []) {
    // Re-authenticate before each request to prevent 302 redirects
    test()->actingAs(test()->user, 'user');

    return match($method) {
        'GET' => test()->get($uri),
        'POST' => test()->post($uri, $data),
        'PUT' => test()->put($uri, $data),
        'PATCH' => test()->patch($uri, $data),
        'DELETE' => test()->delete($uri),
        default => throw new InvalidArgumentException("Unsupported HTTP method: $method")
    };
}

test('can access edit with lead page', function () {
    $person = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'emails'     => [['value' => 'john@example.com', 'label' => 'Work']],
    ]);

    $lead = Lead::factory()->create([
        'first_name'             => 'John',
        'last_name'              => 'Smith',
        'emails'                 => [['value' => 'john.smith@example.com', 'label' => 'Work']],
        'lead_pipeline_id'       => $this->pipelineId,
        'lead_pipeline_stage_id' => $this->stageId,
    ]);

    $response = $this->get(route('admin.contacts.persons.edit_with_lead', [
        'personId' => $person->id,
        'leadId'   => $lead->id,
    ]));

    $response->assertStatus(200);
    $response->assertViewIs('admin::contacts.persons.edit-with-lead');
    $response->assertViewHas('person', $person);
    $response->assertViewHas('lead', $lead);
});

test('shows field differences between person and lead', function () {
    $person = Person::factory()->create([
        'first_name'    => 'John',
        'last_name'     => 'Doe',
        'emails'        => [['value' => 'john@example.com', 'label' => 'Work']],
        'phones'        => [['value' => '123456789', 'label' => 'Mobile']],
        'date_of_birth' => '1990-01-01',
        'user_id' => $this->user->id,
    ]);

    $lead = Lead::factory()->create([
        'first_name'             => 'John',
        'last_name'              => 'Smith', // Different last name
        'emails'                 => [['value' => 'john.smith@example.com', 'label' => 'Work']], // Different email
        'phones'                 => [['value' => '123456789', 'label' => 'Mobile']], // Same phone
        'date_of_birth'          => '1985-05-15', // Different birth date
        'lead_pipeline_id'       => $this->pipelineId,
        'lead_pipeline_stage_id' => $this->stageId,
    ]);

    $response = $this->get(route('admin.contacts.persons.edit_with_lead', [
        'personId' => $person->id,
        'leadId'   => $lead->id,
    ]));

    $response->assertStatus(200);

    // Check that differences are shown
    $response->assertSee('Smith'); // Lead's different last name
    $response->assertSee('john.smith@example.com'); // Lead's different email
    $response->assertSee('1985-05-15'); // Lead's different birth date

    // Should not show phone as it's the same
    $response->assertDontSee('Telefoonnummers'); // Should not appear in differences table
});

test('can update person with lead data', function () {
    $person = Person::factory()->create([
        'first_name'    => 'John',
        'last_name'     => 'Doe',
        'emails'        => [['value' => 'john@example.com', 'label' => 'Work']],
        'date_of_birth' => '1990-01-01',
    ]);

    $lead = Lead::factory()->create([
        'first_name'             => 'John',
        'last_name'              => 'Smith',
        'emails'                 => [['value' => 'john.smith@example.com', 'label' => 'Work']],
        'date_of_birth'          => '1985-05-15',
        'lead_pipeline_id'       => $this->pipelineId,
        'lead_pipeline_stage_id' => $this->stageId,
    ]);

    $response = $this->withHeaders([
        'Accept'           => 'application/json',
        'Content-Type'     => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->postJson(route('admin.contacts.persons.update_with_lead', [
        'personId' => $person->id,
        'leadId'   => $lead->id,
    ]), [
        'person_updates' => [
            'last_name'     => '1', // Update last name
            'date_of_birth' => '1', // Update birth date
        ],
        'lead_updates' => [
            'last_name'     => 'Smith',
            'emails'        => 'john.smith@example.com',
            'date_of_birth' => '1985-05-15',
        ],
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'message'      => 'Person en lead succesvol bijgewerkt.',
        'redirect_url' => route('admin.contacts.persons.view', $person->id),
    ]);

    // Verify person was updated
    $person->refresh();
    expect($person->last_name)->toBe('Smith');
    expect($person->date_of_birth->format('Y-m-d'))->toBe('1985-05-15');

    // Email should not be updated as it wasn't checked
    expect($person->emails[0]['value'])->toBe('john@example.com');
});

test('can update lead data during sync', function () {
    $person = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
    ]);

    $lead = Lead::factory()->create([
        'first_name'             => 'John',
        'last_name'              => 'Smith',
        'lead_pipeline_id'       => $this->pipelineId,
        'lead_pipeline_stage_id' => $this->stageId,
    ]);

    $response = $this->withHeaders([
        'Accept'           => 'application/json',
        'Content-Type'     => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->postJson(route('admin.contacts.persons.update_with_lead', [
        'personId' => $person->id,
        'leadId'   => $lead->id,
    ]), [
        'person_updates' => [
            'last_name' => '1', // Update person with modified lead value
        ],
        'lead_updates' => [
            'last_name' => 'Johnson', // Modify lead value
        ],
    ]);

    $response->assertStatus(200);

    // Verify both were updated
    $person->refresh();
    $lead->refresh();

    expect($person->last_name)->toBe('Johnson');
    expect($lead->last_name)->toBe('Johnson');
});

test('handles array fields correctly during sync', function () {
    $person = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'emails'     => [['value' => 'john@example.com', 'label' => 'Work']],
        'phones'     => [['value' => '123456789', 'label' => 'Mobile']],
    ]);

    $lead = Lead::factory()->create([
        'first_name'             => 'John',
        'last_name'              => 'Doe',
        'emails'                 => [['value' => 'john.smith@example.com', 'label' => 'Work']],
        'phones'                 => [['value' => '987654321', 'label' => 'Mobile']],
        'lead_pipeline_id'       => $this->pipelineId,
        'lead_pipeline_stage_id' => $this->stageId,
    ]);

    $response = $this->withHeaders([
        'Accept'           => 'application/json',
        'Content-Type'     => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->postJson(route('admin.contacts.persons.update_with_lead', [
        'personId' => $person->id,
        'leadId'   => $lead->id,
    ]), [
        'person_updates' => [
            'emails' => '1',
            'phones' => '1',
        ],
        'lead_updates' => [
            'emails' => 'john.updated@example.com, john.second@example.com',
            'phones' => '111222333, 444555666',
        ],
    ]);

    $response->assertStatus(200);

    // Verify arrays were updated correctly
    $person->refresh();

    expect($person->emails)->toHaveCount(2);
    expect($person->emails[0]['value'])->toBe('john.updated@example.com');
    expect($person->emails[1]['value'])->toBe('john.second@example.com');

    expect($person->phones)->toHaveCount(2);
    expect($person->phones[0]['value'])->toBe('111222333');
    expect($person->phones[1]['value'])->toBe('444555666');
});

test('returns validation error for invalid data', function () {
    // Re-authenticate to prevent 302 redirect
    $this->actingAs($this->user, 'user');

    $person = Person::factory()->create([
        'user_id' => $this->user->id,
    ]);
    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $this->pipelineId,
        'lead_pipeline_stage_id' => $this->stageId,
        'user_id' => $this->user->id,
    ]);

    // Test with invalid person ID
    $response = $this->post(route('admin.contacts.persons.update_with_lead', [
        'personId' => 99999,
        'leadId'   => $lead->id,
    ]), []);

    $response->assertStatus(404);
});

test('shows no differences message when records are identical', function () {
    $person = Person::factory()->create([
        'first_name'    => 'John',
        'last_name'     => 'Doe',
        'emails'        => [['value' => 'john@example.com', 'label' => 'Work']],
        'date_of_birth' => '1990-01-01',
    ]);

    $lead = Lead::factory()->create([
        'first_name'             => 'John',
        'last_name'              => 'Doe',
        'emails'                 => [['value' => 'john@example.com', 'label' => 'Work']],
        'date_of_birth'          => '1990-01-01',
        'lead_pipeline_id'       => $this->pipelineId,
        'lead_pipeline_stage_id' => $this->stageId,
    ]);

    $response = $this->get(route('admin.contacts.persons.edit_with_lead', [
        'personId' => $person->id,
        'leadId'   => $lead->id,
    ]));

    $response->assertStatus(200);
    $response->assertSee('Geen verschillen gevonden');
});

test('handles edge case with malformed birth dates', function () {
    // Re-authenticate to prevent 302 redirect
    $this->actingAs($this->user, 'user');

    // Create person with potentially malformed date
    $person = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'user_id' => $this->user->id,
    ]);

    // Manually set a malformed date in the database
    DB::table('persons')->where('id', $person->id)->update(['date_of_birth' => '0000-00-00']);

    $lead = Lead::factory()->create([
        'first_name'             => 'John',
        'last_name'              => 'Doe',
        'date_of_birth'          => null,
        'lead_pipeline_id'       => $this->pipelineId,
        'lead_pipeline_stage_id' => $this->stageId,
        'user_id' => $this->user->id,
    ]);

    // Refresh person to get the malformed date
    $person = $person->fresh();

    $response = $this->get(route('admin.contacts.persons.edit_with_lead', [
        'personId' => $person->id,
        'leadId'   => $lead->id,
    ]));

    $response->assertStatus(200);

    // Should not show birth date differences when one is malformed and other is null
    $response->assertDontSee('Geboortedatum');
    $response->assertDontSee('0000-00-00');
    $response->assertDontSee('-0001-11-30');
});

test('handles date comparison with valid vs invalid dates', function () {
    // Re-authenticate to prevent 302 redirect
    $this->actingAs($this->user, 'user');

    $person = Person::factory()->create([
        'first_name'    => 'John',
        'last_name'     => 'Doe',
        'date_of_birth' => '1990-01-01', // Valid date
        'user_id' => $this->user->id,
    ]);

    $lead = Lead::factory()->create([
        'first_name'             => 'John',
        'last_name'              => 'Doe',
        'date_of_birth'          => null, // No date
        'lead_pipeline_id'       => $this->pipelineId,
        'lead_pipeline_stage_id' => $this->stageId,
        'user_id' => $this->user->id,
    ]);

    $response = $this->get(route('admin.contacts.persons.edit_with_lead', [
        'personId' => $person->id,
        'leadId'   => $lead->id,
    ]));

    $response->assertStatus(200);

    // Should show birth date difference when one has valid date and other is null
    $response->assertSee('Geboortedatum');
    $response->assertSee('1990-01-01');
});

test('validates required route parameters', function () {
    // Test with missing person ID
    $response = $this->get('/admin/contacts/persons/edit-with-lead//1');
    $response->assertStatus(404);

    // Test with missing lead ID
    $response = $this->get('/admin/contacts/persons/edit-with-lead/1/');
    $response->assertStatus(404);
});

test('handles empty form submission gracefully', function () {
    $person = Person::factory()->create();
    $lead = Lead::factory()->create([
        'lead_pipeline_id'       => $this->pipelineId,
        'lead_pipeline_stage_id' => $this->stageId,
    ]);

    $response = $this->withHeaders([
        'Accept'           => 'application/json',
        'Content-Type'     => 'application/json',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->postJson(route('admin.contacts.persons.update_with_lead', [
        'personId' => $person->id,
        'leadId'   => $lead->id,
    ]), []);

    $response->assertStatus(200);
    $response->assertJson([
        'message' => 'Person en lead succesvol bijgewerkt.',
    ]);
});

test('manual search returns match scores when lead_id provided', function () {
    $person = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'emails'     => [['value' => 'john@example.com', 'label' => 'Work']],
        'user_id' => $this->user->id,
    ]);

    $lead = Lead::factory()->create([
        'first_name'             => 'John',
        'last_name'              => 'Smith', // Different last name for partial match
        'emails'                 => [['value' => 'john@example.com', 'label' => 'Work']], // Same email
        'lead_pipeline_id'       => $this->pipelineId,
        'lead_pipeline_stage_id' => $this->stageId,
    ]);

    // Test manual search with lead_id parameter
    $response = $this->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/admin/contacts/persons/search?'.http_build_query([
        'query'   => 'John',
        'lead_id' => $lead->id,
    ]));

    $response->assertStatus(200);

    $data = $response->json('data');
    expect($data)->not->toBeEmpty();

    // Find our test person in the results
    $testPerson = collect($data)->firstWhere('id', $person->id);
    expect($testPerson)->not->toBeNull();
    expect($testPerson['match_score_percentage'])->toBeGreaterThan(0);
});

test('manual search without lead_id returns regular results', function () {
    $person = Person::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'user_id' => $this->user->id,
    ]);

    // Test manual search without lead_id parameter
    $response = $this->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/admin/contacts/persons/search?'.http_build_query([
        'query' => 'John',
    ]));

    $response->assertStatus(200);

    $data = $response->json('data');
    expect($data)->not->toBeEmpty();

    // Results should not have match scores
    $testPerson = collect($data)->firstWhere('id', $person->id);
    expect($testPerson)->not->toBeNull();
    expect($testPerson)->not->toHaveKey('match_score_percentage');
});
