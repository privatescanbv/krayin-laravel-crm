<?php

namespace App\Services\Inkoop;

use Illuminate\Support\Facades\Log;
use Throwable;
use Webkul\Activity\Repositories\ActivityRepository;

/**
 * Writes a system activity ("wijzigingslogboek") on the order whenever an
 * afletter-mutation happens, so we can see afterwards who changed what, when
 * and from which screen. One activity per order per save-action; the caller
 * pre-formats the human-readable change lines.
 */
class AfletterenAuditLogger
{
    public const SCREEN_INKOOP_STEP2 = 'Inkoop – stap 2';

    public const SCREEN_ORDER_AFLETTEREN = 'Order – Afletteren-tab';

    public const SCREEN_ORDERITEM_EDIT = 'Orderregel – bewerken';

    public function __construct(
        private readonly ActivityRepository $activityRepository,
    ) {}

    /**
     * @param  array{unlinked?: list<string>, forced?: list<string>, unforced?: list<string>, price_changes?: list<string>}  $changes
     * @param  array{invoice_id?: int, invoice_number?: string|null, clinic?: string|null}  $meta
     */
    public function log(int $orderId, string $screen, array $changes, array $meta = []): void
    {
        $changes = array_filter($changes, fn ($lines) => ! empty($lines));

        if ($changes === []) {
            return;
        }

        try {
            $this->activityRepository->createSystem([
                'title'      => 'Afletteren bijgewerkt',
                'comment'    => $this->summary($changes, $meta),
                'order_id'   => $orderId,
                'user_id'    => auth()->guard('user')->id(),
                'additional' => array_merge(
                    ['afletteren' => true, 'screen' => $screen],
                    array_filter($meta, fn ($v) => $v !== null),
                    $changes,
                ),
            ]);
        } catch (Throwable $e) {
            Log::error('Kon afletter-audit activiteit niet wegschrijven: '.$e->getMessage(), [
                'order_id' => $orderId,
                'screen'   => $screen,
            ]);
        }
    }

    /**
     * @param  array<string, list<string>>  $changes
     * @param  array{invoice_id?: int, invoice_number?: string|null, clinic?: string|null}  $meta
     */
    private function summary(array $changes, array $meta): string
    {
        $labels = [
            'unlinked'      => 'losgekoppeld',
            'forced'        => 'geforceerd als geheel ontvangen',
            'unforced'      => 'forcering verwijderd',
            'price_changes' => 'factuurprijs gewijzigd',
        ];

        $parts = [];
        foreach ($labels as $key => $label) {
            if (! empty($changes[$key])) {
                $parts[] = count($changes[$key]).' '.$label;
            }
        }

        $prefix = '';
        if (! empty($meta['invoice_number'])) {
            $prefix = 'Factuur '.$meta['invoice_number'];
            if (! empty($meta['clinic'])) {
                $prefix .= ' ('.$meta['clinic'].')';
            }
            $prefix .= ': ';
        }

        $detail = implode('; ', array_merge(...array_values($changes)));

        return $prefix.implode(', ', $parts).'. — '.$detail;
    }
}
