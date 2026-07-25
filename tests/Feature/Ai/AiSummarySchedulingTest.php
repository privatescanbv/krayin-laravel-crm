<?php

use App\Jobs\GenerateAiSummaryJob;
use App\Models\Order;
use App\Models\SalesLead;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Queue;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;

test('creating a lead queues its initial ai summary generation', function () {
    Queue::fake();
    config(['ai_summaries.enabled' => true]);

    $lead = Lead::factory()->create();

    Queue::assertPushed(
        GenerateAiSummaryJob::class,
        fn (GenerateAiSummaryJob $job) => $job->subjectKey === 'leads'
            && $job->subjectId === $lead->id
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
        'ai_summaries.enabled'         => true,
        'ai_summaries.scheduled_queue' => 'slow-ai',
    ]);

    $this->artisan('ai:refresh-summaries')
        ->expectsOutputToContain('Queued 1 open AI summaries on [slow-ai].')
        ->assertSuccessful();

    Queue::assertPushed(
        GenerateAiSummaryJob::class,
        fn (GenerateAiSummaryJob $job) => $job->subjectId === $openLead->id
            && $job->subjectKey === 'leads'
            && $job->trigger === 'daily_refresh'
            && $job->queue === 'slow-ai',
    );
    Queue::assertNotPushed(
        GenerateAiSummaryJob::class,
        fn (GenerateAiSummaryJob $job) => in_array($job->subjectId, [$wonLead->id, $lostLead->id], true)
            && $job->trigger === 'daily_refresh',
    );
});

test('the daily refresh skips subjects that are generated lazily on view', function () {
    Queue::fake();

    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    Order::factory()->create(['sales_lead_id' => $salesLead->id]);

    config(['ai_summaries.enabled' => true]);

    $this->artisan('ai:refresh-summaries')->assertSuccessful();

    Queue::assertNotPushed(
        GenerateAiSummaryJob::class,
        fn (GenerateAiSummaryJob $job) => in_array($job->subjectKey, ['orders', 'sales_leads', 'persons'], true),
    );
});

test('refresh command can target a specific record id including closed leads', function () {
    Queue::fake();

    $openLead = Lead::factory()->create();
    $pipeline = Pipeline::findOrFail($openLead->lead_pipeline_id);
    $wonStage = Stage::factory()->won()->create(['lead_pipeline_id' => $pipeline->id]);
    $wonLead = Lead::factory()->create([
        'lead_pipeline_id'       => $pipeline->id,
        'lead_pipeline_stage_id' => $wonStage->id,
    ]);

    config([
        'ai_summaries.enabled'         => true,
        'ai_summaries.scheduled_queue' => 'slow-ai',
    ]);

    $this->artisan('ai:refresh-summaries', [
        '--subject' => ['leads'],
        '--id'      => [(string) $wonLead->id],
    ])
        ->expectsOutputToContain('Queued 1 filtered AI summaries on [slow-ai].')
        ->assertSuccessful();

    Queue::assertPushed(
        GenerateAiSummaryJob::class,
        fn (GenerateAiSummaryJob $job) => $job->subjectId === $wonLead->id
            && $job->trigger === 'manual_refresh'
            && $job->queue === 'slow-ai',
    );
    Queue::assertNotPushed(
        GenerateAiSummaryJob::class,
        fn (GenerateAiSummaryJob $job) => $job->subjectId === $openLead->id && $job->trigger === 'manual_refresh',
    );
});

test('the refresh command rejects an unknown subject', function () {
    config(['ai_summaries.enabled' => true]);

    $this->artisan('ai:refresh-summaries', ['--subject' => ['invoices']])
        ->expectsOutputToContain('Onbekend subject: invoices')
        ->assertFailed();
});

test('the ai summary refresh command is scheduled once per day', function () {
    $this->artisan('schedule:list', ['--json' => true])
        ->expectsOutputToContain('"expression":"0 0 * * *","command":"php artisan ai:refresh-summaries"')
        ->assertSuccessful();
});

test('ai summary jobs are unique per subject record', function () {
    $job = new GenerateAiSummaryJob('orders', 42);

    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe('orders:42')
        ->and((new GenerateAiSummaryJob('leads', 42))->uniqueId())->toBe('leads:42');
});
