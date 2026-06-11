<?php

namespace App\Observers;

use App\Enums\LostReason;
use App\Enums\OrderItemStatus;
use App\Enums\PipelineStage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderNumberGenerator;
use App\Services\OrderStatusService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Contact\Models\Organization;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

class OrderObserver
{
    public function __construct(
        private readonly OrderStatusService $orderStatusService,
        private readonly OrderNumberGenerator $orderNumberGenerator,
        private readonly ActivityRepository $activityRepository,
    ) {}

    public function creating(Order $order): void
    {
        if (! empty($order->order_number)) {
            return;
        }

        $order->order_number = $this->orderNumberGenerator->next();
    }

    public function created(Order $order): void
    {
        Event::dispatch('order.update_stage.after', $order);
    }

    public function deleting(Order $order): bool
    {
        $order->loadMissing(['salesLead.department', 'salesLead.lead.department']);

        $department = $order->salesLead?->getDepartment();
        $lostStageId = Order::lostOrderStageId($department);

        $order->update([
            'pipeline_stage_id' => $lostStageId,
            'closed_at'         => $order->closed_at ?? now(),
        ]);

        return false;
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        if ($order->wasChanged('pipeline_stage_id')) {
            Event::dispatch('order.update_stage.after', $order);
            $stageChange = true;
        } else {
            $newStageId = $this->orderStatusService->recalculateAndPersist($order);
            $stageChange = ! is_null($newStageId);
        }
        if ($stageChange && in_array($order->pipeline_stage_id, PipelineStage::getStageIdsAfterExecutionExLost(), true)) {
            Log::info('Updating order items to WON for order '.$order->id);
            OrderItem::forOrderAndNotLost($order->id)
                ->update(['status' => OrderItemStatus::WON->value]);
        }

        if ($stageChange && in_array($order->pipeline_stage_id, PipelineStage::getLostOrderStageIds(), true)) {
            Log::info('Updating order items to LOST for order '.$order->id);
            $orderItemIds = OrderItem::where('order_id', $order->id)->pluck('id');
            OrderItem::where('order_id', $order->id)
                ->update(['status' => OrderItemStatus::LOST->value]);
            DB::table('resource_orderitem')->whereIn('orderitem_id', $orderItemIds)->delete();
        }

        $this->logFieldChanges($order);
    }

    private function logFieldChanges(Order $order): void
    {
        $fields = [
            'title'                      => 'Titel',
            'pipeline_stage_id'          => 'Status',
            'user_id'                    => 'Toegewezen aan',
            'clinic_coordinator_user_id' => 'Kliniek coördinator',
            'total_price'                => 'Totaalprijs',
            'first_examination_at'       => 'Eerste onderzoeksdatum',
            'lost_reason'                => 'Reden verlies',
            'is_business'                => 'Zakelijk',
            'combine_order'              => 'Combinatieorder',
            'organization_id'            => 'Organisatie',
        ];

        foreach ($fields as $field => $label) {
            if (! $order->wasChanged($field)) {
                continue;
            }

            $oldRaw = $order->getOriginal($field);
            $newRaw = $order->getAttribute($field);

            [$oldLabel, $newLabel] = $this->resolveFieldLabels($field, $oldRaw, $newRaw);

            if (empty($oldLabel) && empty($newLabel)) {
                continue;
            }

            $this->activityRepository->createSystem([
                'title'      => "{$label} gewijzigd",
                'additional' => [
                    'attribute' => $label,
                    'new'       => ['value' => $newRaw, 'label' => $newLabel ?: '-'],
                    'old'       => ['value' => $oldRaw, 'label' => $oldLabel ?: '-'],
                ],
                'user_id'  => auth()->id() ?? 1,
                'order_id' => $order->id,
            ]);
        }
    }

    /**
     * @return array{string|null, string|null}
     */
    private function resolveFieldLabels(string $field, mixed $oldRaw, mixed $newRaw): array
    {
        return match ($field) {
            'pipeline_stage_id' => [
                Stage::find($oldRaw)?->name,
                Stage::find($newRaw)?->name,
            ],
            'user_id', 'clinic_coordinator_user_id' => [
                User::find($oldRaw)?->name,
                User::find($newRaw)?->name,
            ],
            'organization_id' => [
                Organization::find($oldRaw)?->name,
                Organization::find($newRaw)?->name,
            ],
            'total_price' => [
                $oldRaw !== null ? '€ '.number_format((float) $oldRaw, 2, '.', '') : null,
                $newRaw !== null ? '€ '.number_format((float) $newRaw, 2, '.', '') : null,
            ],
            'first_examination_at' => [
                $oldRaw !== null ? Carbon::parse($oldRaw)->format('d-m-Y') : null,
                $newRaw !== null ? Carbon::parse($newRaw)->format('d-m-Y') : null,
            ],
            'lost_reason' => [
                $oldRaw !== null ? (LostReason::tryFrom((string) $oldRaw)?->label() ?? (string) $oldRaw) : null,
                $newRaw instanceof LostReason
                    ? $newRaw->label()
                    : ($newRaw !== null ? (LostReason::tryFrom((string) $newRaw)?->label() ?? (string) $newRaw) : null),
            ],
            'is_business', 'combine_order' => [
                $oldRaw ? 'Ja' : 'Nee',
                $newRaw ? 'Ja' : 'Nee',
            ],
            default => [(string) ($oldRaw ?? ''), (string) ($newRaw ?? '')],
        };
    }
}
