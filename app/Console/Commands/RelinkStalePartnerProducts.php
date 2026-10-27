<?php

namespace App\Console\Commands;

use App\Models\OrderItem;
use App\Services\OrderItemPurchasePriceService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class RelinkStalePartnerProducts extends Command
{
    protected $signature = 'orders:relink-stale-partner-products
                            {--dry-run : Toon voorgestelde wijzigingen zonder ze op te slaan}';

    protected $description = 'Herkoppel partner product van orderregels waarvan het product is gewijzigd na inplannen';

    public function handle(OrderItemPurchasePriceService $purchasePriceService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Partner product belongs to another product than the order item: product was changed after planning.
        $items = OrderItem::query()
            ->whereHas('partnerProduct', fn (Builder $q) => $q->withTrashed()->whereColumn('partner_products.product_id', '!=', 'order_items.product_id'))
            ->with(['order', 'partnerProduct' => fn ($q) => $q->withTrashed()])
            ->orderBy('id')
            ->get();

        if ($items->isEmpty()) {
            $this->info('Geen orderregels met verkeerd gekoppeld partner product gevonden.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($items as $item) {
            $oldName = $item->partnerProduct?->name ?? '—';

            if (! $dryRun) {
                $purchasePriceService->relinkPartnerProduct($item);
                $item->refresh();
            }

            $rows[] = [
                $item->order?->order_number ?? '—',
                $item->id,
                $oldName,
                $dryRun ? '(dry-run)' : $item->getProductName(),
            ];
        }

        $this->table(['Ordernummer', 'Order item ID', 'Oud partner product', 'Nieuwe naam'], $rows);
        $this->info(($dryRun ? 'Dry-run: ' : '').count($rows).' orderregel(s) '.($dryRun ? 'zouden worden' : 'zijn').' herkoppeld.');

        return self::SUCCESS;
    }
}
