<?php

use App\Models\Department;
use App\Services\InboundLeads\InboundLeadPayloadMapper;
use Database\Seeders\CampaignSeeder;
use Database\Seeders\LeadChannelSeeder;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Webkul\Lead\Http\Controllers\Api\LeadController;
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
        'campaign_id'      => '69b238c0-e630-b733-2bb3-4fd85ff554da',
    ];

    $res = $this->postJson('/api/leads/privatescan', $payload);

    $res->assertStatus(201)
        ->assertJsonPath('message', 'Lead created successfully.')
        ->assertJsonStructure(['lead_id', 'data' => ['id']]);

    $leadId = $res->json('data.id');
    expect($leadId)->not->toBeNull()
        ->and($res->json('lead_id'))->toBe($leadId);

    $lead = Lead::find($leadId);
    expect($lead)->not->toBeNull()
        ->and($lead->first_name)->toBe('Piet')
        ->and($lead->last_name)->toBe('Pieters')
        ->and($lead->lead_type_id)->toBe(1) // Preventie
        ->and($lead->lead_source_id)->toBe(2) // privatescan.nl
        ->and($lead->lead_channel_id)->toBe(2); // Website

    $this->assertDatabaseHas('lead_marketing_data', [
        'lead_id' => $leadId,
        'key'     => 'campaign_id',
        'value'   => '69b238c0-e630-b733-2bb3-4fd85ff554da',
    ]);
});

test('inbound privatescan storage logs an error when campaign_id campaign is not found', function () {
    Log::spy();

    $campaignId = '00000000-0000-0000-0000-000000000000';
    $payload = [
        'lead_source'      => 'privatescannl',
        'kanaal_c'         => 'website',
        'soort_aanvraag_c' => 'preventie',
        'first_name'       => 'Piet',
        'last_name'        => 'Pieters',
        'email'            => 'piet.pieters@example.com',
        'phone'            => '0611111111',
        'campaign_id'      => $campaignId,
    ];

    $inbound = FormRequest::create('/api/leads/privatescan', 'POST', $payload);
    $inbound->setContainer(app());

    $controller = app(LeadController::class);
    $method = new ReflectionMethod($controller, 'storeInboundLead');
    $method->setAccessible(true);

    $response = $method->invoke(
        $controller,
        $inbound,
        app(InboundLeadPayloadMapper::class)->mapPrivatescan($payload),
        Department::findPrivateScanId(),
        ['campaign_id' => $campaignId],
        'api/leads/privatescan'
    );

    expect($response->getStatusCode())->toBe(201);

    $leadId = $response->getData(true)['data']['id'];

    expect(Lead::find($leadId))->not->toBeNull();
    $this->assertDatabaseHas('lead_marketing_data', [
        'lead_id' => $leadId,
        'key'     => 'campaign_id',
        'value'   => $campaignId,
    ]);

    Log::shouldHaveReceived('error')
        ->with(
            'Campaign not found by campaign_id',
            Mockery::on(fn (array $context): bool => ($context['campaign_id'] ?? null) === $campaignId
                && ($context['lead_id'] ?? null) === $leadId
                && ($context['endpoint'] ?? null) === 'api/leads/privatescan')
        )
        ->once();
});
