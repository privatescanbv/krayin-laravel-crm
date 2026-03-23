<?php

namespace App\Console\Commands;

use App\Services\Afb\AfbDispatchService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendAfbDailyBatch extends Command
{
    protected $signature = 'afb:send-daily {--date= : Optional date (Y-m-d), defaults to tomorrow}';

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
}
