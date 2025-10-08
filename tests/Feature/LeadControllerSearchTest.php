<?php

use App\Enums\ContactLabel;
use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    // Create and authenticate a back-office user (first/last name fields)
    $this->user = User::factory()->create(['first_name' => 'Admin', 'last_name' => 'Tester']);
    // Authenticate on the admin guard used by backend routes
    $this->actingAs($this->user, 'user');
    // voorkom auth-redirects in deze test
    $this->withoutMiddleware(Authenticate::class);
    // Ensure we have a pipeline and stage
    $this->pipeline = Pipeline::first();
    $this->stage = Stage::first();
    if (! $this->pipeline || ! $this->stage) {
        throw new Exception('Pipeline or Stage not found. Ensure TestSeeder provisions them.');
    }
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
});

test('lead search with name and whitelisted fields works and person.name works', function () {
    // Lead that should be found by title/name:Kuh → first/last/married_name
    $leadByName = Lead::factory()->create([
        'first_name'             => 'Kuh',
        'last_name'              => 'Finder',
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    // Lead found via assigned user's name like (use the authenticated user to avoid authorization filtering)
    $this->user->update(['first_name' => 'Kuh', 'last_name' => 'Sales']);
    $leadByUser = Lead::factory()->create([
        'first_name'             => 'Alice',
        'last_name'              => 'Smith',
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    // Lead found via related person name like
    $leadByPerson = Lead::factory()->create([
        'first_name'             => 'Bob',
        'last_name'              => 'Jones',
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    $person = Person::factory()->create([
        'first_name' => 'Kuh',
        'last_name'  => 'Related',
    ]);

    // Attach via pivot lead_persons
    DB::table('lead_persons')->insert([
        'lead_id'   => $leadByPerson->id,
        'person_id' => $person->id,
    ]);

    // name with explicit allowed fields
    $respName = $this->getJson(route('admin.leads.search', [
        'search'       => 'name:Kuh;',
        'searchFields' => 'first_name:like;last_name:like;married_name:like;',
    ]));
    $respName->assertOk();
    $idsName = collect($respName->json('data'))->pluck('id');
    expect($idsName)->toContain($leadByName->id);

    // user first/last name lookup finds lead assigned to the matching user
    $respUser = $this->getJson(route('admin.leads.search', [
        'search'       => 'user.first_name:Kuh;user.last_name:Kuh;',
        'searchFields' => 'user.first_name:like;user.last_name:like;',
        'searchJoin'   => 'or',
    ]));
    $respUser->assertOk();
    $idsUser = collect($respUser->json('data'))->pluck('id');
    logger()->info('Search by user.name response', $respUser->json(), ['user' => $this->user->toArray(),'idsUser'=>$idsUser->toArray()]);
    expect($idsUser)->toContain($leadByUser->id);
});

test('lead search returns 400 for invalid search field', function () {
    $response = $this->getJson(route('admin.leads.search', [
        'search'       => 'foo:Bar;',
        'searchFields' => 'foo:like;',
    ]));

    $response->assertStatus(400)
        ->assertJsonStructure(['message', 'field']);
});

test('lead search can find by email and phone', function () {
    // Create a lead with emails/phones arrays
    $lead = Lead::factory()->create([
        'first_name' => 'Eva',
        'last_name'  => 'Kuijer',
        'emails'     => [
            ['value' => 'eva.kuijer@example.com', 'label' => ContactLabel::Eigen->value, 'is_default' => true],
        ],
        'phones' => [
            ['value' => '0612345678', 'label' => ContactLabel::Relatie->value, 'is_default' => true],
        ],
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    // Search by convenience token email: (maps to emails column)
    $respEmail = $this->getJson(route('admin.leads.search', [
        'search'       => 'email:eva.kuijer@example.com;',
        'searchFields' => 'emails:like;',
    ]));
    $respEmail->assertOk();
    $idsEmail = collect($respEmail->json('data'))->pluck('id');
    expect($idsEmail)->toContain($lead->id);

    // Search by convenience token phone: (maps to phones column)
    $respPhone = $this->getJson(route('admin.leads.search', [
        'search'       => 'phone:0612345678;',
        'searchFields' => 'phones:like;',
    ]));
    $respPhone->assertOk();
    $idsPhone = collect($respPhone->json('data'))->pluck('id');
    expect($idsPhone)->toContain($lead->id);
});
