<?php

namespace App\Jobs;

use App\Enums\AfbDispatchType;
use App\Services\Afb\AfbDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
        public int $clinicId,
        public array $orderIds,
        public string $type
    ) {}

    public function handle(AfbDispatchService $afbDispatchService): void
    {
        $afbDispatchService->sendDispatch(
            clinicId: $this->clinicId,
            orderIds: $this->orderIds,
            type: AfbDispatchType::from($this->type),
            attempt: $this->attempts(),
        );
    }
}
