<?php

namespace Tests\Feature;

use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

test('crm user posts a team chat message on a sale without creating patient messages', function () {
    $this->seed(TestSeeder::class);
    $user = User::factory()->create();
    $this->actingAs($user, 'user');

    $pipeline = Pipeline::first() ?? Pipeline::factory()->create();
    $stage = Stage::first() ?? Stage::factory()->create(['lead_pipeline_id' => $pipeline->id]);
    $lead = Lead::factory()->create(['lead_pipeline_id' => $pipeline->id, 'lead_pipeline_stage_id' => $stage->id, 'user_id' => $user->id]);
    $sale = SalesLead::create(['name' => 'WL', 'description' => 'd', 'pipeline_stage_id' => $stage->id, 'lead_id' => $lead->id, 'user_id' => $user->id]);

    $this->post(route('admin.sales-leads.team-chat.store', $sale->id), ['text' => ' Hoi dokter '])->assertRedirect();
    $this->post(route('admin.sales-leads.team-chat.store', $sale->id), ['text' => ''])->assertSessionHasErrors('text');

    $message = Activity::where('type', 'team_chat')->sole();
    expect($message)->sales_lead_id->toBe($sale->id)->created_by->toBe($user->id)->person_id->toBeNull()
        ->and($message->comment)->toContain('Hoi dokter')
        ->and(\DB::table('patient_messages')->count())->toBe(0);

    $this->get(route('admin.sales-leads.view', $sale->id))->assertOk()->assertSee('Teamchat');

    $this->getJson(route('admin.sales-leads.activities.index', $sale->id))->assertOk()->assertJsonCount(0, 'data');
});

function teamChatSale(User $user): SalesLead
{
    $pipeline = Pipeline::first() ?? Pipeline::factory()->create();
    $stage = Stage::first() ?? Stage::factory()->create(['lead_pipeline_id' => $pipeline->id]);
    $lead = Lead::factory()->create(['lead_pipeline_id' => $pipeline->id, 'lead_pipeline_stage_id' => $stage->id, 'user_id' => $user->id]);

    return SalesLead::create(['name' => 'WL', 'description' => 'd', 'pipeline_stage_id' => $stage->id, 'lead_id' => $lead->id, 'user_id' => $user->id]);
}

test('user without sales view permission cannot post a team chat message', function () {
    $this->seed(TestSeeder::class);
    $role = Role::factory()->create(['permission_type' => 'custom', 'permissions' => ['activities.view']]);
    $user = makeUser(['role_id' => $role->id]);
    $this->actingAs($user, 'user');
    $sale = teamChatSale($user);

    $this->post(route('admin.sales-leads.team-chat.store', $sale->id), ['text' => 'Hoi'])->assertStatus(401);

    expect(Activity::where('type', 'team_chat')->count())->toBe(0);
});

test('team chat messages never show up in the activity timeline of a person', function () {
    $this->seed(TestSeeder::class);
    $user = User::factory()->create();
    $this->actingAs($user, 'user');
    $sale = teamChatSale($user);
    $person = Person::factory()->create();
    $sale->persons()->attach($person->id);

    $this->post(route('admin.sales-leads.team-chat.store', $sale->id), ['text' => 'Intern bericht'])->assertRedirect();

    foreach (['', '?tab=system'] as $query) {
        $response = $this->getJson(route('admin.contacts.persons.activities.index', $person->id).$query)->assertOk();
        expect($response->getContent())->not->toContain('Intern bericht');
    }
});
