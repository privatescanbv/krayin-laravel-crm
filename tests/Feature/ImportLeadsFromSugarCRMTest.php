<?php

namespace Tests\Feature;

use App\Models\Address;
use Database\Seeders\TestSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    Config::set('database.connections.sugarcrm', [
        'driver'   => 'sqlite',
        'database' => ':memory:',
        'prefix'   => '',
    ]);

    // Drop if exist
    foreach ([
        'email_addr_bean_rel', 'email_addresses', 'leads_cstm', 'leads', 'leads_contacts_c',
        'leads_pcrm_anamnesepreventie_1_c', 'pcrm_anamnetie_contacts_c', 'pcrm_anamnesepreventie', 'pcrm_anamnesepreventie_cstm', 'calls',
    ] as $tbl) {
        if (Schema::connection('sugarcrm')->hasTable($tbl)) {
            Schema::connection('sugarcrm')->drop($tbl);
        }
    }

    Schema::connection('sugarcrm')->create('leads', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('phone_work')->nullable();
        $table->string('phone_mobile')->nullable();
        $table->string('phone_home')->nullable();
        $table->string('primary_address_street')->nullable();
        $table->string('primary_address_city')->nullable();
        $table->string('primary_address_state')->nullable();
        $table->string('primary_address_postalcode')->nullable();
        $table->string('primary_address_country')->nullable();
        $table->dateTime('date_entered')->nullable();
        $table->dateTime('date_modified')->nullable();
        $table->string('status')->nullable();
        $table->integer('deleted')->default(0);
        $table->string('lead_source')->nullable();
        $table->string('salutation')->nullable();
        $table->date('birthdate')->nullable();
    });

    Schema::connection('sugarcrm')->create('leads_cstm', function (Blueprint $table) {
        $table->string('id_c')->primary();
        $table->string('gender_c')->nullable();
        $table->string('workflow_status_c')->nullable();
        $table->string('kanaal_c')->nullable();
        $table->string('soort_aanvraag_c')->nullable();
        $table->string('meisjesnaam_c')->nullable();
        $table->string('aang_tussenv_c')->nullable();
        $table->string('lengte_c')->nullable();
        $table->string('gewicht_c')->nullable();
        $table->string('tussenvoegsel_c')->nullable();
        $table->string('reden_afvoeren_c')->nullable();
        // Additional nullable columns referenced by the importer select
        $table->string('op_een_factuur_c')->nullable();
        $table->text('anamnese_c')->nullable();
        $table->text('partner_anamnese_c')->nullable();
        $table->integer('metalen_c')->nullable();
        $table->integer('medicijnen_c')->nullable();
        $table->integer('glaucoom_c')->nullable();
        $table->integer('claustrofobie_c')->nullable();
        $table->text('opmerking_c')->nullable();
        $table->string('partner_medicijnen_c')->nullable();
        $table->string('partner_smoking_c')->nullable();
        $table->string('partner_diabetes_c')->nullable();
        $table->string('partner_vaat_erfelijk_c')->nullable();
        $table->string('partner_gewicht_c')->nullable();
        $table->string('partner_metalen_c')->nullable();
        $table->string('partner_tumoren_erfelijk_c')->nullable();
        $table->string('partner_glaucoom_c')->nullable();
        $table->string('partner_meisjesnaam_c')->nullable();
        $table->string('partner_lengte_c')->nullable();
        $table->string('partner_first_name_c')->nullable();
        $table->string('partner_heart_problems_c')->nullable();
        $table->string('partner_rugklachten_c')->nullable();
        $table->string('partner_last_name_c')->nullable();
        $table->string('partner_dormicum_c')->nullable();
        $table->string('partner_claustrofobie_c')->nullable();
        $table->string('partner_spijsverteringsklach_c')->nullable();
        $table->string('partner_hart_erfelijk_c')->nullable();
        $table->string('partner_salutation_c')->nullable();
        $table->string('partner_opmerking_c')->nullable();
        $table->string('partner_risico_hartinfarct_c')->nullable();
        $table->string('ms_sinds_c')->nullable();
        $table->string('ms_type_c')->nullable();
        $table->string('spreektalen_c')->nullable();
        $table->string('straat_c')->nullable();
        $table->string('primary_huisnr_c')->nullable();
        $table->string('primary_huisnr_toevoeging_c')->nullable();
        $table->string('nieuwsbrief_vraag_c')->nullable();
        $table->string('reset_wfl_status_c')->nullable();
        $table->string('particulier_c')->nullable();
        $table->string('roepnaam_c')->nullable();
        $table->string('voorletters_c')->nullable();
        $table->string('leeftijd_c')->nullable();
        $table->string('allergie_c')->nullable();
        $table->string('opm_allergie_c')->nullable();
        $table->string('hart_operatie_c')->nullable();
        $table->string('opm_hart_operatie_c')->nullable();
        $table->string('implantaat_c')->nullable();
        $table->string('opm_implantaat_c')->nullable();
        $table->string('interes_info_c')->nullable();
        $table->string('operaties_c')->nullable();
        $table->string('opm_erf_tumoren_c')->nullable();
        $table->string('partner_birthdate_c')->nullable();
        $table->string('partner_gender_c')->nullable();
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

    Schema::connection('sugarcrm')->create('leads_contacts_c', function (Blueprint $table) {
        $table->string('leads_c7104eads_ida'); // lead id
        $table->string('leads_cbb5dacts_idb'); // person id
        $table->integer('deleted')->default(0);
    });

    // Minimal anamnesis relation tables (empty ok)
    Schema::connection('sugarcrm')->create('leads_pcrm_anamnesepreventie_1_c', function (Blueprint $table) {
        $table->string('leads_pcrm_anamnesepreventie_1leads_ida')->nullable();
        $table->string('leads_pcrm_anamnesepreventie_1pcrm_anamnesepreventie_idb')->nullable();
        $table->integer('deleted')->default(0);
    });
    Schema::connection('sugarcrm')->create('pcrm_anamnetie_contacts_c', function (Blueprint $table) {
        $table->string('pcrm_anamn171deventie_idb')->nullable();
        $table->string('pcrm_anamn0b6eontacts_ida')->nullable();
        $table->integer('deleted')->default(0);
    });
    Schema::connection('sugarcrm')->create('pcrm_anamnesepreventie', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('name')->nullable();
        $table->dateTime('date_entered')->nullable();
        $table->dateTime('date_modified')->nullable();
        $table->string('modified_user_id')->nullable();
        $table->string('created_by')->nullable();
        $table->text('description')->nullable();
        $table->integer('deleted')->default(0);
        $table->string('team_id')->nullable();
        $table->string('team_set_id')->nullable();
        $table->string('assigned_user_id')->nullable();
        $table->string('status')->nullable();
        // medical fields
        $table->text('anamnese')->nullable();
        $table->string('lengte')->nullable();
        $table->string('gewicht')->nullable();
        $table->integer('metalen')->nullable();
        $table->integer('medicijnen')->nullable();
        $table->integer('glaucoom')->nullable();
        $table->integer('claustrofobie')->nullable();
        $table->integer('dormicum')->nullable();
        $table->text('opmerking')->nullable();
        $table->integer('hart_erfelijk')->nullable();
        $table->integer('vaat_erfelijk')->nullable();
        $table->integer('tumoren_erfelijk')->nullable();
        $table->integer('rugklachten')->nullable();
        $table->integer('heart_problems')->nullable();
        $table->integer('smoking')->nullable();
        $table->integer('diabetes')->nullable();
        $table->integer('spijsverteringsklachten')->nullable();
        $table->string('risico_hartinfarct')->nullable();
        $table->date('anamnese_datum')->nullable();
    });
    Schema::connection('sugarcrm')->create('pcrm_anamnesepreventie_cstm', function (Blueprint $table) {
        $table->string('id_c')->primary();
        $table->text('opm_metalen_c')->nullable();
        $table->text('opm_medicijnen_c')->nullable();
        $table->text('opm_glaucoom_c')->nullable();
        $table->text('opm_erf_hart_c')->nullable();
        $table->text('opm_erf_vaat_c')->nullable();
        $table->text('opm_erf_tumor_c')->nullable();
        $table->text('opm_rugklachten_c')->nullable();
        $table->text('opm_hartklachten_c')->nullable();
        $table->text('opm_roken_c')->nullable();
        $table->text('opm_diabetes_c')->nullable();
        $table->text('opm_spijsvertering_c')->nullable();
        $table->integer('operaties_c')->nullable();
        $table->text('opm_operaties_c')->nullable();
        $table->text('opm_advies_c')->nullable();
        $table->integer('hart_operatie_c')->nullable();
        $table->integer('allergie_c')->nullable();
        $table->integer('implantaat_c')->nullable();
        $table->text('opm_allergie_c')->nullable();
        $table->text('opm_hart_operatie_c')->nullable();
        $table->text('opm_implantaat_c')->nullable();
        $table->string('aos_products_id_c')->nullable();
        $table->text('diagnose_c')->nullable();
        $table->integer('operatieopname_c')->nullable();
    });

    // Create calls table for call activities
    Schema::connection('sugarcrm')->create('calls', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('name')->nullable();
        $table->dateTime('date_entered')->nullable();
        $table->dateTime('date_modified')->nullable();
        $table->string('modified_user_id')->nullable();
        $table->string('created_by')->nullable();
        $table->text('description')->nullable();
        $table->integer('deleted')->default(0);
        $table->string('assigned_user_id')->nullable();
        $table->dateTime('date_start')->nullable();
        $table->dateTime('date_end')->nullable();
        $table->string('parent_type')->nullable();
        $table->string('status')->nullable();
        $table->string('direction')->nullable();
        $table->string('parent_id')->nullable();
        $table->string('belgroep_c')->nullable();
    });
});

test('imports lead created_at parsed correctly from sugarcrm', function () {
    // Create app person that will be linked to lead via anamnesis relation
    $personExternalId = 'person-001';
    $appPerson = Person::factory()->create(['external_id' => $personExternalId]);

    // Insert sugarcrm lead and related data
    $leadId = 'lead-001';
    DB::connection('sugarcrm')->table('leads')->insert([
        'id'                         => $leadId,
        'first_name'                 => 'Lead',
        'last_name'                  => 'Tester',
        'status'                     => 'New',
        'primary_address_street'     => 'Leadstraat',
        'primary_address_city'       => 'Utrecht',
        'primary_address_state'      => 'UT',
        'primary_address_postalcode' => '3500AB',
        'primary_address_country'    => 'NL',
        'date_entered'               => '2025-06-11 13:41:07', // treated as UTC in code
        'date_modified'              => '2025-06-12 10:00:00',
        'deleted'                    => 0,
    ]);
    DB::connection('sugarcrm')->table('leads_cstm')->insert([
        'id_c'                        => $leadId,
        'workflow_status_c'           => 'nieuweaanvraag',
        'kanaal_c'                    => 'website',
        'soort_aanvraag_c'            => 'preventie',
        'gender_c'                    => 'male',
        'meisjesnaam_c'               => 'Jansen',
        'aang_tussenv_c'              => 'de',
        'primary_huisnr_c'            => '123',
        'primary_huisnr_toevoeging_c' => 'A',
    ]);
    // Email primary
    DB::connection('sugarcrm')->table('email_addresses')->insert([
        ['id' => 'e1', 'email_address' => 'lead.primary@example.com', 'deleted' => 0],
    ]);
    DB::connection('sugarcrm')->table('email_addr_bean_rel')->insert([
        ['email_address_id' => 'e1', 'bean_id' => $leadId, 'bean_module' => 'Leads', 'primary_address' => 1, 'deleted' => 0],
    ]);
    // Create anamnesis records and relations to ensure mapping works
    $anamnesisId = 'an-001';
    DB::connection('sugarcrm')->table('pcrm_anamnesepreventie')->insert([
        'id'            => $anamnesisId,
        'name'          => 'An test',
        'status'        => 'active',
        'date_entered'  => now(),
        'date_modified' => now(),
        'deleted'       => 0,
    ]);
    DB::connection('sugarcrm')->table('pcrm_anamnesepreventie_cstm')->insert([
        'id_c' => $anamnesisId,
    ]);
    // Link lead to anamnesis
    DB::connection('sugarcrm')->table('leads_pcrm_anamnesepreventie_1_c')->insert([
        'leads_pcrm_anamnesepreventie_1leads_ida'                  => $leadId,
        'leads_pcrm_anamnesepreventie_1pcrm_anamnesepreventie_idb' => $anamnesisId,
        'deleted'                                                  => 0,
    ]);
    // Link anamnesis to person
    DB::connection('sugarcrm')->table('pcrm_anamnetie_contacts_c')->insert([
        'pcrm_anamn171deventie_idb' => $anamnesisId,
        'pcrm_anamn0b6eontacts_ida' => $personExternalId,
        'deleted'                   => 0,
    ]);
    // Also add simple lead->person mapping used to filter personIds in extractAnamenesis
    DB::connection('sugarcrm')->table('leads_contacts_c')->insert([
        'leads_c7104eads_ida' => $leadId,
        'leads_cbb5dacts_idb' => $personExternalId,
        'deleted'             => 0,
    ]);

    $exit = Artisan::call('import:leads', [
        '--connection' => 'sugarcrm',
        '--limit'      => 1,
    ]);
    expect($exit)->toBe(0);

    $lead = Lead::where('external_id', $leadId)->first();
    expect($lead)->not->toBeNull();

    // Personal fields assertions
    expect($lead->married_name)->toBe('Jansen')
        ->and($lead->married_name_prefix)->toBe('de');

    $expected = Carbon::parse('2025-06-11 13:41:07', 'UTC')
        ->setTimezone(config('app.timezone'))
        ->format('Y-m-d H:i:s');
    expect($lead->created_at->format('Y-m-d H:i:s'))->toBe($expected);

    // Address created and mapped
    $address = Address::where('lead_id', $lead->id)->first();
    expect($address)->not->toBeNull()
        ->and($address->street)->toBe('Leadstraat')
        ->and($address->house_number)->toBe('123')
        ->and($address->house_number_suffix)->toBe('A')
        ->and($address->postal_code)->toBe('3500AB')
        ->and($address->city)->toBe('Utrecht')
        ->and($address->state)->toBe('UT')
        ->and($address->country)->toBe('NL');
});

test('imports lead with multiple persons correctly', function () {
    // Create app persons that will be linked to lead
    $person1ExternalId = 'person-multi-001';
    $person2ExternalId = 'person-multi-002';
    $appPerson1 = Person::factory()->create(['external_id' => $person1ExternalId]);
    $appPerson2 = Person::factory()->create(['external_id' => $person2ExternalId]);

    // Insert sugarcrm lead and related data
    $leadId = 'lead-multi-001';
    DB::connection('sugarcrm')->table('leads')->insert([
        'id'            => $leadId,
        'first_name'    => 'Multi',
        'last_name'     => 'Person',
        'status'        => 'New',
        'date_entered'  => '2025-06-11 13:41:07',
        'date_modified' => '2025-06-12 10:00:00',
        'deleted'       => 0,
    ]);
    DB::connection('sugarcrm')->table('leads_cstm')->insert([
        'id_c'              => $leadId,
        'workflow_status_c' => 'nieuweaanvraag',
        'kanaal_c'          => 'website',
        'soort_aanvraag_c'  => 'preventie',
        'gender_c'          => 'male',
    ]);

    // Email primary
    DB::connection('sugarcrm')->table('email_addresses')->insert([
        ['id' => 'e-multi-1', 'email_address' => 'multi.primary@example.com', 'deleted' => 0],
    ]);
    DB::connection('sugarcrm')->table('email_addr_bean_rel')->insert([
        ['email_address_id' => 'e-multi-1', 'bean_id' => $leadId, 'bean_module' => 'Leads', 'primary_address' => 1, 'deleted' => 0],
    ]);

    // Create anamnesis records for both persons
    $anamnesis1Id = 'an-multi-001';
    $anamnesis2Id = 'an-multi-002';

    DB::connection('sugarcrm')->table('pcrm_anamnesepreventie')->insert([
        [
            'id'            => $anamnesis1Id,
            'name'          => 'Anamnesis Person 1',
            'status'        => 'active',
            'date_entered'  => now(),
            'date_modified' => now(),
            'deleted'       => 0,
            'lengte'        => '170',
            'gewicht'       => '65',
            'metalen'       => 0,
            'medicijnen'    => 1,
        ],
        [
            'id'            => $anamnesis2Id,
            'name'          => 'Anamnesis Person 2',
            'status'        => 'active',
            'date_entered'  => now(),
            'date_modified' => now(),
            'deleted'       => 0,
            'lengte'        => '180',
            'gewicht'       => '75',
            'metalen'       => 1,
            'medicijnen'    => 0,
        ],
    ]);

    DB::connection('sugarcrm')->table('pcrm_anamnesepreventie_cstm')->insert([
        ['id_c' => $anamnesis1Id],
        ['id_c' => $anamnesis2Id],
    ]);

    // Link lead to both anamnesis records
    DB::connection('sugarcrm')->table('leads_pcrm_anamnesepreventie_1_c')->insert([
        [
            'leads_pcrm_anamnesepreventie_1leads_ida'                  => $leadId,
            'leads_pcrm_anamnesepreventie_1pcrm_anamnesepreventie_idb' => $anamnesis1Id,
            'deleted'                                                  => 0,
        ],
        [
            'leads_pcrm_anamnesepreventie_1leads_ida'                  => $leadId,
            'leads_pcrm_anamnesepreventie_1pcrm_anamnesepreventie_idb' => $anamnesis2Id,
            'deleted'                                                  => 0,
        ],
    ]);

    // Link anamnesis to persons
    DB::connection('sugarcrm')->table('pcrm_anamnetie_contacts_c')->insert([
        [
            'pcrm_anamn171deventie_idb' => $anamnesis1Id,
            'pcrm_anamn0b6eontacts_ida' => $person1ExternalId,
            'deleted'                   => 0,
        ],
        [
            'pcrm_anamn171deventie_idb' => $anamnesis2Id,
            'pcrm_anamn0b6eontacts_ida' => $person2ExternalId,
            'deleted'                   => 0,
        ],
    ]);

    // Add lead->person mappings for both persons
    DB::connection('sugarcrm')->table('leads_contacts_c')->insert([
        [
            'leads_c7104eads_ida' => $leadId,
            'leads_cbb5dacts_idb' => $person1ExternalId,
            'deleted'             => 0,
        ],
        [
            'leads_c7104eads_ida' => $leadId,
            'leads_cbb5dacts_idb' => $person2ExternalId,
            'deleted'             => 0,
        ],
    ]);

    $exit = Artisan::call('import:leads', [
        '--connection' => 'sugarcrm',
        '--limit'      => 1,
    ]);
    expect($exit)->toBe(0);

    $lead = Lead::where('external_id', $leadId)->first();
    expect($lead)->not->toBeNull();

    // Check that both persons are attached to the lead
    $attachedPersons = $lead->persons;
    expect($attachedPersons)->not->toBeNull()
        ->and($attachedPersons)->toBeInstanceOf(Collection::class)
        ->and($attachedPersons)->toHaveCount(2);

    $personIds = $attachedPersons->pluck('id')->toArray();
    expect($personIds)->toContain($appPerson1->id)
        ->and($personIds)->toContain($appPerson2->id);

    // Check that anamnesis records are created for both persons
    $anamnesisRecords = $lead->anamnesis;
    expect($anamnesisRecords)->not->toBeNull()
        ->and($anamnesisRecords)->toBeInstanceOf(Collection::class)
        ->and($anamnesisRecords)->toHaveCount(2);

    // Verify anamnesis data for person 1
    $anamnesis1 = $anamnesisRecords->where('person_id', $appPerson1->id)->first();
    expect($anamnesis1)->not->toBeNull()
        ->and($anamnesis1->height)->toBe(170)
        ->and($anamnesis1->weight)->toBe(65)
        ->and($anamnesis1->metals)->toBe(false)
        ->and($anamnesis1->medications)->toBe(true);

    // Verify anamnesis data for person 2
    $anamnesis2 = $anamnesisRecords->where('person_id', $appPerson2->id)->first();
    expect($anamnesis2)->not->toBeNull()
        ->and($anamnesis2->height)->toBe(180)
        ->and($anamnesis2->weight)->toBe(75)
        ->and($anamnesis2->metals)->toBe(true)
        ->and($anamnesis2->medications)->toBe(false);
});

test('imports call activities from sugarcrm', function () {
    // Create app person that will be linked to lead
    $personExternalId = 'person-001';
    $appPerson = Person::factory()->create(['external_id' => $personExternalId]);

    $leadId = 'lead-001';
    $callId1 = 'call-001';
    $callId2 = 'call-002';

    // Insert SugarCRM lead data
    DB::connection('sugarcrm')->table('leads')->insert([
        'id' => $leadId,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'phone_work' => '+31612345678',
        'date_entered' => '2024-01-15 10:00:00',
        'date_modified' => '2024-01-15 11:00:00',
        'status' => 'New',
        'deleted' => 0,
    ]);

    // Insert custom fields
    DB::connection('sugarcrm')->table('leads_cstm')->insert([
        'id_c' => $leadId,
        'workflow_status_c' => 'nieuweaanvraag',
        'kanaal_c' => 'website',
        'soort_aanvraag_c' => 'preventie',
    ]);

    // Link lead to person
    DB::connection('sugarcrm')->table('leads_contacts_c')->insert([
        'leads_c7104eads_ida' => $leadId,
        'leads_cbb5dacts_idb' => $personExternalId,
        'deleted' => 0,
    ]);

    // Insert call activities
    DB::connection('sugarcrm')->table('calls')->insert([
        [
            'id' => $callId1,
            'name' => 'Intake gesprek',
            'date_entered' => '2024-01-16 09:00:00',
            'date_modified' => '2024-01-16 09:30:00',
            'description' => 'Eerste intake gesprek met klant',
            'deleted' => 0,
            'date_start' => '2024-01-16 14:00:00',
            'date_end' => '2024-01-16 14:30:00',
            'parent_type' => 'Leads',
            'status' => 'held',
            'direction' => 'outbound',
            'parent_id' => $leadId,
            'belgroep_c' => 'intake',
        ],
        [
            'id' => $callId2,
            'name' => 'Follow-up call',
            'date_entered' => '2024-01-17 10:00:00',
            'date_modified' => '2024-01-17 10:15:00',
            'description' => 'Follow-up gesprek',
            'deleted' => 0,
            'date_start' => '2024-01-17 15:00:00',
            'date_end' => '2024-01-17 15:15:00',
            'parent_type' => 'Leads',
            'status' => 'planned',
            'direction' => 'outbound',
            'parent_id' => $leadId,
            'belgroep_c' => 'follow-up',
        ],
    ]);

    // Run import
    $exit = Artisan::call('import:leads', [
        '--connection' => 'sugarcrm',
        '--limit' => 1,
    ]);
    expect($exit)->toBe(0);

    // Verify lead was imported
    $lead = Lead::where('external_id', $leadId)->first();
    expect($lead)->not->toBeNull();

    // Verify call activities were imported
    $activities = $lead->activities()->where('type', 'call')->get();
    expect($activities)->toHaveCount(2);

    // Check first call activity
    $activity1 = $activities->where('additional->external_id', $callId1)->first();
    expect($activity1)->not->toBeNull()
        ->and($activity1->title)->toBe('Intake gesprek')
        ->and($activity1->type)->toBe('call')
        ->and($activity1->comment)->toBe('Eerste intake gesprek met klant')
        ->and($activity1->is_done)->toBe(true) // 'held' status should map to done
        ->and($activity1->additional['direction'])->toBe('outbound')
        ->and($activity1->additional['status'])->toBe('held')
        ->and($activity1->additional['belgroep'])->toBe('intake');

    // Check second call activity
    $activity2 = $activities->where('additional->external_id', $callId2)->first();
    expect($activity2)->not->toBeNull()
        ->and($activity2->title)->toBe('Follow-up call')
        ->and($activity2->type)->toBe('call')
        ->and($activity2->comment)->toBe('Follow-up gesprek')
        ->and($activity2->is_done)->toBe(false) // 'planned' status should map to not done
        ->and($activity2->additional['direction'])->toBe('outbound')
        ->and($activity2->additional['status'])->toBe('planned')
        ->and($activity2->additional['belgroep'])->toBe('follow-up');
});
