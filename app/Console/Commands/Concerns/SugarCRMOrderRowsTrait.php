<?php

namespace App\Console\Commands\Concerns;

use App\Models\PartnerProduct;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Webkul\Product\Models\Product;

/**
 * Shared Sugar CRM order-row resolution methods used by both
 * ImportOrdersFromSugarCRM and RepairSugarOrderPurchasePrices.
 */
trait SugarCRMOrderRowsTrait
{
    /**
     * Batch-fetch all order rows for the given SugarCRM order UUIDs.
     * Returns a Collection keyed by the SugarCRM order UUID, each value being
     * a Collection of row objects (one row per contact, due to the LEFT JOIN).
     */
    protected function fetchOrderRows(string $connection, array $orderIds): Collection
    {
        if (empty($orderIds)) {
            return collect();
        }

        $baseSelect = [
            'rel.pcrm_salesb9a7esorder_ida as order_id',
            'sor.id',
            'sor.name',
            'sor.sales_price',
            'sor.sales_stage',
            'sor.resource_type',
            'row_cstm.aos_products_id_c',
            'sor.datum_onderzoek',
            'sor.duration',
            'sor.pcrm_partnerresources_id_c',
            'sor.pcrm_partnerproducts_id_c',
            'pt.name as product_template_name',
            'rc.pcrm_sales4bd9ontacts_ida as contact_id',
            // Authoritative purchase price fields on the main row table (no _c suffix)
            'sor.purchase_price as sor_purchase_price',
            'sor.purchase_clinic as sor_purchase_clinic',
            'sor.purchase_doctor as sor_purchase_doctor',
        ];

        $sql = DB::connection($connection)
            ->table('pcrm_salesoalesorderrow_c as rel')
            ->join('pcrm_salesorderrow as sor', 'sor.id', '=', 'rel.pcrm_sales509drderrow_idb')
            ->leftJoin('pcrm_salesorderrow_cstm as row_cstm', 'row_cstm.id_c', '=', 'sor.id')
            ->leftJoin('pcrm_salesorow_contacts_c as rc', function ($join) {
                $join->on('rc.pcrm_sales80b3rderrow_idb', '=', 'sor.id')
                    ->where('rc.deleted', '=', 0);
            })
            ->leftJoin('aos_products as pt', function ($join) {
                $join->on('pt.id', '=', 'row_cstm.aos_products_id_c')
                    ->where('pt.deleted', '=', 0);
            })
            ->select(array_merge($baseSelect, $this->sugarOrderRowCstmSelectFragments($connection)))
            ->where('sor.deleted', 0)
            ->whereIn('rel.pcrm_salesb9a7esorder_ida', $orderIds);

        $this->infoVV($sql->toRawSql());
        $rows = $sql->get()->unique('id');

        return $rows->groupBy('order_id');
    }

    /**
     * Custom fields on pcrm_salesorderrow_cstm differ per Sugar instance; only select columns that exist.
     *
     * @return list<string>
     */
    protected function sugarOrderRowCstmSelectFragments(string $connection): array
    {
        $byLowerName = $this->sugarOrderRowCstmColumnByLowerName($connection);
        $candidates = [
            // cstm purchase components (clinic/total live on main row table; rd not in mapping)
            'purchase_other_c',
            'purchase_cardio_c',
            'purchase_radio_c',
            // aflettering invoice amounts (rd not in mapping)
            'inv_purchase_other_c',
            'inv_purchase_cardio_c',
            'inv_purchase_clinic_c',
            'inv_purchase_radio_c',
            'inv_purchase_doctor_c',
            'inv_purchase_total_c',
            // aflettering statuses
            'ink_other_status_c',
            'ink_cardio_status_c',
            'ink_clinic_status_c',
            'ink_radio_status_c',
            'ink_doctor_status_c',
            'ink_total_status_c',
            'afb_description_c',
        ];

        $fragments = [];
        foreach ($candidates as $column) {
            $dbColumn = $byLowerName[strtolower($column)] ?? null;
            if ($dbColumn !== null) {
                $fragments[] = "row_cstm.{$dbColumn} as {$column}";
            }
        }

        return $fragments;
    }

    /**
     * Lowercase name => actual column name on the database (for correct quoting / casing).
     *
     * @return array<string, string>
     */
    protected function sugarOrderRowCstmColumnByLowerName(string $connection): array
    {
        static $cache = [];

        if (array_key_exists($connection, $cache)) {
            return $cache[$connection];
        }

        try {
            $names = Schema::connection($connection)->getColumnListing('pcrm_salesorderrow_cstm');
            $map = [];
            foreach ($names as $name) {
                $map[strtolower($name)] = $name;
            }
            $cache[$connection] = $map;
        } catch (Exception $e) {
            Log::warning('ImportOrdersFromSugarCRM: could not introspect pcrm_salesorderrow_cstm; order row custom fields will be skipped', [
                'connection' => $connection,
                'error'      => $e->getMessage(),
            ]);
            $this->warn('Could not introspect pcrm_salesorderrow_cstm; order row custom fields will be skipped.');
            $cache[$connection] = [];
        }

        return $cache[$connection];
    }

    /**
     * MAIN purchase row: authoritative source is the pcrm_salesorderrow main table
     * (purchase_price total, purchase_clinic, purchase_doctor). Supplementary cstm fields
     * (purchase_other_c, purchase_cardio_c, purchase_radio_c) cover the remaining components.
     *
     * Note: purchase_clinic_c and purchase_total_c do NOT exist in the Sugar schema —
     * clinic and total live only on the main row table without the _c suffix.
     * purchase_rd is not part of our CRM mapping and is intentionally excluded.
     *
     * @return array<string, float>
     */
    protected function orderItemMainPurchasePayloadFromSugarRow(object $row): array
    {
        return $this->buildPurchasePayloadFromSugarAmounts(
            $this->sugarMoneyAmount(data_get($row, 'purchase_other_c')),
            $this->sugarMoneyAmount(data_get($row, 'purchase_cardio_c')),
            $this->sugarMoneyAmount(data_get($row, 'sor_purchase_clinic')),
            $this->sugarMoneyAmount(data_get($row, 'purchase_radio_c')),
            $this->sugarMoneyAmount(data_get($row, 'sor_purchase_price')),
            $this->sugarMoneyAmount(data_get($row, 'sor_purchase_doctor')),
        );
    }

    /**
     * Load CRM products keyed by exact {@see Product::name} for all non-empty Sugar row labels.
     *
     * @return Collection<string, Product>
     */
    protected function productsByNameForSugarRows(Collection $orderRows): Collection
    {
        // Collect both the (possibly overridden) row name and the original product template name.
        $names = $orderRows->flatMap(fn ($row) => [
            trim((string) ($row->name ?? '')),
            trim((string) ($row->product_template_name ?? '')),
        ])
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($names === []) {
            return collect();
        }

        // Build the initial collection keyed by exact product name.
        $byName = Product::query()
            ->whereIn('name', $names)
            ->get()
            ->keyBy(fn (Product $p) => $p->name);

        // For any row name that did not yield an exact match, try to load CRM products
        // whose normalized name matches (e.g. "TB1 Royal+ Bodyscan" → "TB1 Royal Bodyscan").
        $unmatchedNames = collect($names)->reject(fn ($n) => $byName->has($n));

        if ($unmatchedNames->isNotEmpty()) {
            $normalizedToRaw = $unmatchedNames->mapWithKeys(
                fn ($n) => [$this->normalizeProductName($n) => $n]
            );

            // Load all products and check normalized names; only do this when there are unmatched rows.
            Product::all()->each(function (Product $p) use ($byName, $normalizedToRaw) {
                $normalizedProductName = $this->normalizeProductName($p->name);
                if ($normalizedToRaw->has($normalizedProductName) && ! $byName->has($p->name)) {
                    $byName->put($p->name, $p);
                }
            });
        }

        return $byName;
    }

    /**
     * Partner products keyed by {@see PartnerProduct::external_id} (Sugar pcrm_partnerproducts_id_c).
     * Includes inactive rows: Sugar historical orders may reference retired partner products.
     *
     * @return Collection<string, Product>
     */
    protected function partnerProductsByExternalId(): Collection
    {
        return PartnerProduct::query()
            ->whereNotNull('external_id')
            ->where('external_id', '!=', '')
            ->with('product')
            ->get()
            ->filter(fn (PartnerProduct $pp) => $pp->product !== null)
            ->mapWithKeys(fn (PartnerProduct $pp) => [$pp->external_id => $pp->product]);
    }

    /**
     * Partner products keyed by exact {@see PartnerProduct::name}.
     * Includes inactive rows for Sugar import fallbacks.
     *
     * @return Collection<string, Product>
     */
    protected function partnerProductsByName(): Collection
    {
        return PartnerProduct::query()
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->with('product')
            ->get()
            ->filter(fn (PartnerProduct $pp) => $pp->product !== null)
            ->mapWithKeys(fn (PartnerProduct $pp) => [$pp->name => $pp->product]);
    }

    /**
     * Partner products keyed by normalized {@see PartnerProduct::name} (CRM catalog {@see Product::name} may differ).
     * Includes inactive rows for Sugar import fallbacks.
     *
     * @return Collection<string, Product>
     */
    protected function partnerProductsByNormalizedName(): Collection
    {
        return PartnerProduct::query()
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->with('product')
            ->get()
            ->filter(fn (PartnerProduct $pp) => $pp->product !== null)
            ->mapWithKeys(fn (PartnerProduct $pp) => [
                $this->normalizeProductName($pp->name) => $pp->product,
            ]);
    }

    /**
     * Resolve CRM product for a Sugar order row.
     *
     * Priority: PartnerProduct.external_id (pcrm_partnerproducts_id_c) → PartnerProduct.name →
     * Product.name (exact/normalized) and Sugar product template name fallback.
     *
     * @param  Collection<string, Product>  $productsByName
     * @param  Collection<string, Product>  $productsByNormalizedName
     * @param  Collection<string, Product>  $partnerProductsByExternalId
     * @param  Collection<string, Product>  $partnerProductsByName
     * @param  Collection<string, Product>  $partnerProductsByNormalizedName
     */
    protected function resolveProductForSugarRow(
        object $row,
        Collection $productsByName,
        Collection $productsByNormalizedName,
        Collection $partnerProductsByExternalId,
        Collection $partnerProductsByName,
        Collection $partnerProductsByNormalizedName,
    ): ?Product {
        if (! empty($row->pcrm_partnerproducts_id_c)) {
            $byPartnerProduct = $partnerProductsByExternalId->get($row->pcrm_partnerproducts_id_c);
            if ($byPartnerProduct !== null) {
                return $byPartnerProduct;
            }
        }

        $label = trim((string) ($row->name ?? ''));
        $templateName = trim((string) ($row->product_template_name ?? ''));

        if ($label !== '') {
            $byPartnerName = $partnerProductsByName->get($label);
            if ($byPartnerName !== null) {
                return $byPartnerName;
            }

            $byPartnerNormalized = $partnerProductsByNormalizedName->get($this->normalizeProductName($label));
            if ($byPartnerNormalized !== null) {
                return $byPartnerNormalized;
            }
        }

        if ($label !== '') {
            $byName = $productsByName->get($label);
            if ($byName !== null) {
                return $byName;
            }
        }

        if ($templateName !== '') {
            $byTemplateName = $productsByName->get($templateName);
            if ($byTemplateName !== null) {
                return $byTemplateName;
            }
        }

        if ($label !== '') {
            $byNormalized = $productsByNormalizedName->get($this->normalizeProductName($label));
            if ($byNormalized !== null) {
                return $byNormalized;
            }
        }

        if ($templateName !== '') {
            $byTplNorm = $productsByNormalizedName->get($this->normalizeProductName($templateName));
            if ($byTplNorm !== null) {
                return $byTplNorm;
            }
        }

        return null;
    }

    /**
     * Normalize a product name for fuzzy matching:
     * strips '+' characters and collapses whitespace to handle Sugar overrides
     * like "TB1 Royal+ Bodyscan" matching CRM product "TB1 Royal Bodyscan".
     */
    protected function normalizeProductName(string $name): string
    {
        $normalized = str_replace('+', ' ', $name);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return strtolower(trim($normalized));
    }

    /**
     * When Sugar sends no values, returns explicit zeros so {@see OrderItem::resolvedPurchasePrice()}
     * uses the line only and does not fall back to catalog product / partner product prices.
     *
     * Aligns component sum to Sugar total (remainder in misc).
     *
     * @return array<string, float>
     */
    protected function buildPurchasePayloadFromSugarAmounts(
        ?float $miscRaw,
        ?float $cardio,
        ?float $clinic,
        ?float $radio,
        ?float $totalFromSugar,
        ?float $doctor = null,
    ): array {
        $empty = [
            'purchase_price_misc'       => 0.0,
            'purchase_price_doctor'     => 0.0,
            'purchase_price_cardiology' => 0.0,
            'purchase_price_clinic'     => 0.0,
            'purchase_price_radiology'  => 0.0,
            'purchase_price'            => 0.0,
        ];

        $hasComponent = $miscRaw !== null || $cardio !== null || $clinic !== null || $radio !== null || $doctor !== null;
        $hasTotal = $totalFromSugar !== null;

        if (! $hasComponent && ! $hasTotal) {
            return $empty;
        }

        $sumSugarComponents = ($miscRaw ?? 0.0) + ($cardio ?? 0.0) + ($clinic ?? 0.0) + ($radio ?? 0.0) + ($doctor ?? 0.0);
        $total = $totalFromSugar !== null ? $totalFromSugar : round($sumSugarComponents, 2);

        $remainder = round($total - $sumSugarComponents, 2);
        $misc = round(($miscRaw ?? 0.0) + $remainder, 2);

        return [
            'purchase_price_misc'       => $misc,
            'purchase_price_doctor'     => $doctor ?? 0.0,
            'purchase_price_cardiology' => $cardio ?? 0.0,
            'purchase_price_clinic'     => $clinic ?? 0.0,
            'purchase_price_radiology'  => $radio ?? 0.0,
            'purchase_price'            => $total,
        ];
    }

    protected function sugarMoneyAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }
}
