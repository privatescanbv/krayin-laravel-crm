<?php

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Models\ActivityAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Activity\Models\Activity;
use Webkul\User\Models\Group;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

function createReadonlyActivityTestActivity(array $attributes = []): Activity
{
    $group = Group::create([
        'name'        => 'Readonly activity test group',
        'description' => 'Readonly activity test group',
    ]);

    return Activity::create(array_merge([
        'title'       => 'Readonly activity test',
        'type'        => ActivityType::TASK->value,
        'schedule_to' => now()->addDay(),
        'group_id'    => $group->id,
        'is_done'     => false,
        'status'      => ActivityStatus::ACTIVE,
    ], $attributes));
}

test('actions can be added to open activities', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'user');

    $activity = createReadonlyActivityTestActivity(['user_id' => $user->id]);

    $response = $this->postJson(route('admin.activities.actions.store', $activity->id), [
        'type' => 'notitie',
        'body' => 'Nieuwe actie op open activiteit',
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Notitie toegevoegd');

    $this->assertDatabaseHas('activity_actions', [
        'activity_id' => $activity->id,
        'type'        => 'notitie',
        'body'        => 'Nieuwe actie op open activiteit',
    ]);
});

test('actions cannot be added to completed activities', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'user');

    $activity = createReadonlyActivityTestActivity([
        'user_id' => $user->id,
        'is_done' => true,
        'status'  => ActivityStatus::DONE,
    ]);

    $response = $this->postJson(route('admin.activities.actions.store', $activity->id), [
        'type' => 'notitie',
        'body' => 'Mag niet worden toegevoegd',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Deze activiteit is afgerond. Heropen de activiteit om acties te wijzigen.');

    $this->assertDatabaseMissing('activity_actions', [
        'activity_id' => $activity->id,
        'body'        => 'Mag niet worden toegevoegd',
    ]);
});

test('completed activity edit page is read only for actions and save controls', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'user');

    $activity = createReadonlyActivityTestActivity([
        'user_id' => $user->id,
        'is_done' => true,
        'status'  => ActivityStatus::DONE,
    ]);

    ActivityAction::create([
        'activity_id' => $activity->id,
        'type'        => 'notitie',
        'body'        => 'Bestaande historie',
        'created_by'  => $user->id,
    ]);

    $this->get(route('admin.activities.edit', $activity->id))
        ->assertOk()
        ->assertSee('Deze activiteit is afgerond en daarom alleen-lezen')
        ->assertSee('Heropen de activiteit om acties toe te voegen')
        ->assertDontSee('data-activity-save-button', false)
        ->assertDontSee('Notitie toevoegen')
        ->assertDontSee('Belstatus toevoegen')
        ->assertDontSee('title="Verwijderen"', false);
});

test('completed activities cannot be updated until reopened', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'user');

    $activity = createReadonlyActivityTestActivity([
        'user_id' => $user->id,
        'is_done' => true,
        'status'  => ActivityStatus::DONE,
    ]);

    $response = $this->putJson(route('admin.activities.update', $activity->id), [
        'title'       => 'Gewijzigde titel',
        'type'        => ActivityType::TASK->value,
        'schedule_to' => now()->addDays(2)->format('Y-m-d H:i:s'),
        'group_id'    => $activity->group_id,
        'user_id'     => $user->id,
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Deze activiteit is afgerond. Heropen de activiteit om deze te wijzigen.');

    expect($activity->refresh()->title)->toBe('Readonly activity test');
});

test('reopened activities accept actions again', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'user');

    $activity = createReadonlyActivityTestActivity([
        'user_id' => $user->id,
        'is_done' => true,
        'status'  => ActivityStatus::DONE,
    ]);

    $this->post(route('admin.activities.reopen', $activity->id))
        ->assertRedirect(route('admin.activities.edit', $activity->id));

    expect($activity->refresh()->is_done)->toBeFalse();

    $this->postJson(route('admin.activities.actions.store', $activity->id), [
        'type' => 'notitie',
        'body' => 'Actie na heropenen',
    ])->assertOk();

    $this->assertDatabaseHas('activity_actions', [
        'activity_id' => $activity->id,
        'body'        => 'Actie na heropenen',
    ]);
});
