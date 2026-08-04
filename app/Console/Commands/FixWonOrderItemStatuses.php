<?php

namespace App\Console\Commands;

use App\Enums\OrderItemStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FixWonOrderItemStatuses extends Command
{
    private const CHUNK_SIZE = 100;

    protected $signature = 'orders:fix-won-item-statuses
                            {--dry-run : Toon voorgestelde wijzigingen zonder ze op te slaan}
                            {--order-id=* : Beperk tot specifieke order IDs}';

    protected $description = 'Zet orderregels van gewonnen orders die nog niet won/lost zijn op won';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->components->info(sprintf(
            'Zoeken naar gewonnen orders met orderregels die nog niet won of lost zijn%s...',
            $dryRun ? ' (dry-run)' : ''
        ));

        $query = $this->wonOrdersWithIncorrectItemsQuery();
        $orderCount = (clone $query)->count();

        if ($orderCount === 0) {
            $this->info('Geen gewonnen orders gevonden met te corrigeren orderregels.');

            return self::SUCCESS;
        }

        $this->info("Gevonden {$orderCount} gewonnen order(s) met te corrigeren orderregels.");

        $tableRows = [];
        $updatedItemCount = 0;

        $bar = $this->output->createProgressBar($orderCount);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();

        $query->with([
            'stage',
            'orderItems' => function ($q) {
                $q->whereNotIn('status', [
                    OrderItemStatus::WON->value,
                    OrderItemStatus::LOST->value,
                ]);
            },
        ])->chunkById(self::CHUNK_SIZE, function (Collection $orders) use (&$tableRows, &$updatedItemCount, $dryRun, $bar) {
            foreach ($orders as $order) {
                /** @var Order $order */
                $items = $order->orderItems;

                if ($items->isEmpty()) {
                    $bar->advance();

                    continue;
                }

                $orderStatus = $order->stage?->name ?? '—';

                foreach ($items as $item) {
                    /** @var OrderItem $item */
                    $tableRows[] = [
                        $order->id,
                        $order->order_number ?? '—',
                        $orderStatus,
                        $item->id,
                        $item->status?->value ?? 'null',
                        OrderItemStatus::WON->value,
                    ];
                }

                $itemIds = $items->pluck('id');

                if (! $dryRun) {
                    OrderItem::query()
                        ->whereIn('id', $itemIds)
                        ->update(['status' => OrderItemStatus::WON->value]);
                }

                $updatedItemCount += $itemIds->count();
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        if ($tableRows === []) {
            $this->info('Geen orderregels gevonden om te corrigeren.');

            return self::SUCCESS;
        }

        $this->table(
            ['Order ID', 'Ordernummer', 'Order status', 'Order item ID', 'Huidige status', 'Nieuwe status'],
            $tableRows,
        );

        if ($dryRun) {
            $this->info("Dry-run: {$updatedItemCount} orderregel(s) zouden naar won worden gezet.");
            $this->info('Voer uit zonder --dry-run om deze wijzigingen door te voeren.');
        } else {
            $this->info("{$updatedItemCount} orderregel(s) bijgewerkt naar won.");
        }

        return self::SUCCESS;
    }

    /**
     * Won orders that still have order items whose status is neither won nor lost.
     *
     * @return Builder<Order>
     */
    private function wonOrdersWithIncorrectItemsQuery(): Builder
    {
        $query = Order::query()
            ->whereHas('stage', fn (Builder $stage) => $stage->where('is_won', true))
            ->whereHas('orderItems', function (Builder $items) {
                $items->whereNotIn('status', [
                    OrderItemStatus::WON->value,
                    OrderItemStatus::LOST->value,
                ]);
            })
            ->orderBy('id');

        $orderIds = array_values(array_filter(
            array_map('intval', (array) $this->option('order-id')),
            fn (int $id) => $id > 0,
        ));

        if ($orderIds !== []) {
            $query->whereIn('id', $orderIds);
        }

        return $query;
    }
}
