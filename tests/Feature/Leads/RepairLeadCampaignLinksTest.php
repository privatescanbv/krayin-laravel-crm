<?php

use App\Models\LeadMarketingData;
use Database\Seeders\CampaignSeeder;
use Webkul\Lead\Models\Lead;

beforeEach(function () {
    $this->seed(CampaignSeeder::class);
});

test('it backfills campaign_id from origin_form', function () {
    $lead = Lead::factory()->create();
    LeadMarketingData::create([
        'lead_id' => $lead->id,
        'key'     => 'origin_form',
        'value'   => 'Diagnose Formulier',
    ]);

    $this->artisan('leads:repair-campaign-links')->assertSuccessful();

    $this->assertDatabaseHas('lead_marketing_data', [
        'lead_id' => $lead->id,
        'key'     => 'campaign_id',
        'value'   => '39153848-eea7-9f47-cc53-5eb952a33583',
    ]);
});

test('it backfills campaign_id from description when the campaign exists', function () {
    $lead = Lead::factory()->create([
        'description' => "Vraag via website\nCampaign external_id: 69b238c0-e630-b733-2bb3-4fd85ff554da",
    ]);

    $this->artisan('leads:repair-campaign-links')->assertSuccessful();

    $this->assertDatabaseHas('lead_marketing_data', [
        'lead_id' => $lead->id,
        'key'     => 'campaign_id',
        'value'   => '69b238c0-e630-b733-2bb3-4fd85ff554da',
    ]);
});

test('description uuid wins over origin_form', function () {
    $lead = Lead::factory()->create([
        'description' => 'Campaign external_id: 81289196-5d51-817a-c8c5-5eb9419e4d9b',
    ]);
    LeadMarketingData::create([
        'lead_id' => $lead->id,
        'key'     => 'origin_form',
        'value'   => 'Diagnose Formulier',
    ]);

    $this->artisan('leads:repair-campaign-links')->assertSuccessful();

    $this->assertDatabaseHas('lead_marketing_data', [
        'lead_id' => $lead->id,
        'key'     => 'campaign_id',
        'value'   => '81289196-5d51-817a-c8c5-5eb9419e4d9b',
    ]);
    $this->assertDatabaseCount('lead_marketing_data', 2);
});

test('it does not overwrite a campaign_id that already matches a campaign', function () {
    $lead = Lead::factory()->create();
    LeadMarketingData::create([
        'lead_id' => $lead->id,
        'key'     => 'campaign_id',
        'value'   => 'b5d4e16f-0246-36d7-bacc-4fd85e8ee774',
    ]);
    LeadMarketingData::create([
        'lead_id' => $lead->id,
        'key'     => 'origin_form',
        'value'   => 'Diagnose Formulier',
    ]);

    $this->artisan('leads:repair-campaign-links')->assertSuccessful();

    $this->assertDatabaseHas('lead_marketing_data', [
        'lead_id' => $lead->id,
        'key'     => 'campaign_id',
        'value'   => 'b5d4e16f-0246-36d7-bacc-4fd85e8ee774',
    ]);
});

test('it replaces an unmatched campaign_id when origin_form maps to a known campaign', function () {
    $lead = Lead::factory()->create();
    LeadMarketingData::create([
        'lead_id' => $lead->id,
        'key'     => 'campaign_id',
        'value'   => 'not-a-real-campaign',
    ]);
    LeadMarketingData::create([
        'lead_id' => $lead->id,
        'key'     => 'origin_form',
        'value'   => 'Contact',
    ]);

    $this->artisan('leads:repair-campaign-links', ['--lead' => [$lead->id]])->assertSuccessful();

    $this->assertDatabaseHas('lead_marketing_data', [
        'lead_id' => $lead->id,
        'key'     => 'campaign_id',
        'value'   => '81289196-5d51-817a-c8c5-5eb9419e4d9b',
    ]);
    expect(LeadMarketingData::query()->where('lead_id', $lead->id)->where('key', 'campaign_id')->count())->toBe(1);
});

test('unknown origin_form and gad_campaignid are left alone', function () {
    $lead = Lead::factory()->create();
    LeadMarketingData::create([
        'lead_id' => $lead->id,
        'key'     => 'origin_form',
        'value'   => 'Onbekend formulier',
    ]);
    LeadMarketingData::create([
        'lead_id' => $lead->id,
        'key'     => 'gad_campaignid',
        'value'   => '20706069315',
    ]);

    $this->artisan('leads:repair-campaign-links')->assertSuccessful();

    $this->assertDatabaseMissing('lead_marketing_data', [
        'lead_id' => $lead->id,
        'key'     => 'campaign_id',
    ]);
});

test('dry run does not persist campaign_id', function () {
    $lead = Lead::factory()->create();
    LeadMarketingData::create([
        'lead_id' => $lead->id,
        'key'     => 'origin_form',
        'value'   => 'Bel mij terug',
    ]);

    $this->artisan('leads:repair-campaign-links', ['--dry-run' => true])->assertSuccessful();

    $this->assertDatabaseMissing('lead_marketing_data', [
        'lead_id' => $lead->id,
        'key'     => 'campaign_id',
    ]);
});

test('--lead limits which rows are repaired', function () {
    $repair = Lead::factory()->create();
    $skip = Lead::factory()->create();
    LeadMarketingData::create([
        'lead_id' => $repair->id,
        'key'     => 'origin_form',
        'value'   => 'Afspraak maken MRI-scan',
    ]);
    LeadMarketingData::create([
        'lead_id' => $skip->id,
        'key'     => 'origin_form',
        'value'   => 'Afspraak maken MRI-scan',
    ]);

    $this->artisan('leads:repair-campaign-links', ['--lead' => [$repair->id]])->assertSuccessful();

    $this->assertDatabaseHas('lead_marketing_data', [
        'lead_id' => $repair->id,
        'key'     => 'campaign_id',
        'value'   => '26221d52-feff-19b8-3680-64ae9e1224c1',
    ]);
    $this->assertDatabaseMissing('lead_marketing_data', [
        'lead_id' => $skip->id,
        'key'     => 'campaign_id',
    ]);
});
