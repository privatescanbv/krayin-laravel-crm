<?php

use Database\Seeders\LeadChannelSeeder;
use Database\Seeders\TestSeeder;
use Webkul\Lead\Models\Lead;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->seed(LeadChannelSeeder::class);

    // API lead creation picks the first user as "current user"
    makeUser();

    // These endpoints are behind api.key in production; disable middleware in tests.
    $this->withoutMiddleware();
});

test('POST api/leads/privatescan creates a lead', function () {
    $payload = [
        'lead_source'      => 'privatescannl',
        'kanaal_c'         => 'website',
        'soort_aanvraag_c' => 'preventie',
        'salutation'       => 'Mr.',
        'first_name'       => 'Piet',
        'last_name'        => 'Pieters',
        'email'            => 'piet.pieters@example.com',
        'phone'            => '0611111111',
        'url'              => 'https://example.com/form',
        'section'          => 'home',
        'select_verzoek'   => 'Bel mij terug',
        'select_interesse' => 'Preventiescan',
        'personen'         => 2,
        'campaign_id'      => 'utm-ps-001',
    ];

    $res = $this->postJson('/api/leads/privatescan', $payload);

    $res->assertStatus(201)
        ->assertJsonPath('message', 'Lead created successfully.')
        ->assertJsonStructure(['lead_id', 'data' => ['id']]);

    $leadId = $res->json('data.id');
    expect($leadId)->not->toBeNull();
    expect($res->json('lead_id'))->toBe($leadId);

    $lead = Lead::find($leadId);
    expect($lead)->not->toBeNull()
        ->and($lead->first_name)->toBe('Piet')
        ->and($lead->last_name)->toBe('Pieters')
        ->and($lead->lead_type_id)->toBe(1) // Preventie
        ->and($lead->lead_source_id)->toBe(2) // privatescan.nl
        ->and($lead->lead_channel_id)->toBe(2); // Website
});
