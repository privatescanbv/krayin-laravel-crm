<?php

namespace App\Console\Commands;

use App\Enums\PurchasePriceType;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;

class ForceReceivedImportedOrders extends Command
{
    protected $signature = 'orders:force-received-imported
                            {--until=2026-03-31 : Orders aangemaakt op of vóór deze datum (Y-m-d)}
                            {--order-nums=* : Specifieke ordernummers (alleen geïmporteerde orders)}
                            {--apply : Zet force_received op invoice purchase prices (standaard: alleen rapport)}';

    protected $description = 'Zet force_received op afletteren-prijzen van geïmporteerde orders (preview standaard)';

    public function handle(): int
    {
        $until = $this->parseUntilDate((string) $this->option('until'));
        if ($until === null) {
            $this->error('Ongeldige --until datum. Gebruik formaat Y-m-d (bijv. 2026-03-31).');

            return self::FAILURE;
        }

        $orderNums = $this->normalizeOrderNums($this->option('order-nums'));
        $apply = (bool) $this->option('apply');

        $this->info('Controleren geïmporteerde orders voor force_received op afletteren-prijzen...');
        $this->infoV('Tot datum: '.$until->toDateString().' (einde dag)');
        if ($orderNums !== []) {
            $this->infoV('Ordernummers: '.implode(', ', $orderNums));
        }
        $this->infoV('Modus: '.($apply ? 'toepassen (--apply)' : 'rapport (standaard)'));

        $orders = $this->loadOrders($until, $orderNums);
        if ($orders->isEmpty()) {
            $this->info('Geen geïmporteerde orders gevonden binnen de opgegeven criteria.');

            return self::SUCCESS;
        }

        $this->info('Gevonden '.$orders->count().' order(s) om te controleren.');

        $tableRows = [];
        $toUpdate = [];

        foreach ($orders as $order) {
            $order->loadMissing('orderItems.invoicePurchasePrice');

            foreach ($order->orderItems as $orderItem) {
                if ($this->invoicePurchasePriceIsForceReceived($orderItem)) {
                    continue;
                }

                $tableRows[] = $this->formatPreviewRow($order, $orderItem);
                $toUpdate[] = $orderItem;
            }
        }

        if ($tableRows === []) {
            $this->info('Geen orderregels gevonden die force_received nodig hebben.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            [
                'Order#',
                'Order ID',
                'Item ID',
                'Regel',
                'Aangemaakt',
                'force_received huidig',
                'Actie',
            ],
            $tableRows,
        );

        $this->warn(count($tableRows).' orderregel(s) krijgen force_received=true op de afletteren-prijs.');

        if (! $apply) {
            $this->line('');
            $this->info('Voer uit met --apply om deze wijzigingen door te voeren.');

            return self::SUCCESS;
        }

        $updated = 0;

        foreach ($toUpdate as $orderItem) {
            $orderItem->invoicePurchasePrice()->updateOrCreate(
                ['type' => PurchasePriceType::INVOICE],
                ['force_received' => true],
            );
            $updated++;
        }

        $this->info("✓ {$updated} orderregel(s) bijgewerkt.");

        return self::SUCCESS;
    }

    private function parseUntilDate(string $raw): ?Carbon
    {
        try {
            return Carbon::parse($raw)->endOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  list<string|int>  $raw
     * @return list<string>
     */
    private function normalizeOrderNums(mixed $raw): array
    {
        if ($raw === null || $raw === [] || $raw === '') {
            return [];
        }

        if (is_string($raw)) {
            $raw = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        return collect($raw)
            ->flatMap(fn ($value) => preg_split('/[\s,]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $orderNums
     */
    private function loadOrders(Carbon $until, array $orderNums): Collection
    {
        $query = Order::query()
            ->whereNotNull('external_id')
            ->where('external_id', '!=', '')
            ->where('created_at', '<=', $until)
            ->orderByDesc('id');

        if ($orderNums !== []) {
            $query->whereIn('order_number', $orderNums);
        }

        return $query->get();
    }

    private function invoicePurchasePriceIsForceReceived(OrderItem $orderItem): bool
    {
        return (bool) ($orderItem->invoicePurchasePrice?->force_received ?? false);
    }

    /**
     * @return list<string>
     */
    private function formatPreviewRow(Order $order, OrderItem $orderItem): array
    {
        return [
            $order->order_number ?? '—',
            (string) $order->id,
            (string) $orderItem->id,
            $orderItem->name ?? '—',
            $order->created_at?->format('Y-m-d') ?? '—',
            'nee',
            'force_received → ja',
        ];
    }

    private function infoV(string $message): void
    {
        if ($this->output->isVerbose()) {
            $this->info($message);
        }
    }
}
