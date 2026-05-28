<?php

namespace App\Console\Commands;

use App\Services\Afb\AfbDispatchService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendAfbDailyBatch extends Command
{
    protected $signature = 'afb:send-daily
                            {--date= : Optional date (Y-m-d), defaults to tomorrow}
                            {--dry-run : Preview only: no jobs, e-mail or database writes}';

    protected $description = 'Queue AFB batch verzendingen per kliniek voor onderzoeken op T-1.';

    public function __construct(
        private readonly AfbDispatchService $afbDispatchService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dateOption = $this->option('date');
        $date = $dateOption
            ? Carbon::createFromFormat('Y-m-d', (string) $dateOption)->startOfDay()
            : now()->addDay()->startOfDay();

        if ($this->option('dry-run')) {
            return $this->runDryRun($date);
        }

        try {
            $queued = $this->afbDispatchService->queueDailyBatchDispatches($date);

            $message = sprintf(
                'AFB daily batch queued for %s (%d clinic job%s).',
                $date->toDateString(),
                $queued,
                $queued === 1 ? '' : 's'
            );

            $this->info($message);
            Log::info($message, ['date' => $date->toDateString(), 'queued' => $queued]);
        } catch (Throwable $e) {
            Log::error('AFB daily batch queueing failed', [
                'date'  => $date->toDateString(),
                'error' => $e->getMessage(),
            ]);

            $this->error('AFB daily batch queueing failed: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function runDryRun(Carbon $date): int
    {
        $preview = $this->afbDispatchService->previewDailyBatchDispatches($date);

        $this->warn('DRY-RUN: er worden geen jobs, e-mails of database-records aangemaakt.');
        $this->newLine();

        $this->info(sprintf(
            'Onderzoeksdatum: %s | Batch-jobs die zouden worden gequeued: %d',
            $preview['date'],
            $preview['job_count']
        ));

        if ($preview['batches'] === []) {
            $this->line('Geen geplande onderzoeken gevonden voor deze datum (met AFB-toegestane orderstatus).');

            return self::SUCCESS;
        }

        foreach ($preview['batches'] as $batch) {
            $this->newLine();
            $this->line(sprintf(
                '<fg=cyan>Kliniek:</> %s — <fg=cyan>Afdeling:</> %s (ID %d)',
                $batch['clinic_name'],
                $batch['department_name'],
                $batch['department_id']
            ));
            $this->line(sprintf(
                'E-mail afdeling: %s | Planningsslots op datum: %d | Orders in job-payload: %d',
                $batch['department_email'] ?? '(geen e-mail)',
                $batch['resource_slot_count'],
                count($batch['queued_order_ids'])
            ));

            if ($batch['would_send'] !== []) {
                $this->table(
                    ['Order ID', 'Ordernr.', 'Status', 'Actie'],
                    array_map(fn (array $row) => [
                        $row['order_id'],
                        $row['order_number'] ?? '—',
                        $row['stage'] ?? '—',
                        'Zou AFB-mail + documenten versturen',
                    ], $batch['would_send'])
                );
            } else {
                $this->line('<fg=yellow>Geen orders zouden daadwerkelijk gemaild worden (job draait wel, lege dispatch).</>');
            }

            if ($batch['skipped'] !== []) {
                $this->line('<fg=yellow>Overgeslagen in job (payload wel aanwezig):</>');
                $this->table(
                    ['Order ID', 'Ordernr.', 'Status', 'Reden'],
                    array_map(fn (array $row) => [
                        $row['order_id'],
                        $row['order_number'] ?? '—',
                        $row['stage'] ?? '—',
                        $row['reason'],
                    ], $batch['skipped'])
                );
            }
        }

        $wouldEmailCount = array_sum(array_map(
            fn (array $batch) => count($batch['would_send']),
            $preview['batches']
        ));

        $this->newLine();
        $this->info(sprintf(
            'Samenvatting: %d job(s), %d order(s) zouden een AFB-e-mail ontvangen.',
            $preview['job_count'],
            $wouldEmailCount
        ));

        return self::SUCCESS;
    }
}
