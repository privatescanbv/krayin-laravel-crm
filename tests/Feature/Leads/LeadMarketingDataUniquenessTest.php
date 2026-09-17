<?php

use App\Models\LeadMarketingData;
use Illuminate\Database\QueryException;
use Webkul\Lead\Models\Lead;

test('a lead cannot have two marketing_data rows for the same key', function () {
    $lead = Lead::factory()->create();

    LeadMarketingData::create(['lead_id' => $lead->id, 'key' => 'campaign_id', 'value' => 'first']);

    expect(fn () => LeadMarketingData::create(['lead_id' => $lead->id, 'key' => 'campaign_id', 'value' => 'second']))
        ->toThrow(QueryException::class);
});

test('the same key is still allowed on two different leads', function () {
    $leadA = Lead::factory()->create();
    $leadB = Lead::factory()->create();

    LeadMarketingData::create(['lead_id' => $leadA->id, 'key' => 'campaign_id', 'value' => 'a']);
    LeadMarketingData::create(['lead_id' => $leadB->id, 'key' => 'campaign_id', 'value' => 'b']);

    expect(LeadMarketingData::where('key', 'campaign_id')->count())->toBe(2);
});
