<?php

namespace App\Jobs;

use App\Models\LeadAiSummary;
use App\Services\Ai\LeadAiSummaryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;
use Webkul\Lead\Models\Lead;

class GenerateLeadAiSummaryJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 240;

    // Connection failures (e.g. the LLM host is temporarily unreachable) are
    // rethrown by the service so the job fails and gets retried here; keep
    // retrying for a while since recovery (e.g. an IP whitelist change) can
    // take longer than a normal backoff window.
    public int $uniqueFor = 7200;

    public function __construct(
        public readonly int $leadId,
        public readonly string $trigger = 'automatic',
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->leadId;
    }

    /**
     * Stop retrying after this window (covers slow-to-propagate fixes, e.g. an IP whitelist).
     */
    public function retryUntil(): Carbon
    {
        return now()->addHours(2);
    }

    /**
     * Backoff in seconds between retries: 1m, 5m, then every 15m until retryUntil().
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(LeadAiSummaryService $service): void
    {
        if (! config('services.llm.lead_summary.enabled', true)) {
            return;
        }

        $lead = Lead::find($this->leadId);

        if (! $lead) {
            return;
        }

        try {
            $summary = $service->generate($lead, $this->trigger);
        } catch (ConnectionException $exception) {
            // The service already recorded this attempt as 'failed'. While the queue
            // still has retries left, reflect that here so a manual "vernieuwen"
            // click sees 'retrying' instead of a misleadingly final 'failed'.
            LeadAiSummary::where('lead_id', $this->leadId)->update(['status' => 'retrying']);

            throw $exception;
        }

        if ($summary->status === 'failed') {
            Log::warning('Lead AI summary job completed with a generation failure', [
                'lead_id' => $this->leadId,
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        // Called once the queue gives up for good (retries exhausted or retryUntil() passed).
        LeadAiSummary::where('lead_id', $this->leadId)->update(['status' => 'failed']);

        Log::error('Lead AI summary job permanently failed', [
            'lead_id'         => $this->leadId,
            'exception_class' => $exception::class,
            'error'           => $exception->getMessage(),
        ]);
    }
}
