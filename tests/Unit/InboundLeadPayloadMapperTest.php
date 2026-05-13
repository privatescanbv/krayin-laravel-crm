<?php

use App\Services\InboundLeads\InboundLeadPayloadMapper;

test('extractHerniaMarketingData returns only supported non-empty tracking fields', function () {
    $mapper = new InboundLeadPayloadMapper;

    $result = $mapper->extractHerniaMarketingData([
        'source'          => ' google ',
        'medium'          => '',
        'campaign'        => null,
        'adgroup'         => false,
        'utm_term'        => 'mri scan',
        'utm_content'     => ' ',
        'utm_id'          => 'utm-123',
        'gclid'           => 'gclid-123',
        'gbraid'          => 'gbraid-123',
        'wbraid'          => 'wbraid-123',
        'gad_source'      => '1',
        'gad_campaignid'  => 'gad-123',
        'landing_page'    => 'https://example.com/landing',
        'referrer'        => 'https://google.com',
        'first_visit_at'  => '2026-04-29T10:00:00+02:00',
        'last_visit_at'   => '2026-04-30T10:00:00+02:00',
        'attribution_url' => 'https://example.com/?gclid=gclid-123',
        'unknown_key'     => 'ignored',
    ]);

    expect($result)->toBe([
        'source'          => 'google',
        'utm_term'        => 'mri scan',
        'utm_id'          => 'utm-123',
        'gclid'           => 'gclid-123',
        'gbraid'          => 'gbraid-123',
        'wbraid'          => 'wbraid-123',
        'gad_source'      => '1',
        'gad_campaignid'  => 'gad-123',
        'landing_page'    => 'https://example.com/landing',
        'referrer'        => 'https://google.com',
        'first_visit_at'  => '2026-04-29T10:00:00+02:00',
        'last_visit_at'   => '2026-04-30T10:00:00+02:00',
        'attribution_url' => 'https://example.com/?gclid=gclid-123',
    ]);
});

test('extractPrivatescanMarketingData returns supported non-empty tracking fields', function () {
    $mapper = new InboundLeadPayloadMapper;

    expect($mapper->extractPrivatescanMarketingData([
        'campaign_id' => ' 69b238c0-e630-b733-2bb3-4fd85ff554da ',
        'source'      => 'google',
        'medium'      => ' ',
        'utm_id'      => 'utm-123',
    ]))->toBe([
        'campaign_id' => '69b238c0-e630-b733-2bb3-4fd85ff554da',
        'source'      => 'google',
        'utm_id'      => 'utm-123',
    ])
        ->and($mapper->extractPrivatescanMarketingData([
            'campaign_id' => ' ',
            'source'      => null,
        ]))->toBe([]);
});
