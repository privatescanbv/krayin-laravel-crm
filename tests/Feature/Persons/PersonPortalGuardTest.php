<?php

use App\Exceptions\CannotMergePersonWithPortalException;
use App\Services\Keycloak\KeycloakService;
use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    Person::unsetEventDispatcher();

    $this->actingAs(getDefaultAdmin(), 'user');
    $this->withoutMiddleware(Authenticate::class);
    $this->personRepository = app(PersonRepository::class);
});

test('http merge is rejected when the duplicate has a portal account', function () {
    $primary = Person::factory()->create(['first_name' => 'Jan', 'last_name' => 'Jansen']);
    $duplicate = Person::factory()->create([
        'first_name'       => 'Jan',
        'last_name'        => 'Jansen',
        'keycloak_user_id' => 'kc-duplicate',
    ]);

    $this->postJson(route('admin.contacts.persons.duplicates.merge', $primary->id), [
        'primary_person_id'    => $primary->id,
        'duplicate_person_ids' => [$duplicate->id],
        'field_mappings'       => [],
    ])->assertUnprocessable()
        ->assertJsonPath('success', false);

    expect(Person::find($duplicate->id))->not->toBeNull()
        ->and($duplicate->fresh()->keycloak_user_id)->toBe('kc-duplicate');
});

test('http merge is rejected when both persons have a portal account', function () {
    $primary = Person::factory()->create([
        'first_name'       => 'Jan',
        'last_name'        => 'Jansen',
        'keycloak_user_id' => 'kc-primary',
    ]);
    $duplicate = Person::factory()->create([
        'first_name'       => 'Jan',
        'last_name'        => 'Jansen',
        'keycloak_user_id' => 'kc-duplicate',
    ]);

    $this->postJson(route('admin.contacts.persons.duplicates.merge', $primary->id), [
        'primary_person_id'    => $primary->id,
        'duplicate_person_ids' => [$duplicate->id],
    ])->assertUnprocessable();

    expect(Person::find($primary->id)->keycloak_user_id)->toBe('kc-primary')
        ->and(Person::find($duplicate->id)->keycloak_user_id)->toBe('kc-duplicate');
});

test('http merge succeeds when only the primary has a portal account', function () {
    $primary = Person::factory()->create([
        'first_name'       => 'Jan',
        'last_name'        => 'Jansen',
        'keycloak_user_id' => 'kc-primary',
    ]);
    $duplicate = Person::factory()->create([
        'first_name' => 'Jan',
        'last_name'  => 'Jansen',
    ]);

    $this->postJson(route('admin.contacts.persons.duplicates.merge', $primary->id), [
        'primary_person_id'    => $primary->id,
        'duplicate_person_ids' => [$duplicate->id],
        'field_mappings'       => [],
    ])->assertOk()
        ->assertJsonPath('success', true);

    expect(Person::find($primary->id)->keycloak_user_id)->toBe('kc-primary')
        ->and(Person::withTrashed()->find($duplicate->id)->trashed())->toBeTrue();
});

test('a person with a portal account cannot be deleted', function () {
    $person = Person::factory()->create(['keycloak_user_id' => 'kc-user']);

    $this->deleteJson(route('admin.contacts.persons.delete', $person->id))
        ->assertUnprocessable()
        ->assertJsonPath('message', __('messages.person.delete_blocked_portal'));

    expect(Person::find($person->id))->not->toBeNull()
        ->and($person->fresh()->keycloak_user_id)->toBe('kc-user');
});

test('mass delete is rejected entirely when any selected person has a portal account', function () {
    $portal = Person::factory()->create(['keycloak_user_id' => 'kc-user']);
    $plain = Person::factory()->create();

    $this->postJson(route('admin.contacts.persons.mass_delete'), [
        'indices' => [$portal->id, $plain->id],
    ])->assertUnprocessable();

    expect(Person::find($portal->id))->not->toBeNull()
        ->and(Person::find($plain->id))->not->toBeNull();
});

test('a person without a portal account can still be deleted', function () {
    $person = Person::factory()->create(['keycloak_user_id' => null]);

    $this->deleteJson(route('admin.contacts.persons.delete', $person->id))
        ->assertOk();

    expect(Person::find($person->id))->toBeNull();
});

test('edit person locks the keycloak email and allows extra addresses', function () {
    $keycloak = Mockery::mock(KeycloakService::class);
    $keycloak->shouldReceive('getUserById')
        ->andReturn(['email' => 'jan@example.com']);
    $this->app->instance(KeycloakService::class, $keycloak);

    $person = Person::factory()->create([
        'first_name'       => 'Jan',
        'last_name'        => 'Jansen',
        'emails'           => [
            ['value' => 'jan@example.com', 'label' => 'eigen', 'is_default' => true],
        ],
        'keycloak_user_id' => 'kc-user',
        'is_active'        => true,
    ]);

    $this->get(route('admin.contacts.persons.edit', $person->id))
        ->assertOk()
        ->assertSee('jan@example.com hoort bij het patiëntportaal', false);

    $this->put(route('admin.contacts.persons.update', $person->id), [
        'first_name'  => 'Jan',
        'last_name'   => 'Jansen',
        'emails'      => [
            ['value' => 'other@example.com', 'label' => 'eigen', 'is_default' => true],
        ],
        'phones'      => [],
        'is_active'   => 1,
        'entity_type' => 'persons',
    ])->assertSessionHasErrors('emails');

    $this->put(route('admin.contacts.persons.update', $person->id), [
        'first_name'  => 'Jan',
        'last_name'   => 'Jansen',
        'emails'      => [
            ['value' => 'jan@example.com', 'label' => 'eigen', 'is_default' => true],
            ['value' => 'extra@example.com', 'label' => 'anders', 'is_default' => false],
        ],
        'phones'      => [],
        'is_active'   => 1,
        'entity_type' => 'persons',
    ])->assertSessionHasNoErrors();

    $person->refresh();
    $values = collect($person->emails)->pluck('value')->all();

    expect($values)->toContain('jan@example.com')
        ->and($values)->toContain('extra@example.com');
});

test('edit person does not restore a missing keycloak email', function () {
    $keycloak = Mockery::mock(KeycloakService::class);
    $keycloak->shouldReceive('getUserById')
        ->andReturn(['email' => 'portal@example.com']);
    $this->app->instance(KeycloakService::class, $keycloak);

    $person = Person::factory()->create([
        'first_name'       => 'Jan',
        'last_name'        => 'Jansen',
        'emails'           => [
            ['value' => 'crm@example.com', 'label' => 'eigen', 'is_default' => true],
        ],
        'keycloak_user_id' => 'kc-user',
        'is_active'        => true,
    ]);

    $this->get(route('admin.contacts.persons.edit', $person->id))
        ->assertOk()
        ->assertSee('portal@example.com hoort bij het patiëntportaal', false)
        ->assertDontSee('niet automatisch toegevoegd', false);

    $this->put(route('admin.contacts.persons.update', $person->id), [
        'first_name'  => 'Jan',
        'last_name'   => 'Jansen',
        'emails'      => [
            ['value' => 'crm@example.com', 'label' => 'eigen', 'is_default' => true],
        ],
        'phones'      => [],
        'is_active'   => 1,
        'entity_type' => 'persons',
    ])->assertSessionHasNoErrors();

    expect($person->fresh()->emails[0]['value'])->toBe('crm@example.com')
        ->and(collect($person->fresh()->emails)->pluck('value'))->not->toContain('portal@example.com');
});

test('repository merge does not adopt a portal account from a duplicate', function () {
    expect(fn () => $this->personRepository->mergePersons(
        Person::factory()->create(['keycloak_user_id' => null])->id,
        [Person::factory()->create(['keycloak_user_id' => 'kc-dup'])->id],
    ))->toThrow(CannotMergePersonWithPortalException::class);
});
