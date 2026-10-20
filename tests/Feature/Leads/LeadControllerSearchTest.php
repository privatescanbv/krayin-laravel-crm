<?php

use App\Enums\ContactLabel;
use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
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

test('lead search returns 400 for invalid search field', function () {
    $response = $this->getJson(route('admin.leads.search', [
        'search'       => 'foo:Bar;',
        'searchFields' => 'foo:like;',
    ]));

    $response->assertStatus(400)
        ->assertJsonStructure(['message', 'field']);
});

test('lead search returns stage and created_at so the entity-link picker can distinguish results', function () {
    $lead = Lead::factory()->create([
        'first_name'             => 'Stage',
        'last_name'              => 'Info',
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    $response = $this->getJson(route('admin.leads.search', [
        'search'       => 'first_name:Stage;',
        'searchFields' => 'first_name:like;',
    ]));

    $response->assertOk();

    $result = collect($response->json('data'))->firstWhere('id', $lead->id);

    expect($result)->not->toBeNull();
    expect($result['created_at'])->not->toBeNull();
    expect($result['stage']['name'])->toBe($this->stage->name);
    expect($result['stage'])->toHaveKeys(['is_won', 'is_lost']);
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

test('lead search ignores empty phone token and does not add phones like %%', function () {
    // Create target lead
    $leadTarget = Lead::factory()->create([
        'first_name' => 'Pieter',
        'last_name'  => 'Post',
        'emails'     => [
            ['value' => 'pieter.post@example.com', 'label' => ContactLabel::Eigen->value, 'is_default' => true],
        ],
        // no phone term in the query; phones present or absent should not matter
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    // Create noise lead that should NOT match
    $leadNoise = Lead::factory()->create([
        'first_name' => 'Noise',
        'last_name'  => 'Lead',
        'emails'     => [
            ['value' => 'noise.lead@example.com', 'label' => ContactLabel::Relatie->value, 'is_default' => true],
        ],
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    // Simulate client passing searchFields including phones:like; but WITHOUT a phone: token.
    // The controller should strip phones:* from searchFields to avoid `phones like %%`.
    $resp = $this->getJson(route('admin.leads.search', [
        'search'       => 'email:pieter.post@example.com;',
        'searchFields' => 'emails:like;phones:like;',
        'searchJoin'   => 'or',
    ]));
    $resp->assertOk();

    $ids = collect($resp->json('data'))->pluck('id')->toArray();
    expect($ids)->toContain($leadTarget->id)
        ->and($ids)->not->toContain($leadNoise->id);
});

test('lead search finds by email when only email token is provided', function () {
    // Create target lead with email
    $leadTarget = Lead::factory()->create([
        'first_name' => 'Test',
        'last_name'  => 'User',
        'emails'     => [
            ['value' => 'test.user@example.com', 'label' => ContactLabel::Eigen->value, 'is_default' => true],
        ],
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    // Create noise lead with different email but same name
    $leadNoise = Lead::factory()->create([
        'first_name' => 'Test',
        'last_name'  => 'User',
        'emails'     => [
            ['value' => 'different.email@example.com', 'label' => ContactLabel::Eigen->value, 'is_default' => true],
        ],
        'lead_pipeline_id'       => $this->pipeline->id,
        'lead_pipeline_stage_id' => $this->stage->id,
        'user_id'                => $this->user->id,
    ]);

    // Search with only email token (simulating mega search email detection)
    $resp = $this->getJson(route('admin.leads.search', [
        'search'       => 'email:test.user@example.com;',
        'searchFields' => 'emails:like;',
        'searchJoin'   => 'or',
    ]));
    $resp->assertOk();

    $ids = collect($resp->json('data'))->pluck('id')->toArray();
    expect($ids)->toContain($leadTarget->id)
        ->and($ids)->not->toContain($leadNoise->id);
});

test('mega search finds lead by multi-word name including prefix', function (string $term) {
    $theo = Lead::factory()->create(['first_name' => 'Theo', 'lastname_prefix' => 'de', 'last_name' => 'Man', 'user_id' => $this->user->id, 'lead_pipeline_id' => $this->pipeline->id, 'lead_pipeline_stage_id' => $this->stage->id]);
    $otherTheo = Lead::factory()->create(['first_name' => 'Theo', 'lastname_prefix' => null, 'last_name' => 'Jansen', 'user_id' => $this->user->id, 'lead_pipeline_id' => $this->pipeline->id, 'lead_pipeline_stage_id' => $this->stage->id]);

    $response = $this->getJson(route('admin.leads.search', [
        'search'       => "name:{$term};email:{$term};phone:{$term};",
        'searchFields' => 'first_name:like;last_name:like;married_name:like;emails:like;phones:like;',
        'limit'        => 15,
    ]));

    $ids = collect($response->assertOk()->json('data'))->pluck('id')->all();
    expect($ids)->toContain($theo->id)->not->toContain($otherTheo->id);
})->with(['theo de man', 'de Man, Theo']);

test('lead search respects individual view permission for name matches', function () {
    $this->user->update(['view_permission' => 'individual']);
    $otherUser = User::factory()->create();
    $attrs = ['first_name' => 'Theo', 'last_name' => 'Man', 'lead_pipeline_id' => $this->pipeline->id, 'lead_pipeline_stage_id' => $this->stage->id];

    $own = Lead::factory()->create([...$attrs, 'user_id' => $this->user->id]);
    $foreign = Lead::factory()->create([...$attrs, 'user_id' => $otherUser->id]);

    $ids = collect($this->getJson(route('admin.leads.search', [
        'search'       => 'name:theo;email:theo;phone:theo;',
        'searchFields' => 'first_name:like;last_name:like;married_name:like;emails:like;phones:like;',
    ]))->assertOk()->json('data'))->pluck('id')->all();

    expect($ids)->toContain($own->id)->not->toContain($foreign->id);
});
