<?php

namespace Tests\Feature;

use Database\Seeders\TestSeeder;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\User\Models\User;

/**
 * Regression / TDD anchor for the production case of persons 62844 & 81248
 * ("Ritske Clewits"): two CRM person records that share the SAME phone number
 * but have DIFFERENT email addresses were not flagged as duplicates. One record
 * also carries a second, unrelated phone number.
 *
 * A shared phone number alone must be enough to detect the duplicate.
 */
beforeEach(function () {
    $this->seed(TestSeeder::class);
    Person::unsetEventDispatcher();
    $this->personRepository = app(PersonRepository::class);
});

test('persons with the same phone but different email are detected as duplicates', function () {
    $sharedPhone = '+31653231860';

    $a = Person::factory()->create([
        'first_name' => 'Ritske',
        'last_name'  => 'Clewits',
        'emails'     => [['value' => 'ritske.a@example.com', 'label' => 'eigen', 'is_default' => true]],
        'phones'     => [
            ['value' => $sharedPhone, 'label' => 'mobiel', 'is_default' => true],
            ['value' => '+31206441910', 'label' => 'werk'],
        ],
    ]);

    $b = Person::factory()->create([
        'first_name' => 'Ritske',
        'last_name'  => 'Clewits',
        'emails'     => [['value' => 'ritske.b@example.com', 'label' => 'eigen', 'is_default' => true]],
        'phones'     => [
            ['value' => $sharedPhone, 'label' => 'mobiel', 'is_default' => true],
        ],
    ]);

    $duplicates = $this->personRepository->findPotentialDuplicates($a);

    expect($duplicates->pluck('id'))->toContain($b->id);
});

test('a shared phone alone flags duplicates even when names and emails differ', function () {
    $sharedPhone = '+31653231860';

    $a = Person::factory()->create([
        'first_name' => 'Ritske',
        'last_name'  => 'Clewits',
        'emails'     => [['value' => 'a@example.com', 'label' => 'eigen', 'is_default' => true]],
        'phones'     => [['value' => $sharedPhone, 'label' => 'mobiel', 'is_default' => true]],
    ]);

    $b = Person::factory()->create([
        'first_name' => 'Totally',
        'last_name'  => 'Different',
        'emails'     => [['value' => 'b@example.com', 'label' => 'eigen', 'is_default' => true]],
        'phones'     => [['value' => $sharedPhone, 'label' => 'werk']],
    ]);

    $duplicates = $this->personRepository->findPotentialDuplicatesDirectly($a);

    expect($duplicates->pluck('id'))->toContain($b->id);
});

test('the duplicates endpoint returns the phone-duplicate person', function () {
    $this->actingAs(User::factory()->create(), 'user');

    $sharedPhone = '+31653231860';

    $a = Person::factory()->create([
        'first_name' => 'Ritske',
        'last_name'  => 'Clewits',
        'emails'     => [['value' => 'ritske.a@example.com', 'label' => 'eigen', 'is_default' => true]],
        'phones'     => [['value' => $sharedPhone, 'label' => 'mobiel', 'is_default' => true]],
    ]);

    $b = Person::factory()->create([
        'first_name' => 'Ritske',
        'last_name'  => 'Clewits',
        'emails'     => [['value' => 'ritske.b@example.com', 'label' => 'eigen', 'is_default' => true]],
        'phones'     => [['value' => $sharedPhone, 'label' => 'mobiel', 'is_default' => true]],
    ]);

    $response = $this->getJson(route('admin.contacts.persons.duplicates.get', ['id' => $a->id]));

    $response->assertOk();

    expect(collect($response->json('duplicates'))->pluck('id'))->toContain($b->id);
});

test('the person view page shows a duplicate warning when duplicates exist', function () {
    $this->actingAs(User::factory()->create(), 'user');

    $sharedPhone = '+31653231860';

    $a = Person::factory()->create([
        'first_name' => 'Ritske',
        'last_name'  => 'Clewits',
        'emails'     => [['value' => 'ritske.a@example.com', 'label' => 'eigen', 'is_default' => true]],
        'phones'     => [['value' => $sharedPhone, 'label' => 'mobiel', 'is_default' => true]],
    ]);

    Person::factory()->create([
        'first_name' => 'Ritske',
        'last_name'  => 'Clewits',
        'emails'     => [['value' => 'ritske.b@example.com', 'label' => 'eigen', 'is_default' => true]],
        'phones'     => [['value' => $sharedPhone, 'label' => 'mobiel', 'is_default' => true]],
    ]);

    $response = $this->get(route('admin.contacts.persons.view', $a->id));

    $response->assertOk()
        ->assertSee('mogelijk duplica', false)
        ->assertSee(route('admin.contacts.persons.duplicates.index', $a->id), false);
});

test('the person view page shows no duplicate warning when there are none', function () {
    $this->actingAs(User::factory()->create(), 'user');

    $person = Person::factory()->create([
        'first_name' => 'Zephyr',
        'last_name'  => 'Quintessential',
        'emails'     => [['value' => 'zephyr.unique@example.com', 'label' => 'eigen', 'is_default' => true]],
        'phones'     => [['value' => '+31600000000', 'label' => 'mobiel', 'is_default' => true]],
    ]);

    $this->get(route('admin.contacts.persons.view', $person->id))
        ->assertOk()
        ->assertDontSee('mogelijk duplica', false);
});
