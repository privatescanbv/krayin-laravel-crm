<?php

use App\Models\Anamnesis;
use Database\Seeders\CampaignSeeder;
use Database\Seeders\LeadChannelSeeder;
use Database\Seeders\TestSeeder;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->seed(LeadChannelSeeder::class);
    $this->seed(CampaignSeeder::class);

    // API lead creation picks the first user as "current user"
    makeUser();

    // These endpoints are behind api.key in production; disable middleware in tests.
    $this->withoutMiddleware();
});

test('POST api/leads/hernia creates a lead', function () {
    $payload = [
        'campaign_id'                 => '69b238c0-e630-b733-2bb3-4fd85ff554da',
        'lead_source'                 => 'Herniapoli.nl',
        'kanaal_c'                    => 'website',
        'soort_aanvraag_c'            => 'operatie',
        'salutation'                  => 'Dhr.',
        'first_name'                  => 'Jan',
        'last_name'                   => 'Jansen',
        'birthdate'                   => '1980-01-02',
        'email1'                      => 'jan.jansen@example.com',
        'phone_mobile'                => '0612345678',
        'primary_huisnr_c'            => '12',
        'primary_huisnr_toevoeging_c' => 'A',
        'primary_address_postalcode'  => '1234AB',
        'description'                 => 'Test hernia lead',
    ];

    $res = $this->postJson('/api/leads/hernia', $payload);

    $res->assertStatus(201)
        ->assertJsonPath('message', 'Lead created successfully.')
        ->assertJsonStructure(['lead_id', 'data' => ['id']]);

    $leadId = $res->json('data.id');
    expect($leadId)->not->toBeNull()
        ->and($res->json('lead_id'))->toBe($leadId);

    $lead = Lead::find($leadId);
    expect($lead)->not->toBeNull()
        ->and($lead->first_name)->toBe('Jan')
        ->and($lead->last_name)->toBe('Jansen')
        ->and($lead->lead_type_id)->toBe(3); // Operatie
});

test('POST api/leads/hernia creates anamnesis when person is attached', function () {
    $payload = [
        'campaign_id'      => '69b238c0-e630-b733-2bb3-4fd85ff554da',
        'lead_source'      => 'Herniapoli.nl',
        'kanaal_c'         => 'website',
        'soort_aanvraag_c' => 'operatie',
        'first_name'       => 'Jan',
        'last_name'        => 'Jansen',
        'email1'           => 'jan.jansen@example.com',
    ];

    $res = $this->postJson('/api/leads/hernia', $payload);
    $res->assertStatus(201);

    $lead = Lead::find($res->json('data.id'));
    $person = Person::factory()->create();
    $lead->attachPersons([$person->id]);

    expect(Anamnesis::where('lead_id', $lead->id)->where('person_id', $person->id)->count())->toBe(1);
});

test('POST api/leads/hernia rejects unknown marketing campaign external_id', function () {
    $payload = [
        'campaign_id'      => '00000000-0000-0000-0000-000000000000',
        'lead_source'      => 'Herniapoli.nl',
        'kanaal_c'         => 'website',
        'soort_aanvraag_c' => 'operatie',
        'first_name'       => 'Jan',
        'last_name'        => 'Jansen',
        'email1'           => 'jan.jansen@example.com',
    ];

    $this->postJson('/api/leads/hernia', $payload)
        ->assertStatus(422)
        ->assertJsonStructure(['errors']);
});

test('POST api/leads/hernia rejects unknown properties (additionalProperties=false)', function () {
    $payload = [
        'campaign_id'      => '69b238c0-e630-b733-2bb3-4fd85ff554da',
        'lead_source'      => 'Herniapoli.nl',
        'kanaal_c'         => 'website',
        'soort_aanvraag_c' => 'operatie',
        'first_name'       => 'Jan',
        'last_name'        => 'Jansen',
        'email1'           => 'jan.jansen@example.com',
        'unexpected_field' => 'nope',
    ];

    $res = $this->postJson('/api/leads/hernia', $payload);

    $res->assertStatus(422)
        ->assertJsonStructure(['errors']);
});
