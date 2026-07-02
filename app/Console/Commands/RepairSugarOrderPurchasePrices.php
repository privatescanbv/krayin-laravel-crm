<?php

namespace App\Console\Commands;

use App\Enums\PurchasePriceType;
use App\Models\Order;
use App\Models\OrderItem;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Webkul\Contact\Models\Person;
use Webkul\Product\Models\Product;

class RepairSugarOrderPurchasePrices extends ImportOrdersFromSugarCRM
{
    protected $signature = 'orders:repair-sugar-purchase-prices
                            {--connection=sugarcrm : Database connection name}
                            {--order-nums=* : Specifieke ordernummers (bijv. 202502011)}
                            {--limit=-1 : Maximum aantal orders om te scannen (alleen zonder --order-nums)}
                            {--apply : Pas correcte inkoopprijzen toe (standaard: alleen rapport)}';

    protected $description = 'Vergelijk geïmporteerde orderregel-inkoopprijzen met SugarCRM en toon of herstel afwijkingen';

    public function handle(): int
    {
        $connection = (string) $this->option('connection');
        $orderNums = $this->normalizeOrderNums($this->option('order-nums'));
        $limit = (int) $this->option('limit');
        $apply = (bool) $this->option('apply');

        $this->info('Controleren inkoopprijzen geïmporteerde orders t.o.v. SugarCRM...');
        $this->infoV("Connection: {$connection}");
        if ($orderNums !== []) {
            $this->infoV('Ordernummers: '.implode(', ', $orderNums));
        } else {
            $this->infoV("Limit: {$limit}");
        }
        $this->infoV('Modus: '.($apply ? 'toepassen (--apply)' : 'rapport (standaard)'));

        try {
            $this->testConnection($connection);
        } catch (Exception $e) {
            $this->error('SugarCRM-verbinding mislukt: '.$e->getMessage());

            return self::FAILURE;
        }

        $orders = $this->loadCrmOrders($orderNums, $limit);
        if ($orders->isEmpty()) {
            $this->info('Geen geïmporteerde orders gevonden om te controleren.');

            return self::SUCCESS;
        }

        // Skip orders without any CRM order items — nothing to repair.
        $orders = $orders->filter(fn (Order $o) => $o->orderItems->isNotEmpty());
        if ($orders->isEmpty()) {
            $this->info('Geen orders met orderregels gevonden om te controleren.');

            return self::SUCCESS;
        }

        $this->info('Gevonden '.$orders->count().' order(s) om te controleren.');

        $sugarOrderIds = $orders->pluck('external_id')->filter()->values()->all();
        $rowsByOrder = $this->fetchOrderRows($connection, $sugarOrderIds);

        // Pre-load persons for all contact_ids found in the Sugar rows.
        $allContactIds = $rowsByOrder->flatten(1)->pluck('contact_id')->filter()->unique()->values()->all();
        $personsByContactId = ! empty($allContactIds)
            ? Person::whereIn('external_id', $allContactIds)->get()->keyBy('external_id')
            : collect();

        $tableRows = [];
        $mismatches = [];
        $applied = 0;
        $skippedNoMatch = 0;

        foreach ($orders as $order) {
            $orderRows = $rowsByOrder->get($order->external_id, collect());
            if ($orderRows->isEmpty()) {
                $this->infoV("Geen Sugar-regels gevonden voor order {$order->order_number} (external_id={$order->external_id}), overgeslagen.");

                continue;
            }

            $productsByName = $this->productsByNameForSugarRows($orderRows);
            $productsByNormalizedName = $productsByName->mapWithKeys(
                fn (Product $p, string $name) => [$this->normalizeProductName($name) => $p]
            );
            $partnerProductsByExternalId = $this->partnerProductsByExternalId();
            $partnerProductsByName = $this->partnerProductsByName();
            $partnerProductsByNormalizedName = $this->partnerProductsByNormalizedName();

            $usedOrderItemIds = [];

            foreach ($orderRows as $sugarRow) {
                $product = $this->resolveProductForSugarRow(
                    $sugarRow,
                    $productsByName,
                    $productsByNormalizedName,
                    $partnerProductsByExternalId,
                    $partnerProductsByName,
                    $partnerProductsByNormalizedName,
                );

                if ($product === null) {
                    $skippedNoMatch++;

                    continue;
                }

                $person = ! empty($sugarRow->contact_id)
                    ? ($personsByContactId->get($sugarRow->contact_id) ?? null)
                    : null;

                $orderItem = $this->matchOrderItemToSugarRow($order->orderItems, $sugarRow, $product, $usedOrderItemIds, $order->order_number, $person?->id);
                if ($orderItem === null) {
                    $skippedNoMatch++;

                    continue;
                }

                $usedOrderItemIds[] = $orderItem->id;

                $expected = $this->orderItemMainPurchasePayloadFromSugarRow($sugarRow);
                $current = $this->currentMainPurchasePayload($orderItem);

                if (! $this->purchasePayloadDiffers($current, $expected)) {
                    continue;
                }

                $tableRows[] = $this->formatMismatchRow($order, $orderItem, $current, $expected);
                $mismatches[] = [
                    'order_item' => $orderItem,
                    'expected'   => $expected,
                ];
            }
        }

        if ($tableRows === []) {
            $this->info('Geen afwijkende inkoopprijzen gevonden.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            [
                'Order#',
                'Item ID',
                'Regel',
                'Ink.totaal huidig',
                'Ink.totaal juist',
                'Kliniek huidig',
                'Kliniek juist',
                'Arts huidig',
                'Arts juist',
                'Overig huidig',
                'Overig juist',
            ],
            $tableRows,
        );

        $this->warn(count($tableRows).' orderregel(s) met afwijkende inkoopprijzen.');

        if ($skippedNoMatch > 0) {
            $this->warn("{$skippedNoMatch} Sugar-regel(s) konden niet gekoppeld worden aan een CRM orderregel.");
        }

        if (! $apply) {
            $this->line('');
            $this->info('Voer uit met --apply om deze prijzen automatisch bij te werken.');

            return self::SUCCESS;
        }

        foreach ($mismatches as $mismatch) {
            /** @var OrderItem $orderItem */
            $orderItem = $mismatch['order_item'];
            $expected = $mismatch['expected'];

            $orderItem->purchasePrice()->updateOrCreate(
                ['type' => PurchasePriceType::MAIN],
                array_merge(['type' => PurchasePriceType::MAIN], $expected),
            );
            $applied++;
        }

        $this->info("✓ {$applied} orderregel(s) bijgewerkt.");

        return self::SUCCESS;
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
    private function loadCrmOrders(array $orderNums, int $limit): Collection
    {
        $query = Order::query()
            ->with(['orderItems.purchasePrice', 'orderItems.product'])
            ->whereNotNull('external_id')
            ->where('external_id', '!=', '')
            ->orderByDesc('id');

        if ($orderNums !== []) {
            $query->whereIn('order_number', $orderNums);
        } elseif ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, OrderItem>  $orderItems
     * @param  list<int>  $usedOrderItemIds
     */
    private function matchOrderItemToSugarRow(
        Collection $orderItems,
        object $sugarRow,
        Product $product,
        array $usedOrderItemIds,
        ?string $orderNumber = null,
        ?int $personId = null,
    ): ?OrderItem {
        $rowName = trim((string) ($sugarRow->name ?? ''));
        $salesPrice = round((float) ($sugarRow->sales_price ?? 0), 2);

        $candidates = $orderItems
            ->filter(fn (OrderItem $item) => ! in_array($item->id, $usedOrderItemIds, true))
            ->filter(fn (OrderItem $item) => (int) $item->product_id === (int) $product->id);

        // Exact match via Sugar row external_id — most reliable, no ambiguity.
        if (! empty($sugarRow->id)) {
            $byExternalId = $candidates->first(fn (OrderItem $item) => $item->external_id === $sugarRow->id);
            if ($byExternalId !== null) {
                return $byExternalId;
            }
        }

        if ($candidates->isEmpty()) {
            return null;
        }

        $nameAndPriceMatch = fn (OrderItem $item) => (
            ($rowName === '' || trim((string) ($item->name ?? '')) === $rowName)
            && abs(round((float) $item->total_price, 2) - $salesPrice) < 0.02
        );

        $exact = $candidates->filter($nameAndPriceMatch);

        if ($exact->count() === 1) {
            return $exact->first();
        }

        // Tiebreak with person_id when multiple items match on product+name+price.
        if ($exact->count() > 1 && $personId !== null) {
            $byPerson = $exact->filter(fn (OrderItem $item) => (int) $item->person_id === $personId);
            if ($byPerson->count() === 1) {
                return $byPerson->first();
            }
        }

        if ($exact->count() > 1) {
            // Multiple identical items remain after person tiebreak. When this Sugar row is one of
            // several identical rows, the next iteration will claim the remaining item via
            // $usedOrderItemIds — no action needed. Log at debug level only.
            Log::debug('RepairSugarOrderPurchasePrices: meerdere orderregels matchen op product+naam+prijs (ook na person_id tiebreak)', [
                'order_number'   => $orderNumber,
                'sugar_row_id'   => $sugarRow->id ?? null,
                'person_id'      => $personId,
                'order_item_ids' => $exact->pluck('id')->all(),
            ]);

            return $exact->first();
        }

        $byPrice = $candidates->filter(
            fn (OrderItem $item) => abs(round((float) $item->total_price, 2) - $salesPrice) < 0.02
        );

        if ($byPrice->count() === 1) {
            return $byPrice->first();
        }

        $byName = $candidates->filter(
            fn (OrderItem $item) => $rowName !== '' && trim((string) ($item->name ?? '')) === $rowName
        );

        if ($byName->count() === 1) {
            return $byName->first();
        }

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        return null;
    }

    /**
     * @return array<string, float>
     */
    private function currentMainPurchasePayload(OrderItem $orderItem): array
    {
        $price = $orderItem->purchasePrice;

        return [
            'purchase_price_misc'       => (float) ($price?->purchase_price_misc ?? 0),
            'purchase_price_doctor'     => (float) ($price?->purchase_price_doctor ?? 0),
            'purchase_price_cardiology' => (float) ($price?->purchase_price_cardiology ?? 0),
            'purchase_price_clinic'     => (float) ($price?->purchase_price_clinic ?? 0),
            'purchase_price_radiology'  => (float) ($price?->purchase_price_radiology ?? 0),
            'purchase_price'            => (float) ($price?->purchase_price ?? 0),
        ];
    }

    /**
     * @param  array<string, float>  $current
     * @param  array<string, float>  $expected
     */
    private function purchasePayloadDiffers(array $current, array $expected): bool
    {
        foreach (array_keys($expected) as $field) {
            if (abs(($current[$field] ?? 0.0) - ($expected[$field] ?? 0.0)) >= 0.01) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, float>  $current
     * @param  array<string, float>  $expected
     * @return list<string>
     */
    private function formatMismatchRow(
        Order $order,
        OrderItem $orderItem,
        array $current,
        array $expected,
    ): array {
        return [
            $order->order_number ?? '—',
            (string) $orderItem->id,
            $orderItem->name ?? '—',
            $this->formatMoney($current['purchase_price'] ?? 0),
            $this->formatMoney($expected['purchase_price'] ?? 0),
            $this->formatMoney($current['purchase_price_clinic'] ?? 0),
            $this->formatMoney($expected['purchase_price_clinic'] ?? 0),
            $this->formatMoney($current['purchase_price_doctor'] ?? 0),
            $this->formatMoney($expected['purchase_price_doctor'] ?? 0),
            $this->formatMoney($current['purchase_price_misc'] ?? 0),
            $this->formatMoney($expected['purchase_price_misc'] ?? 0),
        ];
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, ',', '.');
    }
}
