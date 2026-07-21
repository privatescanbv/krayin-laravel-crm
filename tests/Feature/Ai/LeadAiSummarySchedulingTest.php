<?php

use App\Jobs\GenerateLeadAiSummaryJob;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Queue;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;

test('creating a lead queues its initial ai summary generation', function () {
    Queue::fake();
    config(['services.llm.lead_summary.enabled' => true]);

    $lead = Lead::factory()->create();

    Queue::assertPushed(
        GenerateLeadAiSummaryJob::class,
        fn (GenerateLeadAiSummaryJob $job) => $job->leadId === $lead->id
            && $job->trigger === 'lead_created'
            && $job->queue === null,
    );

    expect($lead->aiSummary)->not->toBeNull()
        ->and($lead->aiSummary->status)->toBe('queued');
});

test('daily refresh queues all open leads and skips won and lost leads', function () {
    Queue::fake();

    $openLead = Lead::factory()->create();
    $pipeline = Pipeline::findOrFail($openLead->lead_pipeline_id);
    $wonStage = Stage::factory()->won()->create(['lead_pipeline_id' => $pipeline->id]);
    $lostStage = Stage::factory()->lost()->create(['lead_pipeline_id' => $pipeline->id]);
    $wonLead = Lead::factory()->create([
        'lead_pipeline_id'       => $pipeline->id,
        'lead_pipeline_stage_id' => $wonStage->id,
    ]);
    $lostLead = Lead::factory()->create([
        'lead_pipeline_id'       => $pipeline->id,
        'lead_pipeline_stage_id' => $lostStage->id,
        'lost_reason'            => 'geen_reden',
    ]);

    config([
        'services.llm.lead_summary.enabled'         => true,
        'services.llm.lead_summary.scheduled_queue' => 'slow-lead-ai',
    ]);

    $this->artisan('leads:refresh-ai-summaries')
        ->expectsOutputToContain('Queued 1 open lead AI summaries on [slow-lead-ai].')
        ->assertSuccessful();

    Queue::assertPushed(
        GenerateLeadAiSummaryJob::class,
        fn (GenerateLeadAiSummaryJob $job) => $job->leadId === $openLead->id
            && $job->trigger === 'daily_refresh'
            && $job->queue === 'slow-lead-ai',
    );
    Queue::assertNotPushed(
        GenerateLeadAiSummaryJob::class,
        fn (GenerateLeadAiSummaryJob $job) => in_array($job->leadId, [$wonLead->id, $lostLead->id], true),
    );
});

test('the lead summary refresh command is scheduled once per day', function () {
    $this->artisan('schedule:list', ['--json' => true])
        ->expectsOutputToContain('"expression":"0 0 * * *","command":"php artisan leads:refresh-ai-summaries"')
        ->assertSuccessful();
});

test('lead summary jobs are unique per lead', function () {
    $job = new GenerateLeadAiSummaryJob(42);

    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe('42');
});
