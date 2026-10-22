<?php

use App\Enums\ActivityActionType;
use App\Enums\ActivityType;
use App\Enums\CallStatus as CallStatusEnum;
use App\Enums\LostReason;
use App\Models\ActivityAction;
use App\Models\SalesLead;
use Illuminate\Support\Facades\DB;
use Webkul\Activity\Models\Activity;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;

function contactStage(Lead $lead, bool $lost): Stage
{
    $factory = Stage::factory();

    return ($lost ? $factory->lost() : $factory)->create(['lead_pipeline_id' => $lead->lead_pipeline_id]);
}

function loseLead(Lead $lead): Lead
{
    $lead->update([
        'lead_pipeline_stage_id' => contactStage($lead, lost: true)->id,
        'lost_reason'            => LostReason::NoResponse,
    ]);

    return $lead->refresh();
}

function contactCall(array $attributes, CallStatusEnum $status): void
{
    $call = Activity::create(['type' => ActivityType::CALL->value, 'title' => 'Bellen', ...$attributes]);
    ActivityAction::create(['activity_id' => $call->id, 'type' => ActivityActionType::Belstatus, 'call_status' => $status->value]);
}

function contactEmail(Lead $lead, string $userType): void
{
    Email::create([
        'subject'   => 'Re: afspraak',
        'from'      => ['klant@example.com'],
        'reply'     => '<p>Hallo</p>',
        'folder_id' => Folder::create(['name' => 'inbox'])->id,
        'lead_id'   => $lead->id,
        'user_type' => $userType,
    ]);
}

test('an open lead has no contact outcome', function () {
    expect(Lead::factory()->create()->refresh()->had_contact)->toBeNull();
});

test('a lost lead with only unanswered calls never had contact', function () {
    $lead = Lead::factory()->create()->refresh();
    contactCall(['lead_id' => $lead->id], CallStatusEnum::NOT_REACHABLE);

    expect(loseLead($lead)->had_contact)->toBeFalse();
});

test('a left voicemail counts as contact', function () {
    $lead = Lead::factory()->create()->refresh();
    contactCall(['lead_id' => $lead->id], CallStatusEnum::VOICEMAIL_LEFT);

    expect(loseLead($lead)->had_contact)->toBeTrue();
});

test('a lost lead with a spoken call had contact', function () {
    $lead = Lead::factory()->create()->refresh();
    contactCall(['lead_id' => $lead->id], CallStatusEnum::SPOKEN);

    expect(loseLead($lead)->had_contact)->toBeTrue();
});

test('a spoken call on a sales lead of the lead counts as contact', function () {
    $lead = Lead::factory()->create()->refresh();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    contactCall(['sales_lead_id' => $salesLead->id], CallStatusEnum::SPOKEN);

    expect(loseLead($lead)->had_contact)->toBeTrue();
});

test('a spoken call logged after the lead was lost flips it to had contact', function () {
    $lead = loseLead(Lead::factory()->create()->refresh());
    expect($lead->had_contact)->toBeFalse();

    contactCall(['lead_id' => $lead->id], CallStatusEnum::SPOKEN);

    expect($lead->refresh()->had_contact)->toBeTrue();
});

test('a spoken call on a sales lead logged after losing flips the lead', function () {
    $lead = Lead::factory()->create()->refresh();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    loseLead($lead);

    contactCall(['sales_lead_id' => $salesLead->id], CallStatusEnum::SPOKEN);

    expect($lead->refresh()->had_contact)->toBeTrue();
});

test('removing the only spoken call reverts a lost lead to no contact', function () {
    $lead = Lead::factory()->create()->refresh();
    contactCall(['lead_id' => $lead->id], CallStatusEnum::SPOKEN);
    loseLead($lead);

    ActivityAction::query()->first()->delete();

    expect($lead->refresh()->had_contact)->toBeFalse();
});

test('a call logged on an open lead leaves the contact outcome empty', function () {
    $lead = Lead::factory()->create()->refresh();

    contactCall(['lead_id' => $lead->id], CallStatusEnum::SPOKEN);

    expect($lead->refresh()->had_contact)->toBeNull();
});

test('a person email linked after the lead was lost flips it to had contact', function () {
    $lead = loseLead(Lead::factory()->create()->refresh());

    contactEmail($lead, 'person');

    expect($lead->refresh()->had_contact)->toBeTrue();
});

test('our own email after losing does not count as contact', function () {
    $lead = loseLead(Lead::factory()->create()->refresh());

    contactEmail($lead, 'user');

    expect($lead->refresh()->had_contact)->toBeFalse();
});

test('reopening a lost lead clears the contact outcome', function () {
    $lead = Lead::factory()->create()->refresh();
    loseLead($lead);

    $lead->update(['lead_pipeline_stage_id' => contactStage($lead, lost: false)->id]);

    expect($lead->refresh()->had_contact)->toBeNull();
});

test('backfill records the contact outcome for leads that were already lost', function () {
    $lead = Lead::factory()->create()->refresh();
    contactCall(['lead_id' => $lead->id], CallStatusEnum::SPOKEN);
    loseLead($lead);

    // Simulate a lead lost before this feature existed.
    DB::table('leads')->update(['had_contact' => null]);

    $this->artisan('leads:backfill-customer-type')->assertSuccessful();

    expect($lead->refresh()->had_contact)->toBeTrue();
});
