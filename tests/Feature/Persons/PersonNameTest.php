<?php

use Webkul\Contact\Models\Person;

test('person name: first name and last name', function () {
    $person = Person::factory()->create([
        'first_name' => 'Jan',
        'last_name'  => 'Jansen',
        'is_active'  => true,
    ]);

    expect($person->name)->toBe('Jan Jansen');
});

test('person name: first name only', function () {
    $person = Person::factory()->create([
        'first_name' => 'Jan',
        'last_name'  => null,
        'is_active'  => true,
    ]);

    expect($person->name)->toBe('Jan');
});

test('person name: with lastname prefix', function () {
    $person = Person::factory()->create([
        'first_name'      => 'Jan',
        'lastname_prefix' => 'van',
        'last_name'       => 'Jansen',
        'is_active'       => true,
    ]);

    expect($person->name)->toBe('Jan van Jansen');
});

test('person name: with married name', function () {
    $person = Person::factory()->create([
        'first_name'   => 'Jan',
        'last_name'    => 'Jansen',
        'married_name' => 'Vries',
        'is_active'    => true,
    ]);

    expect($person->name)->toBe('Jan Jansen / Vries');
});

test('person name: with all name parts', function () {
    $person = Person::factory()->create([
        'first_name'          => 'Jan',
        'lastname_prefix'     => 'van',
        'last_name'           => 'Jansen',
        'married_name_prefix' => 'van de',
        'married_name'        => 'Vries',
        'is_active'           => true,
    ]);

    expect($person->name)->toBe('Jan van Jansen / van de Vries');
});

test('person name: inactive person appends inactief label', function () {
    $person = Person::factory()->create([
        'first_name' => 'Jan',
        'last_name'  => 'Jansen',
        'is_active'  => false,
    ]);

    expect($person->name)->toBe('Jan Jansen [Inactief]');
});
