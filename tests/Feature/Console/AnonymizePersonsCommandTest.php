<?php

namespace Tests\Feature\Console;

use App\Models\Address;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Models\Person;

test('it replaces identifying fields with fake data and preserves ids and relations', function () {
    $address = Address::factory()->create([
        'street'              => 'Echte Straat',
        'house_number'        => '12',
        'house_number_suffix' => 'A',
        'postal_code'         => '1234AB',
        'city'                => 'Amsterdam',
    ]);

    $person = Person::factory()->create([
        'first_name'                      => 'Jan',
        'last_name'                       => 'Jansen',
        'lastname_prefix'                 => 'van',
        'married_name'                    => 'Pietersen',
        'married_name_prefix'             => 'de',
        'initials'                        => 'J.J.',
        'date_of_birth'                   => '1980-05-10',
        'national_identification_number'  => '123456782',
        'general_practitioner_name'       => 'Dr. Huisarts',
        'general_practitioner_practice'   => 'Huisartsenpraktijk Centrum',
        'general_practitioner_phone'      => '0612345678',
        'emergency_contact_name'          => 'Marie Jansen',
        'emergency_contact_relationship'  => 'Partner',
        'emergency_contact_phone'         => '0687654321',
        'emails'                          => [['value' => 'jan.jansen@real-domain.nl', 'label' => 'Eigen', 'is_default' => true]],
        'phones'                          => [['value' => '0611111111', 'label' => 'Eigen', 'is_default' => true]],
        'address_id'                      => $address->id,
    ]);

    $otherPersonId = Person::factory()->create()->id;
    $originalEmail = $person->emails[0]['value'];

    $this->artisan('persons:anonymize', ['--force' => true])->assertSuccessful();

    $fresh = DB::table('persons')->where('id', $person->id)->first();
    $freshAddress = DB::table('addresses')->where('id', $address->id)->first();
    $emails = json_decode($fresh->emails, true);
    $phones = json_decode($fresh->phones, true);

    expect($fresh->id)->toBe($person->id)
        ->and($fresh->address_id)->toBe($address->id)
        ->and($fresh->first_name)->not->toBe('Jan')
        ->and($fresh->last_name)->not->toBe('Jansen')
        ->and($fresh->lastname_prefix)->not->toBe('van')
        ->and($fresh->married_name)->not->toBe('Pietersen')
        ->and($fresh->initials)->not->toBe('J.J.')
        ->and($fresh->date_of_birth)->not->toBe('1980-05-10')
        ->and($fresh->national_identification_number)->toBeNull()
        ->and($fresh->general_practitioner_name)->not->toBe('Dr. Huisarts')
        ->and($fresh->general_practitioner_phone)->not->toBe('0612345678')
        ->and($fresh->emergency_contact_name)->not->toBe('Marie Jansen')
        ->and($fresh->emergency_contact_relationship)->toBe('Partner')
        ->and($fresh->emergency_contact_phone)->not->toBe('0687654321')
        ->and($emails[0]['value'])->not->toBe($originalEmail)
        ->and($emails[0]['value'])->toEndWith('@example.com')
        ->and($emails[0]['label'])->toBe('Eigen')
        ->and($phones[0]['value'])->not->toBe('0611111111')
        ->and($freshAddress->street)->not->toBe('Echte Straat')
        ->and($freshAddress->house_number)->not->toBe('12')
        ->and($freshAddress->postal_code)->not->toBe('1234AB')
        ->and($freshAddress->city)->not->toBe('Amsterdam');

    expect(Person::where('id', $otherPersonId)->exists())->toBeTrue();
});

test('it leaves empty fields empty instead of inventing data', function () {
    $person = Person::factory()->create([
        'married_name'                   => null,
        'married_name_prefix'            => null,
        'date_of_birth'                  => null,
        'national_identification_number' => null,
        'general_practitioner_name'      => null,
        'emergency_contact_name'         => null,
        'address_id'                     => null,
    ]);

    $this->artisan('persons:anonymize', ['--force' => true])->assertSuccessful();

    $fresh = DB::table('persons')->where('id', $person->id)->first();

    expect($fresh->married_name)->toBeNull()
        ->and($fresh->married_name_prefix)->toBeNull()
        ->and($fresh->date_of_birth)->toBeNull()
        ->and($fresh->national_identification_number)->toBeNull()
        ->and($fresh->general_practitioner_name)->toBeNull()
        ->and($fresh->emergency_contact_name)->toBeNull();
});

test('it does not change the users table', function () {
    $user = makeUser(['email' => 'staff@real-domain.nl']);

    Person::factory()->create();

    $this->artisan('persons:anonymize', ['--force' => true])->assertSuccessful();

    expect($user->fresh()->email)->toBe('staff@real-domain.nl');
});

test('it reports how many persons were anonymized', function () {
    Person::factory()->count(3)->create();

    $this->artisan('persons:anonymize', ['--force' => true])
        ->expectsOutputToContain('Anonymized 3 person(s).')
        ->assertSuccessful();
});

test('it refuses to run outside local and testing environments', function () {
    app()->detectEnvironment(fn () => 'production');

    $person = Person::factory()->create(['first_name' => 'Jan']);

    $this->artisan('persons:anonymize', ['--force' => true])->assertFailed();

    expect($person->fresh()->first_name)->toBe('Jan');

    app()->detectEnvironment(fn () => 'testing');
});
