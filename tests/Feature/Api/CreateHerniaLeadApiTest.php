<?php

use App\Models\Anamnesis;
use App\Models\LeadMarketingData;
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
        'campaign_id'                 => '39153848-eea7-9f47-cc53-5eb952a33583',
        'gad_campaignid'              => 'test-gad-campaign-123',
        'gclid'                       => 'test-gclid',
        'utm_term'                    => 'hernia behandeling',
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
        ->and($lead->lead_type_id)->toBe(3) // Operatie
        ->and($lead->description)->toBe('Test hernia lead');

    $this->assertDatabaseHas('lead_marketing_data', [
        'lead_id' => $leadId,
        'key'     => 'campaign_id',
        'value'   => '39153848-eea7-9f47-cc53-5eb952a33583',
    ]);

    $this->assertDatabaseHas('lead_marketing_data', [
        'lead_id' => $leadId,
        'key'     => 'gad_campaignid',
        'value'   => 'test-gad-campaign-123',
    ]);

    $this->assertDatabaseHas('lead_marketing_data', [
        'lead_id' => $leadId,
        'key'     => 'gclid',
        'value'   => 'test-gclid',
    ]);

    $this->assertDatabaseHas('lead_marketing_data', [
        'lead_id' => $leadId,
        'key'     => 'utm_term',
        'value'   => 'hernia behandeling',
    ]);
});

test('POST api/leads/hernia stores campaign_id without writing it into description', function () {
    $payload = [
        'lead_source'      => 'Herniapoli.nl',
        'kanaal_c'         => 'website',
        'soort_aanvraag_c' => 'operatie',
        'first_name'       => 'Jan',
        'last_name'        => 'Jansen',
        'email1'           => 'jan.jansen@example.com',
        'campaign_id'      => '39153848-eea7-9f47-cc53-5eb952a33583',
    ];

    $res = $this->postJson('/api/leads/hernia', $payload);
    $res->assertStatus(201);

    $leadId = $res->json('data.id');
    $lead = Lead::find($leadId);

    expect($lead->description)->toBeNull();

    $this->assertDatabaseHas('lead_marketing_data', [
        'lead_id' => $leadId,
        'key'     => 'campaign_id',
        'value'   => '39153848-eea7-9f47-cc53-5eb952a33583',
    ]);
});

test('POST api/leads/hernia rejects a real-world Facebook lead payload because campaign_id is not a known marketing_campaigns.external_id', function () {
    // Exact payload as received from a live Facebook/Gravity Forms hernia lead. Its "campaign_id"
    // (and "campaign") is a raw Facebook Ads campaign id, not a marketing_campaigns.external_id
    // (those are UUIDs, see CampaignSeeder), so MarketingCampaignExternalIdExists rejects it.
    $payload = [
        'lead_source'                 => 'Herniapoli.nl',
        'kanaal_c'                    => 'website',
        'soort_aanvraag_c'            => 'operatie',
        'salutation'                  => 'Mr.',
        'first_name'                  => 'Andre',
        'last_name'                   => 'Hammersma',
        'email1'                      => 'andrehammersma@gmail.com',
        'phone_mobile'                => '0647562481',
        'birthdate'                   => '1946-04-14',
        'description'                 => '',
        'campaign_id'                 => '120227054029630096',
        'origin_form'                 => 'Diagnose Formulier',
        'primary_huisnr_c'            => '',
        'primary_huisnr_toevoeging_c' => '',
        'primary_address_postalcode'  => '',
        'source'                      => 'fb',
        'medium'                      => 'paid',
        'campaign'                    => '120227054029630096',
        'adgroup'                     => '',
        'utm_term'                    => '120227054029640096',
        'utm_content'                 => '120227055208100096',
        'utm_id'                      => '120227054029630096',
        'gclid'                       => '',
        'gbraid'                      => '',
        'wbraid'                      => '',
        'gad_source'                  => '',
        'gad_campaignid'              => '',
        'landing_page'                => '/hernia-rug/?fbclid=abc&utm_medium=paid&utm_source=fb&utm_id=120227054029630096&utm_content=120227055208100096&utm_term=120227054029640096&utm_campaign=120227054029630096',
        'referrer'                    => 'http://m.facebook.com/',
        'first_visit_at'              => '2026-07-20T19:25:29.591Z',
        'last_visit_at'               => '2026-07-20T19:26:22.894Z',
        'attribution_url'             => 'https://www.herniapoli.nl/hernia-rug/?fbclid=abc&utm_medium=paid&utm_source=fb&utm_id=120227054029630096&utm_content=120227055208100096&utm_term=120227054029640096&utm_campaign=120227054029630096',
        'diagnoseform_pdf_url'        => 'https://herniapoli.nl/pdf/62e1bc9017ed3/31171/',
    ];

    $res = $this->postJson('/api/leads/hernia', $payload);

    $res->assertStatus(422)
        ->assertJsonValidationErrors(['campaign_id']);

    expect(Lead::where('email', 'andrehammersma@gmail.com')->exists())->toBeFalse();
});

test('POST api/leads/hernia creates anamnesis when person is attached', function () {
    $payload = [
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

test('POST api/leads/hernia returns a structured 500 (not a bare 200/201) when marketing data fails to persist, but the lead still exists', function () {
    LeadMarketingData::saving(function () {
        throw new RuntimeException('simulated marketing data failure');
    });

    $payload = [
        'lead_source'      => 'Herniapoli.nl',
        'kanaal_c'         => 'website',
        'soort_aanvraag_c' => 'operatie',
        'first_name'       => 'Jan',
        'last_name'        => 'Jansen',
        'email1'           => 'jan.jansen@example.com',
        'gad_campaignid'   => 'test-gad-campaign-123',
    ];

    $res = $this->postJson('/api/leads/hernia', $payload);

    $res->assertStatus(500)
        ->assertJsonStructure(['message', 'lead_id']);

    $leadId = $res->json('lead_id');
    expect($leadId)->not->toBeNull()
        ->and(Lead::find($leadId))->not->toBeNull(); // the lead is genuinely created despite the 500
});

test('POST api/leads/hernia rejects unknown properties (additionalProperties=false)', function () {
    $payload = [
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
