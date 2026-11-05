<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

/**
 * Verzamelt de vaste checks van de Health API (contract v1). Alleen lezen, geen invoer van de aanroeper.
 * Een mislukte verbinding is 'critical'; ontbrekend bewijs (niet gemeten) is 'unknown'.
 *
 * ponytail: geen worker-heartbeat- of scheduler-check; de app schrijft die bewijzen niet. Toevoegen
 * zodra queue:work/schedule:run een heartbeat wegschrijft.
 */
class HealthChecker
{
    private array $checks = [];

    private float $deadline = 0;

    /** @return list<array> */
    public function collect(bool $requiredOnly = false): array
    {
        $this->checks = [];
        $this->deadline = hrtime(true) + config('health.total_timeout_ms') * 1e6;

        $dbOk = $this->database();

        if (! $requiredOnly) {
            $this->queues($dbOk);
        }

        return $this->checks;
    }

    public function report(): array
    {
        $start = hrtime(true);
        $checks = $this->collect();

        return [
            'schema_version' => '1',
            'status'         => $this->summary($checks)['status'],
            'checked_at'     => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            'duration_ms'    => (int) round((hrtime(true) - $start) / 1e6),
            'checks'         => $checks,
        ];
    }

    /** @return array{status: string, ready: string, report_http_status: int} */
    public function summary(array $checks): array
    {
        $rank = ['healthy' => 0, 'warning' => 1, 'unknown' => 2, 'critical' => 3];
        $overall = $checks === [] ? 'unknown' : 'healthy';
        $ready = $checks !== [];

        foreach ($checks as $check) {
            $status = $check['status'];

            if ($rank[$status] > $rank[$overall]) {
                $overall = $status;
            }

            if ($check['required'] && in_array($status, ['critical', 'unknown'], true)) {
                $ready = false;
            }
        }

        return [
            'status'             => $overall,
            'ready'              => $ready ? 'pass' : 'fail',
            'report_http_status' => in_array($overall, ['critical', 'unknown'], true) ? 503 : 200,
        ];
    }

    private function database(): bool
    {
        // Eigen verbinding met korte connect-timeout: test echt een verse verbinding en kan niet blijven hangen.
        $base = config('database.connections.'.config('health.connection', config('database.default')));
        config(['database.connections.health' => array_replace($base, [
            'options' => [PDO::ATTR_TIMEOUT => 1] + (array) ($base['options'] ?? []),
        ])]);

        $this->run('database.connectivity', 'database', true, null, null, fn () => DB::connection('health')
            ->select('SELECT /*+ MAX_EXECUTION_TIME(1000) */ 1') !== [], 'critical', 'Database unavailable');

        DB::purge('health');

        return $this->checks[0]['status'] === 'healthy';
    }

    private function queues(bool $dbOk): void
    {
        // Alleen de database-queue heeft hier meetbaar bewijs; sync = geen achtergrondwerk.
        if (config('queue.default') !== 'database') {
            return;
        }

        $table = config('queue.connections.database.table', 'jobs');
        $failedTable = config('queue.failed.table', 'failed_jobs');
        $th = config('health.thresholds');
        $failedTh = ['operator' => '>=', 'warning' => config('failed_jobs.warning_threshold'), 'critical' => config('failed_jobs.critical_threshold')];
        $failed = fn () => DB::connection(config('queue.failed.database'))->table($failedTable);

        // Bewust globaal en zo genoemd: gelijk aan wat queue:monitor-failed-jobs mailt.
        $this->run('queue.failed_total', 'queue', false, 'jobs', $failedTh, fn () => $dbOk ? $failed()->count() : null);

        foreach (config('health.queues') as $queue) {
            $pending = fn () => DB::table($table)->where('queue', $queue)->whereNull('reserved_at')->where('available_at', '<=', time());

            $this->run("queue.$queue.failed", 'queue', false, 'jobs', $failedTh,
                fn () => $dbOk ? $failed()->where('queue', $queue)->count() : null);
            $this->run("queue.$queue.pending", 'queue', false, 'jobs', $th['pending'],
                fn () => $dbOk ? $pending()->count() : null);
            $this->run("queue.$queue.oldest_pending", 'queue', false, 'seconds', $th['oldest_pending_seconds'],
                fn () => $dbOk ? max(0, time() - ($pending()->min('available_at') ?? time())) : null);
        }
    }

    private function run(string $name, string $type, bool $required, ?string $unit, ?array $thresholds, callable $measure, string $failure = 'unknown', string $failureMessage = 'Measurement unavailable'): void
    {
        $start = hrtime(true);
        $value = null;
        $status = 'unknown';
        $message = 'Not measured';

        if ($start <= $this->deadline) {
            try {
                $value = $measure();

                if ($value === false) {
                    throw new \RuntimeException; // mislukte boolean-meting = zelfde pad als een uitzondering
                }

                if ($value !== null) {
                    $status = $thresholds ? $this->level($value, $thresholds) : 'healthy';
                    $message = $status === 'healthy' ? null : 'Threshold exceeded';
                }
            } catch (Throwable) {
                // Nooit de uitzondering teruggeven of loggen: kan DSN/credentials bevatten.
                $value = $failure === 'critical' ? false : null;
                $status = $failure;
                $message = $failureMessage;
            }
        }

        $this->checks[] = [
            'name'           => $name,
            'type'           => $type,
            'required'       => $required,
            'status'         => $status,
            'observed_value' => $value,
            'unit'           => $unit,
            'thresholds'     => $thresholds,
            'duration_ms'    => (int) round((hrtime(true) - $start) / 1e6),
            'checked_at'     => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            'message'        => $message,
        ];
    }

    private function level(int|float $value, array $th): string
    {
        $hit = fn ($bound) => $bound !== null && ($th['operator'] === '>=' ? $value >= $bound : $value <= $bound);

        return $hit($th['critical']) ? 'critical' : ($hit($th['warning']) ? 'warning' : 'healthy');
    }
}
