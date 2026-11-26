<?php

namespace App\Jobs;

use App\Enums\AfbDispatchType;
use App\Services\Afb\AfbDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAfbDispatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    /**
     * @param  array<int, int>  $orderIds
     */
    public function __construct(
        public int $departmentId,
        public array $orderIds,
        public string $type
    ) {}

    public function handle(AfbDispatchService $afbDispatchService): void
    {
        if (! config('services.afb.dispatch_enabled')) {
            Log::info('AFB dispatch overgeslagen: verzending staat uit (AFB_DISPATCH_ENABLED)', [
                'clinic_department_id' => $this->departmentId,
                'order_ids'            => $this->orderIds,
            ]);

            return;
        }

        $afbDispatchService->sendDispatch(
            departmentId: $this->departmentId,
            orderIds: $this->orderIds,
            type: AfbDispatchType::from($this->type),
            attempt: $this->attempts(),
        );
    }
}
