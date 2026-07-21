<?php

namespace App\Console\Commands;

use App\Jobs\GenerateLeadAiSummaryJob;
use App\Services\Ai\LeadAiSummaryService;
use Illuminate\Console\Command;
use Throwable;
use Webkul\Lead\Models\Lead;

class RefreshLeadAiSummaries extends Command
{
    protected $signature = 'leads:refresh-ai-summaries
        {--lead=* : Alleen deze lead ID(s) verversen (sla open-stage filter over)}
        {--sync : Direct genereren i.p.v. op de queue zetten}';

    protected $description = 'Queue a daily AI summary refresh for every open lead (of geforceerd voor specifieke lead IDs)';

    public function handle(LeadAiSummaryService $summaryService): int
    {
        if (! config('services.llm.lead_summary.enabled', true)) {
            $this->components->info('Lead AI summaries are disabled.');

            return self::SUCCESS;
        }

        $leadIds = collect($this->option('lead'))
            ->flatMap(fn (string $value) => preg_split('/\s*,\s*/', trim($value)) ?: [])
            ->map(fn (string $value) => (int) $value)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $sync = (bool) $this->option('sync');
        $queue = (string) config('services.llm.lead_summary.scheduled_queue', 'lead-ai-summary-scheduled');
        $trigger = $leadIds->isNotEmpty() ? 'manual_refresh' : 'daily_refresh';

        $query = Lead::query()->select('id');

        if ($leadIds->isNotEmpty()) {
            $query->whereIn('id', $leadIds);
        } else {
            $query->inOpenStage();
        }

        $processed = 0;
        $failed = 0;

        $query->chunkById(200, function ($leads) use (
            &$processed,
            &$failed,
            $sync,
            $queue,
            $trigger,
            $summaryService,
        ) {
            foreach ($leads as $lead) {
                if ($sync) {
                    $this->components->info("Genereren AI-summary voor lead {$lead->id}...");

                    try {
                        $summary = $summaryService->generate($lead, $trigger);
                        $processed++;

                        if ($summary->status === 'failed') {
                            $failed++;
                            $this->components->error("Lead {$lead->id} mislukt: ".($summary->last_error ?: 'onbekende fout'));
                        } else {
                            $this->components->info("Lead {$lead->id}: {$summary->status}");
                        }
                    } catch (Throwable $exception) {
                        $failed++;
                        $this->components->error("Lead {$lead->id} exception: ".$exception->getMessage());
                    }

                    continue;
                }

                GenerateLeadAiSummaryJob::dispatch($lead->id, $trigger)
                    ->onQueue($queue);
                $processed++;
            }
        });

        if ($leadIds->isNotEmpty() && $processed === 0) {
            $this->components->warn('Geen leads gevonden voor de opgegeven ID(s): '.$leadIds->implode(', '));

            return self::FAILURE;
        }

        if ($sync) {
            $this->components->info("Klaar: {$processed} lead(s) synchroon verwerkt".($failed > 0 ? ", {$failed} mislukt" : '').'.');

            return $failed > 0 ? self::FAILURE : self::SUCCESS;
        }

        $scope = $leadIds->isNotEmpty() ? 'filtered' : 'open';
        $this->components->info("Queued {$processed} {$scope} lead AI summaries on [{$queue}].");

        return self::SUCCESS;
    }
}
