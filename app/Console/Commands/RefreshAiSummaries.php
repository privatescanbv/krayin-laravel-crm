<?php

namespace App\Console\Commands;

use App\Jobs\GenerateAiSummaryJob;
use App\Services\Ai\AiSubjectDefinition;
use App\Services\Ai\AiSubjectRegistry;
use App\Services\Ai\AiSummaryService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Scheduled refresh of AI summaries.
 *
 * Without --subject it only covers subjects that are not generated lazily on view
 * (see generate_on_view in config/ai_summaries.php), and within those only records
 * that are still in play, so the daily run does not burn tokens on closed work.
 */
class RefreshAiSummaries extends Command
{
    protected $signature = 'ai:refresh-summaries
        {--subject=* : Alleen deze subjecten verversen (leads, persons, orders, sales_leads)}
        {--id=* : Alleen deze record ID(s) verversen (vereist precies één --subject)}
        {--sync : Direct genereren i.p.v. op de queue zetten}';

    protected $description = 'Ververs AI-samenvattingen van de geregistreerde subjecten (standaard alleen open records)';

    public function handle(AiSubjectRegistry $registry, AiSummaryService $summaryService): int
    {
        if (! $registry->enabled()) {
            $this->components->info('AI summaries are disabled.');

            return self::SUCCESS;
        }

        $requestedSubjects = $this->flattenOption('subject');
        $unknown = $requestedSubjects->reject(fn (string $key) => $registry->has($key));

        if ($unknown->isNotEmpty()) {
            $this->components->error(
                'Onbekend subject: '.$unknown->implode(', ').'. Bekend: '.implode(', ', $registry->keys())
            );

            return self::FAILURE;
        }

        $ids = $this->flattenOption('id')
            ->map(fn (string $value) => (int) $value)
            ->filter(fn (int $id) => $id > 0)
            ->values();

        $definitions = collect($registry->all())
            ->filter(fn (AiSubjectDefinition $definition) => $registry->isEnabled($definition))
            ->filter(fn (AiSubjectDefinition $definition) => $requestedSubjects->isEmpty()
                ? ! $definition->generateOnView
                : $requestedSubjects->contains($definition->key))
            ->values();

        if ($definitions->isEmpty()) {
            $this->components->info('Geen subjecten om te verversen.');

            return self::SUCCESS;
        }

        if ($ids->isNotEmpty() && $definitions->count() !== 1) {
            $this->components->error('Gebruik --id samen met precies één --subject.');

            return self::FAILURE;
        }

        $sync = (bool) $this->option('sync');
        $queue = (string) config('ai_summaries.scheduled_queue', 'ai-summary-scheduled');
        $trigger = $ids->isNotEmpty() ? 'manual_refresh' : 'daily_refresh';

        $processed = 0;
        $failed = 0;

        foreach ($definitions as $definition) {
            [$subjectProcessed, $subjectFailed] = $this->refreshSubject(
                $definition,
                $ids,
                $sync,
                $queue,
                $trigger,
                $summaryService,
            );

            $processed += $subjectProcessed;
            $failed += $subjectFailed;
        }

        if ($ids->isNotEmpty() && $processed === 0) {
            $this->components->warn('Geen records gevonden voor de opgegeven ID(s): '.$ids->implode(', '));

            return self::FAILURE;
        }

        if ($sync) {
            $this->components->info(
                "Klaar: {$processed} record(s) synchroon verwerkt".($failed > 0 ? ", {$failed} mislukt" : '').'.'
            );

            return $failed > 0 ? self::FAILURE : self::SUCCESS;
        }

        $scope = $ids->isNotEmpty() ? 'filtered' : 'open';
        $this->components->info("Queued {$processed} {$scope} AI summaries on [{$queue}].");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return array{0: int, 1: int}
     */
    private function refreshSubject(
        AiSubjectDefinition $definition,
        Collection $ids,
        bool $sync,
        string $queue,
        string $trigger,
        AiSummaryService $summaryService,
    ): array {
        /** @var Model $prototype */
        $prototype = new $definition->modelClass;
        $query = $prototype->newQuery()->select($prototype->getQualifiedKeyName());

        if ($ids->isNotEmpty()) {
            $query->whereIn($prototype->getKeyName(), $ids);
        } elseif (method_exists($prototype, 'scopeInOpenStage')) {
            // Closed and lost records no longer move; refreshing them burns tokens.
            $query->inOpenStage();
        }

        $processed = 0;
        $failed = 0;

        $query->chunkById(200, function (EloquentCollection $records) use (
            &$processed,
            &$failed,
            $definition,
            $sync,
            $queue,
            $trigger,
            $summaryService,
        ) {
            foreach ($records as $record) {
                if (! $sync) {
                    GenerateAiSummaryJob::dispatch($definition->key, (int) $record->getKey(), $trigger)->onQueue($queue);
                    $processed++;

                    continue;
                }

                $this->components->info("Genereren AI-summary voor {$definition->key} {$record->getKey()}...");

                try {
                    $summary = $summaryService->generate($record, $trigger);
                    $processed++;

                    if ($summary->status === 'failed') {
                        $failed++;
                        $this->components->error(
                            "{$definition->key} {$record->getKey()} mislukt: ".($summary->last_error ?: 'onbekende fout')
                        );
                    } else {
                        $this->components->info("{$definition->key} {$record->getKey()}: {$summary->status}");
                    }
                } catch (Throwable $exception) {
                    $failed++;
                    $this->components->error("{$definition->key} {$record->getKey()} exception: ".$exception->getMessage());
                }
            }
        });

        return [$processed, $failed];
    }

    /**
     * @return Collection<int, string>
     */
    private function flattenOption(string $name): Collection
    {
        return collect($this->option($name))
            ->flatMap(fn (string $value) => preg_split('/\s*,\s*/', trim($value)) ?: [])
            ->filter()
            ->unique()
            ->values();
    }
}
