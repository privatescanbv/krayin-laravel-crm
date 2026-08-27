<?php

use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Models\Person;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    Person::unsetEventDispatcher();

    $this->actingAs(getDefaultAdmin(), 'user');
    $this->withoutMiddleware(Authenticate::class);
});

test('a duplicate with a portal account is still selectable (not hard-disabled) in the duplicates view', function () {
    $person = Person::factory()->create([
        'keycloak_user_id' => 'kc-primary',
    ]);

    Person::factory()->create([
        'first_name'       => $person->first_name,
        'last_name'        => $person->last_name,
        'keycloak_user_id' => 'kc-duplicate',
    ]);

    $this->get(route('admin.contacts.persons.duplicates.index', $person->id))
        ->assertOk()
        ->assertDontSee(':disabled="duplicate.has_portal_account"', false);
});

test('two persons that both have a portal account can be marked as not a duplicate', function () {
    $person = Person::factory()->create([
        'keycloak_user_id' => 'kc-primary',
    ]);

    $other = Person::factory()->create([
        'first_name'       => $person->first_name,
        'last_name'        => $person->last_name,
        'keycloak_user_id' => 'kc-duplicate',
    ]);

    $this->postJson(route('admin.contacts.persons.duplicates.false_positive', $person->id), [
        'entity_ids' => [$person->id, $other->id],
    ])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(
        DB::table('duplicates_false_positives')
            ->where(function ($query) use ($person, $other) {
                $query->where('entity_id_1', $person->id)->where('entity_id_2', $other->id);
            })
            ->orWhere(function ($query) use ($person, $other) {
                $query->where('entity_id_1', $other->id)->where('entity_id_2', $person->id);
            })
            ->exists()
    )->toBeTrue();
});
