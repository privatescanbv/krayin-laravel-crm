<?php

use Webkul\Lead\Models\Lead;

test('lead name: first name and last name', function () {
    $lead = Lead::factory()->create([
        'first_name' => 'Jan',
        'last_name'  => 'Jansen',
    ]);

    expect($lead->name)->toBe('Jan Jansen');
});

test('lead name: first name only', function () {
    $lead = Lead::factory()->create([
        'first_name' => 'Jan',
        'last_name'  => null,
    ]);

    expect($lead->name)->toBe('Jan');
});

test('lead name: with lastname prefix', function () {
    $lead = Lead::factory()->create([
        'first_name'      => 'Jan',
        'lastname_prefix' => 'van',
        'last_name'       => 'Jansen',
    ]);

    expect($lead->name)->toBe('Jan van Jansen');
});

test('lead name: with married name', function () {
    $lead = Lead::factory()->create([
        'first_name'   => 'Jan',
        'last_name'    => 'Jansen',
        'married_name' => 'Vries',
    ]);

    expect($lead->name)->toBe('Jan Jansen / Vries');
});

test('lead name: with all name parts', function () {
    $lead = Lead::factory()->create([
        'first_name'          => 'Jan',
        'lastname_prefix'     => 'van',
        'last_name'           => 'Jansen',
        'married_name_prefix' => 'van de',
        'married_name'        => 'Vries',
    ]);

    expect($lead->name)->toBe('Jan van Jansen / van de Vries');
});
