<?php

use App\Enums\ContactLabel;
use App\Enums\DuplicateEntityType;
use App\Services\DuplicateFalsePositiveService;
use Database\Seeders\TestSeeder;
use Webkul\Contact\Models\Person;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);
    $this->seed(TestSeeder::class);

    $role = Role::factory()->create([
        'permission_type' => 'all',
        'permissions'     => null,
    ]);

    $this->actingAs(User::factory()->create([
        'role_id'         => $role->id,
        'view_permission' => 'global',
        'status'          => 1,
    ]), 'user');

    $email = [['value' => 'undo.fp@example.com', 'label' => ContactLabel::Eigen->value]];

    $this->person = Person::factory()->create(['emails' => $email]);
    $this->other = Person::factory()->create(['emails' => $email]);

    app(DuplicateFalsePositiveService::class)->storeForEntities(
        DuplicateEntityType::PERSON,
        [$this->person->id, $this->other->id]
    );
});

test('person view lists persons marked as not a duplicate', function () {
    get(route('admin.contacts.persons.view', $this->person->id))
        ->assertOk()
        ->assertSee('Gemarkeerd als "geen duplicaat"', false)
        ->assertSee($this->other->name, false);
});

test('undoing a false positive makes the pair a duplicate again', function () {
    delete(route('admin.contacts.persons.duplicates.false_positive.destroy', $this->person->id), [
        'entity_id' => $this->other->id,
    ])->assertRedirect();

    expect(app(DuplicateFalsePositiveService::class)->shouldIgnore(
        DuplicateEntityType::PERSON,
        $this->person->id,
        $this->other->id
    ))->toBeFalse();

    expect($this->person->fresh()->has_duplicates)->toBeTruthy();

    get(route('admin.contacts.persons.view', $this->person->id))
        ->assertOk()
        ->assertDontSee('Gemarkeerd als "geen duplicaat"', false);
});

test('undoing requires a different existing person', function () {
    delete(route('admin.contacts.persons.duplicates.false_positive.destroy', $this->person->id), [
        'entity_id' => $this->person->id,
    ])->assertSessionHasErrors('entity_id');
});
