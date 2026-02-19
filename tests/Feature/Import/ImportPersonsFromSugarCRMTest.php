<?php

namespace Tests\Feature;

use App\Enums\ContactLabel;
use App\Models\Address;
use Database\Seeders\TestSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Contact\Models\Person;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    // Configure a separate in-memory sqlite for sugarcrm connection
    Config::set('database.connections.sugarcrm', [
        'driver'   => 'sqlite',
        'database' => ':memory:',
        'prefix'   => '',
    ]);

    // Recreate minimal SugarCRM tables on sugarcrm connection for each test
    foreach (['email_addr_bean_rel', 'email_addresses', 'contacts_cstm', 'contacts'] as $tbl) {
        if (Schema::connection('sugarcrm')->hasTable($tbl)) {
            Schema::connection('sugarcrm')->drop($tbl);
        }
    }

    Schema::connection('sugarcrm')->create('contacts', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('phone_work')->nullable();
        $table->string('phone_mobile')->nullable();
        $table->string('phone_home')->nullable();
        $table->string('phone_other')->nullable();
        $table->string('primary_address_street')->nullable();
        $table->string('primary_address_city')->nullable();
        $table->string('primary_address_state')->nullable();
        $table->string('primary_address_postalcode')->nullable();
        $table->string('primary_address_country')->nullable();
        $table->date('birthdate')->nullable();
        $table->dateTime('date_entered')->nullable();
        $table->dateTime('date_modified')->nullable();
        $table->integer('deleted')->default(0);
    });

    Schema::connection('sugarcrm')->create('contacts_cstm', function (Blueprint $table) {
        $table->string('id_c')->primary();
        $table->string('gender_c')->nullable();
        $table->string('meisjesnaam_c')->nullable();
        $table->string('aang_tussenv_c')->nullable();
        $table->string('roepnaam_c')->nullable();
        $table->string('voorletters_c')->nullable();
        $table->string('tussenvoegsel_c')->nullable();
        $table->string('primary_huisnr_c')->nullable();
        $table->string('primary_huisnr_toevoeging_c')->nullable();
    });

    Schema::connection('sugarcrm')->create('email_addresses', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('email_address');
        $table->integer('deleted')->default(0);
    });

    Schema::connection('sugarcrm')->create('email_addr_bean_rel', function (Blueprint $table) {
        $table->string('email_address_id');
        $table->string('bean_id');
        $table->string('bean_module');
        $table->integer('primary_address')->default(0);
        $table->integer('deleted')->default(0);
    });
});

test('imports person with emails, phones and address from sugarcrm sqlite stub', function () {
    // Insert stub data
    $contactId = 'contact-001';
    DB::connection('sugarcrm')->table('contacts')->insert([
        'id'                         => $contactId,
        'first_name'                 => 'Anna',
        'last_name'                  => 'Tester',
        'phone_work'                 => '010-123',
        'phone_mobile'               => '06-11251145',
        'primary_address_street'     => 'Teststraat',
        'primary_address_city'       => 'Rotterdam',
        'primary_address_state'      => 'ZH',
        'primary_address_postalcode' => '3000AA',
        'primary_address_country'    => 'NL',
        'birthdate'                  => '1991-02-03',
        'date_entered'               => now(),
        'date_modified'              => now(),
        'deleted'                    => 0,
    ]);

    DB::connection('sugarcrm')->table('contacts_cstm')->insert([
        'id_c'                              => $contactId,
        'gender_c'                          => 'female',
        'voorletters_c'                     => 'A.',
        'tussenvoegsel_c'                   => 'van',
        'meisjesnaam_c'                     => 'Jansen',
        'aang_tussenv_c'                    => 'de',
        'primary_huisnr_c'                  => '12',
        'primary_huisnr_toevoeging_c'       => 'B',
    ]);

    DB::connection('sugarcrm')->table('email_addresses')->insert([
        ['id' => 'e1', 'email_address' => 'anna.primary@example.com', 'deleted' => 0],
        ['id' => 'e2', 'email_address' => 'anna.other@example.com', 'deleted' => 0],
    ]);

    DB::connection('sugarcrm')->table('email_addr_bean_rel')->insert([
        ['email_address_id' => 'e1', 'bean_id' => $contactId, 'bean_module' => 'Contacts', 'primary_address' => 1, 'deleted' => 0],
        ['email_address_id' => 'e2', 'bean_id' => $contactId, 'bean_module' => 'Contacts', 'primary_address' => 0, 'deleted' => 0],
    ]);

    // Run command (non-dry to persist)
    $exit = Artisan::call('import:persons', [
        '--connection' => 'sugarcrm',
        '--table'      => 'contacts',
        '--limit'      => 10,
    ]);

    expect($exit)->toBe(0);

    // Assert person
    $person = Person::where('external_id', $contactId)->first();
    expect($person)->not->toBeNull()
        ->and($person->first_name)->toBe('Anna')
        ->and((string) $person->gender->value)->toBe('Vrouw')
        ->and((string) $person->salutation->value)->toBe('Mevr.')
        ->and($person->lastname_prefix)->toBe('van')
        ->and($person->married_name)->toBe('Jansen')
        ->and($person->married_name_prefix)->toBe('de')
        ->and($person->phones)->toBeArray()
        ->and(collect($person->phones)->pluck('value'))->toContain('010-123', '+31611251145')
        ->and($person->emails)->toBeArray()
        ->and($person->emails[0]['value'])->toBe('anna.primary@example.com');

    // Assert phones

    // Assert emails (primary or fallback exists)

    // Assert address created and linked via address_id
    expect($person->address_id)->not->toBeNull()
        ->and($person->address)->not->toBeNull()
        ->and($person->address->street)->toBe('Teststraat')
        ->and($person->address->house_number)->toBe('12')
        ->and($person->address->house_number_suffix)->toBe('B')
        ->and($person->address->postal_code)->toBe('3000AA')
        ->and($person->address->city)->toBe('Rotterdam')
        ->and($person->address->country)->toBe('NL');
});

test('imports person with primary and secondary emails ordered correctly', function () {
    $contactId = 'contact-002';
    DB::connection('sugarcrm')->table('contacts')->insert([
        'id'            => $contactId,
        'first_name'    => 'Bob',
        'last_name'     => 'Mailer',
        'date_entered'  => now(),
        'date_modified' => now(),
        'deleted'       => 0,
    ]);
    DB::connection('sugarcrm')->table('contacts_cstm')->insert([
        'id_c' => $contactId,
    ]);
    DB::connection('sugarcrm')->table('email_addresses')->insert([
        ['id' => 'e10', 'email_address' => 'bob.primary@example.com', 'deleted' => 0],
        ['id' => 'e11', 'email_address' => 'bob.secondary@example.com', 'deleted' => 0],
    ]);
    DB::connection('sugarcrm')->table('email_addr_bean_rel')->insert([
        ['email_address_id' => 'e10', 'bean_id' => $contactId, 'bean_module' => 'Contacts', 'primary_address' => 1, 'deleted' => 0],
        ['email_address_id' => 'e11', 'bean_id' => $contactId, 'bean_module' => 'Contacts', 'primary_address' => 0, 'deleted' => 0],
    ]);

    $exit = Artisan::call('import:persons', [
        '--connection' => 'sugarcrm',
        '--table'      => 'contacts',
        '--limit'      => 10,
    ]);
    expect($exit)->toBe(0);

    $person = Person::where('external_id', $contactId)->first();
    expect($person)->not->toBeNull();
    $emails = $person->emails;
    expect($emails)->toBeArray()
        ->and(count($emails))->toBeGreaterThanOrEqual(1)
        ->and($emails[0]['value'])->toBe('bob.primary@example.com');
});

test('person import strips label text from phone values and infers labels', function () {
    $contactId = 'contact-phones-001';

    DB::connection('sugarcrm')->table('contacts')->insert([
        'id'                         => $contactId,
        'first_name'                 => 'Chris',
        'last_name'                  => 'Phones',
        'phone_mobile'               => '+31623234434 (prive)',
        'phone_work'                 => 'werk +31201234567',
        'phone_home'                 => '+31851234567 thuis',
        'date_entered'               => now(),
        'date_modified'              => now(),
        'deleted'                    => 0,
    ]);

    DB::connection('sugarcrm')->table('contacts_cstm')->insert([
        'id_c' => $contactId,
    ]);

    $exit = Artisan::call('import:persons', [
        '--connection' => 'sugarcrm',
        '--table'      => 'contacts',
        '--limit'      => 10,
    ]);
    expect($exit)->toBe(0);

    $person = Person::where('external_id', $contactId)->first();
    expect($person)->not->toBeNull();

    $phones = collect($person->phones);

    // Mobile with (prive) becomes label home and value cleaned
    $mobile = $phones->firstWhere('value', '+31623234434');
    expect($mobile)->not->toBeNull()
        ->and($mobile['label'])->toBe(ContactLabel::default()->value);

    // Work prefixed becomes work
    $work = $phones->firstWhere('value', '+31201234567');
    expect($work)->not->toBeNull()
        ->and($work['label'])->toBe(ContactLabel::default()->value);

    // Home suffixed becomes home
    $home = $phones->firstWhere('value', '+31851234567');
    expect($home)->not->toBeNull()
        ->and($home['label'])->toBe(ContactLabel::default()->value);
});
