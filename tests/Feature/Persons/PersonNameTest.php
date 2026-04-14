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

test('person name: with married name (no birth prefix, no married prefix)', function () {
    $person = Person::factory()->create([
        'first_name'   => 'Jan',
        'last_name'    => 'Jansen',
        'married_name' => 'Vries',
        'is_active'    => true,
    ]);

    expect($person->name)->toBe('Jan Vries - Jansen');
});

test('person name: with all name parts', function () {
    $person = Person::factory()->create([
        'first_name'          => 'Bart',
        'lastname_prefix'     => 'van',
        'last_name'           => 'Jansen',
        'married_name_prefix' => 'opt',
        'married_name'        => 'Nijhuis',
        'is_active'           => true,
    ]);

    expect($person->name)->toBe('Bart Nijhuis opt - Jansen van');
});

test('person name: inactive person appends inactief label', function () {
    $person = Person::factory()->create([
        'first_name' => 'Jan',
        'last_name'  => 'Jansen',
        'is_active'  => false,
    ]);

    expect($person->name)->toBe('Jan Jansen [Inactief]');
});

test('person name: married name only (no birth last name)', function () {
    $person = Person::factory()->create([
        'first_name'          => 'Jan',
        'last_name'           => null,
        'married_name'        => 'Vries',
        'married_name_prefix' => 'van de',
        'is_active'           => true,
    ]);

    expect($person->name)->toBe('Jan Vries van de');
});

test('person full_last_name: married name with all parts', function () {
    $person = Person::factory()->create([
        'first_name'          => 'Bart',
        'lastname_prefix'     => 'van',
        'last_name'           => 'Jansen',
        'married_name_prefix' => 'opt',
        'married_name'        => 'Nijhuis',
        'is_active'           => true,
    ]);

    expect($person->full_last_name)->toBe('Nijhuis opt - Jansen van');
});
