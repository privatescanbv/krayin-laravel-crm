<?php

use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Webkul\Contact\Models\Person;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    $this->actingAs(User::factory()->create(), 'user');
    $this->withoutMiddleware(Authenticate::class);
});

function careContactsPayload(array $overrides = []): array
{
    return array_merge([
        'first_name'                     => 'Jan',
        'last_name'                      => 'Jansen',
        'emails'                         => [['value' => 'jan@example.com', 'label' => 'eigen', 'is_default' => true]],
        'phones'                         => [],
        'entity_type'                    => 'persons',
        'general_practitioner_name'      => 'Dr. De Vries',
        'general_practitioner_practice'  => 'Praktijk Centrum',
        'general_practitioner_phone'     => '0201234567',
        'emergency_contact_name'         => 'Marie Jansen',
        'emergency_contact_relationship' => 'Partner',
        'emergency_contact_phone'        => '0612345678',
    ], $overrides);
}

test('person can be created with huisarts and noodcontact', function () {
    $this->post(route('admin.contacts.persons.store'), careContactsPayload())
        ->assertSessionHasNoErrors();

    $person = Person::where('last_name', 'Jansen')->firstOrFail();

    expect($person->general_practitioner_name)->toBe('Dr. De Vries')
        ->and($person->general_practitioner_practice)->toBe('Praktijk Centrum')
        ->and($person->emergency_contact_relationship)->toBe('Partner')
        ->and($person->emergency_contact_phone)->toBe('+31612345678');
});

test('huisarts and noodcontact are optional', function () {
    $payload = array_filter(careContactsPayload(), fn ($key) => ! str_starts_with($key, 'general_') && ! str_starts_with($key, 'emergency_'), ARRAY_FILTER_USE_KEY);

    $this->post(route('admin.contacts.persons.store'), $payload)
        ->assertSessionHasNoErrors();

    expect(Person::where('last_name', 'Jansen')->firstOrFail()->general_practitioner_name)->toBeNull();
});

test('person update changes and clears huisarts and noodcontact', function () {
    $person = Person::factory()->create(['general_practitioner_name' => 'Oud', 'emergency_contact_name' => 'Oud']);

    $this->put(route('admin.contacts.persons.update', $person->id), careContactsPayload([
        'general_practitioner_name' => 'Dr. Nieuw',
        'emergency_contact_name'    => null,
    ]))->assertSessionHasNoErrors();

    $person->refresh();

    expect($person->general_practitioner_name)->toBe('Dr. Nieuw')
        ->and($person->emergency_contact_name)->toBeNull();
});

test('invalid noodcontact phone is rejected', function () {
    $this->post(route('admin.contacts.persons.store'), careContactsPayload(['emergency_contact_phone' => 'geen nummer']))
        ->assertSessionHasErrors('emergency_contact_phone');
});

test('edit page shows huisarts and noodcontact fields', function () {
    $person = Person::factory()->create(['general_practitioner_name' => 'Dr. Zichtbaar']);

    $this->get(route('admin.contacts.persons.edit', $person->id))
        ->assertOk()
        ->assertSee('Noodcontact')
        ->assertSee('Dr. Zichtbaar');
});

test('create page shows huisarts and noodcontact fields', function () {
    $this->get(route('admin.contacts.persons.create'))
        ->assertOk()
        ->assertSee('general_practitioner_name', false)
        ->assertSee('emergency_contact_phone', false);
});
