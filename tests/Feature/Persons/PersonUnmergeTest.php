<?php

use App\Enums\ContactLabel;
use App\Enums\DuplicateEntityType;
use App\Services\DuplicateFalsePositiveService;
use Database\Seeders\TestSeeder;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);
    $this->seed(TestSeeder::class);

    $role = Role::factory()->create([
        'permission_type' => 'all',
        'permissions'     => null,
    ]);

    $this->actingAs($this->user = User::factory()->create([
        'role_id'         => $role->id,
        'view_permission' => 'global',
        'status'          => 1,
    ]), 'user');

    // Shared email, like a couple: after undoing the merge they must no longer be flagged.
    $email = [['value' => 'kam.couple@example.com', 'label' => ContactLabel::Eigen->value]];

    $this->primary = Person::factory()->create(['first_name' => 'Jan', 'emails' => $email]);
    $this->merged = Person::factory()->create(['first_name' => 'Marie', 'last_name' => "O'Connell", 'emails' => $email]);

    app(PersonRepository::class)->mergePersons($this->primary->id, [$this->merged->id]);
});

test('merging via the duplicates screen flashes the result for the next page', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();

    $this->postJson(route('admin.contacts.persons.duplicates.merge', $primary->id), [
        'primary_person_id'    => $primary->id,
        'duplicate_person_ids' => [$duplicate->id],
    ])
        ->assertOk()
        ->assertSessionHas('success', __('messages.person.merge_success'));
});

test('person view links to the unmerge confirmation screen', function () {
    get(route('admin.contacts.persons.view', $this->primary->id))
        ->assertOk()
        ->assertSee('Samengevoegd met deze persoon (1)')
        ->assertSee(route('admin.contacts.persons.duplicates.unmerge.confirm', ['id' => $this->primary->id, 'entity_id' => $this->merged->id]), false)
        ->assertDontSee('confirm(', false);
});

test('confirmation screen explains the unmerge without restoring anything yet', function () {
    get(route('admin.contacts.persons.duplicates.unmerge.confirm', ['id' => $this->primary->id, 'entity_id' => $this->merged->id]))
        ->assertOk()
        ->assertSee($this->primary->name)
        ->assertSee($this->merged->name)
        ->assertSee('Wat je daarna met de hand doet')
        ->assertSee('Ja, samenvoegen ongedaan maken');

    expect(Person::find($this->merged->id))->toBeNull();
});

test('confirmation screen refuses a person that was not merged into this one', function () {
    $unrelated = Person::factory()->create();
    $unrelated->delete();

    get(route('admin.contacts.persons.duplicates.unmerge.confirm', ['id' => $this->primary->id, 'entity_id' => $unrelated->id]))
        ->assertStatus(422);
});

test('undoing a merge restores the person and marks the pair not a duplicate', function () {
    post(route('admin.contacts.persons.duplicates.unmerge', $this->primary->id), [
        'entity_id' => $this->merged->id,
    ])
        ->assertRedirect(route('admin.contacts.persons.view', $this->merged->id))
        ->assertSessionHas('success');

    expect(Person::find($this->merged->id))->not->toBeNull()
        ->and(app(DuplicateFalsePositiveService::class)->partnerIdsFor(DuplicateEntityType::PERSON, $this->primary->id)->all())->toBe([$this->merged->id])
        ->and($this->primary->fresh()->has_duplicates)->toBeFalsy()
        ->and($this->merged->fresh()->has_duplicates)->toBeFalsy();

    foreach ([$this->primary->id, $this->merged->id] as $personId) {
        $this->assertDatabaseHas('activities', [
            'person_id' => $personId,
            'type'      => 'system',
            'title'     => 'System: Samenvoegen ongedaan gemaakt',
            'user_id'   => $this->user->id,
        ]);
    }

    get(route('admin.contacts.persons.view', $this->primary->id))
        ->assertOk()
        ->assertDontSee('Samenvoegen ongedaan maken');
});

test('a person that was not merged into this one cannot be restored', function () {
    $unrelated = Person::factory()->create();
    $unrelated->delete();

    post(route('admin.contacts.persons.duplicates.unmerge', $this->primary->id), [
        'entity_id' => $unrelated->id,
    ])->assertStatus(422);

    expect(Person::find($unrelated->id))->toBeNull();
});
