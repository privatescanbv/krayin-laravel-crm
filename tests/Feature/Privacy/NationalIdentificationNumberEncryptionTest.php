<?php

use Illuminate\Support\Facades\DB;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

it('stores national_identification_number encrypted for persons', function () {
    $bsn = '123456789';

    $person = Person::factory()->create([
        'first_name'                     => 'Test',
        'last_name'                      => 'Persoon',
        'national_identification_number' => $bsn,
    ]);

    $raw = DB::table('persons')
        ->where('id', $person->id)
        ->value('national_identification_number');

    expect($raw)->not->toBeNull();
    expect($raw)->not->toBe($bsn);
    expect($person->fresh()->national_identification_number)->toBe($bsn);
});

it('stores national_identification_number encrypted for leads', function () {
    $bsn = '987654321';

    $lead = Lead::factory()->create([
        'national_identification_number' => $bsn,
    ]);

    $raw = DB::table('leads')
        ->where('id', $lead->id)
        ->value('national_identification_number');

    expect($raw)->not->toBeNull();
    expect($raw)->not->toBe($bsn);
    expect($lead->fresh()->national_identification_number)->toBe($bsn);
});
