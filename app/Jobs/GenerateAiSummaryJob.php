<?php

namespace App\Jobs;

use App\Models\AiSummary;
use App\Services\Ai\AiSubjectRegistry;
use App\Services\Ai\AiSummaryService;
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

class GenerateAiSummaryJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 240;

    // Connection failures (e.g. the LLM host is temporarily unreachable) are
    // rethrown by the service so the job fails and gets retried here; keep
    // retrying for a while since recovery (e.g. an IP whitelist change) can
    // take longer than a normal backoff window.
    public int $uniqueFor = 7200;

    public function __construct(
        public readonly string $subjectKey,
        public readonly int $subjectId,
        public readonly string $trigger = 'automatic',
    ) {}

    public function uniqueId(): string
    {
        return $this->subjectKey.':'.$this->subjectId;
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

    public function handle(AiSummaryService $service, AiSubjectRegistry $registry): void
    {
        $definition = $registry->find($this->subjectKey);

        if (! $definition || ! $registry->isEnabled($definition)) {
            return;
        }

        $subject = $definition->find($this->subjectId);

        if (! $subject) {
            return;
        }

        try {
            $summary = $service->generate($subject, $this->trigger);
        } catch (ConnectionException $exception) {
            // The service already recorded this attempt as 'failed'. While the queue
            // still has retries left, reflect that here so a manual "vernieuwen"
            // click sees 'retrying' instead of a misleadingly final 'failed'.
            $this->updateStatus('retrying', $definition->morphClass());

            throw $exception;
        }

        if ($summary->status === 'failed') {
            Log::warning('AI summary job completed with a generation failure', [
                'subject_type' => $this->subjectKey,
                'subject_id'   => $this->subjectId,
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        // Called once the queue gives up for good (retries exhausted or retryUntil() passed).
        $morphClass = app(AiSubjectRegistry::class)->find($this->subjectKey)?->morphClass();

        if ($morphClass) {
            $this->updateStatus('failed', $morphClass);
        }

        Log::error('AI summary job permanently failed', [
            'subject_type'    => $this->subjectKey,
            'subject_id'      => $this->subjectId,
            'exception_class' => $exception::class,
            'error'           => $exception->getMessage(),
        ]);
    }

    private function updateStatus(string $status, string $morphClass): void
    {
        AiSummary::query()
            ->where('subject_type', $morphClass)
            ->where('subject_id', $this->subjectId)
            ->update(['status' => $status]);
    }
}
