<?php

namespace App\Console\Commands;

use App\Jobs\GenerateLeadAiSummaryJob;
use Illuminate\Console\Command;
use Webkul\Lead\Models\Lead;

class RefreshLeadAiSummaries extends Command
{
    protected $signature = 'leads:refresh-ai-summaries';

    protected $description = 'Queue a daily AI summary refresh for every open lead';

    public function handle(): int
    {
        if (! config('services.llm.lead_summary.enabled', true)) {
            $this->components->info('Lead AI summaries are disabled.');

            return self::SUCCESS;
        }

        $queued = 0;
        $queue = (string) config('services.llm.lead_summary.scheduled_queue', 'lead-ai-summary-scheduled');

        Lead::query()
            ->inOpenStage()
            ->select('id')
            ->chunkById(200, function ($leads) use (&$queued, $queue) {
                foreach ($leads as $lead) {
                    GenerateLeadAiSummaryJob::dispatch($lead->id, 'daily_refresh')
                        ->onQueue($queue);
                    $queued++;
                }
            });

        $this->components->info("Queued {$queued} open lead AI summaries on [{$queue}].");

        return self::SUCCESS;
    }
}
