<?php

namespace App\Console\Commands;

use App\Enums\LostReason;
use App\Enums\OrderItemStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\PipelineStage;
use App\Enums\PurchasePriceType;
use App\Models\Department;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\PartnerProduct;
use App\Models\Resource;
use App\Models\ResourceOrderItem;
use App\Models\SalesLead;
use App\Repositories\AddressRepository;
use App\Repositories\SalesLeadRepository;
use App\Services\Importers\SugarCRM\ActivityImporter;
use App\Services\OrderCheckService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Email\Models\Email;
use Webkul\Lead\Models\Lead;
use Webkul\Product\Models\Product;
use Webkul\User\Models\User;

class ImportOrdersFromSugarCRM extends AbstractSugarCRMImport
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:orders
                            {--connection=sugarcrm : Database connection name}
                            {--table=pcrm_salesorder : Source table name}
                            {--limit=-1 : Number of records to import}
                            {--order-ids=* : Specific order numbers to import, e.g. 202600625 (ignores limit)}
                            {--date-from= : Only import orders with date_entered on or after this date (Y-m-d)}
                            {--date-to= : Only import orders with date_entered before this date (Y-m-d)}
                            {--import-leads : Import missing linked leads (with persons) before importing orders}
                            {--dry-run : Show what would be imported without actually importing}
                            {--tasks-only : Only import tasks for all existing orders (skip order import)}
                            {--tasks-parent-type=PCRM_SalesOrder : SugarCRM parent_type value used to link tasks to orders}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import orders from SugarCRM database, creating SalesLeads and OrderItems per order';

    public function __construct(
        private readonly SalesLeadRepository $salesLeadRepository,
        private readonly OrderCheckService $orderCheckService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $connection = $this->option('connection');
        $table = $this->option('table');
        $limit = (int) $this->option('limit');
        $orderIds = $this->option('order-ids');
        $dryRun = $this->option('dry-run');
        $dateFrom = $this->option('date-from') ? $this->option('date-from').' 00:00:00' : null;
        $dateTo = $this->option('date-to') ? $this->option('date-to').' 00:00:00' : null;
        if ($this->option('tasks-only')) {
            $this->importTasksForExistingOrders($connection);

            return self::SUCCESS;
        }

        $this->info('Starting order import from SugarCRM...');
        $this->infoV("Connection: {$connection}");
        $this->infoV("Table: {$table}");
        if (! empty($orderIds)) {
            $this->infoV('Order numbers: '.(is_array($orderIds) ? implode(', ', $orderIds) : $orderIds));
        } else {
            $this->infoV("Limit: {$limit}");
        }
        $this->infoV('Date range: '.($this->option('date-from') ?: 'geen ondergrens').' to '.($this->option('date-to') ?: 'geen bovengrens'));
        $this->infoV('Dry run: '.($dryRun ? 'Yes' : 'No'));

        try {
            return $this->executeImport($dryRun, function () use ($connection, $table, $limit, $orderIds, $dryRun, $dateFrom, $dateTo) {
                if (! $dryRun) {
                    $this->startImportRun('orders');
                }

                $this->testConnection($connection);

                // Subquery: one Sugar lead UUID per order (first by MIN for determinism)
                $leadRelSub = DB::connection($connection)
                    ->table('leads_pcrm_salesorder_c')
                    ->selectRaw('leads_p5ae2rder_idb as order_id, MIN(leads_p903eeads_ida) as sugar_lead_id')
                    ->where('deleted', 0)
                    ->groupBy('leads_p5ae2rder_idb');

                $sql = DB::connection($connection)
                    ->table($table.' as so')
                    ->join('pcrm_salesorder_cstm as cstm', 'cstm.id_c', '=', 'so.id')
                    ->leftJoinSub($leadRelSub, 'lead_rel', 'lead_rel.order_id', '=', 'so.id')
                    ->leftJoin('pcrm_salesoder_accounts_c as a', function ($join) {
                        $join->on('a.pcrm_salesd0bfesorder_idb', '=', 'so.id')
                            ->where('a.deleted', '=', 0);
                    })
                    ->leftJoin('accounts as ac', function ($join) {
                        $join->on('ac.id', '=', 'a.pcrm_sales697fccounts_ida')
                            ->where('ac.deleted', '=', 0);
                    })
                    // Sugar has accounts_cstm for every account row; require it when an account is linked.
                    // Plain INNER JOIN would drop all particuliere orders (no account ⇒ ac.id IS NULL).
                    ->leftJoin('accounts_cstm as ac_cstm', 'ac_cstm.id_c', '=', 'ac.id')
                    ->where(function ($q): void {
                        $q->whereNull('ac.id')
                            ->orWhereNotNull('ac_cstm.id_c');
                    })
                    ->select([
                        'so.id',
                        'so.name',
                        'so.order_num',
                        'so.amount',
                        'so.sales_stage',
                        'so.date_closed',
                        'so.datum_onderzoek_1',
                        'so.date_entered',
                        'so.date_modified',
                        'cstm.reden_afvoeren_c',
                        'cstm.op_een_factuur_c',
                        'cstm.d_wfl_status_c',
                        'cstm.aankomsttijd_c',
                        'cstm.betaald_vooruit_c',
                        'cstm.datum_betaling_vr_c',
                        'cstm.betaald_kliniek_c',
                        'cstm.openstaand_c',
                        'cstm.betaal_status_c',
                        'cstm.pin_contant_c',
                        'cstm.user_id_c',
                        'so.assigned_user_id',
                        'lead_rel.sugar_lead_id',
                        'ac.name as account_name',
                        'ac.billing_address_postalcode as account_billing_postalcode',
                        'ac.billing_address_state as account_billing_state',
                        'ac.billing_address_street as account_billing_street',
                        'ac.billing_address_city as account_billing_city',
                        'ac.shipping_address_city as account_shipping_city',
                        'ac.billing_address_country as account_billing_country',
                        'ac_cstm.billing_huisnr_c as account_billing_huisnr_c',
                        'ac_cstm.billing_huisnr_toevoeging_c as account_billing_huisnr_toevoeging_c',
                    ])
                    ->where('so.deleted', 0)
                    ->whereNotNull('so.id')
                    ->where('so.id', '!=', '');

                if ($dateFrom) {
                    $sql = $sql->where('so.date_entered', '>=', $dateFrom);
                }

                if ($dateTo) {
                    $sql = $sql->where('so.date_entered', '<', $dateTo);
                }

                if (! empty($orderIds)) {
                    if (is_array($orderIds)) {
                        $orderIds = implode(' ', $orderIds);
                    }
                    $normalizedIds = preg_split('/[\s,]+/', (string) $orderIds, -1, PREG_SPLIT_NO_EMPTY);
                    $sql = $sql->whereIn('so.order_num', $normalizedIds);
                } else {
                    $sql = $sql->orderBy('so.date_entered', 'desc');
                    // LIMIT on joined rows is wrong when an order has multiple account links: apply cap on distinct orders only.
                    if ($limit > 0) {
                        $orderIdCap = DB::connection($connection)
                            ->table($table.' as so')
                            ->join('pcrm_salesorder_cstm as cstm', 'cstm.id_c', '=', 'so.id')
                            ->where('so.deleted', 0)
                            ->whereNotNull('so.id')
                            ->where('so.id', '!=', '');

                        if ($dateFrom) {
                            $orderIdCap = $orderIdCap->where('so.date_entered', '>=', $dateFrom);
                        }

                        if ($dateTo) {
                            $orderIdCap = $orderIdCap->where('so.date_entered', '<', $dateTo);
                        }

                        $orderIdCap = $orderIdCap
                            ->orderBy('so.date_entered', 'desc')
                            ->limit($limit)
                            ->select('so.id');

                        // MySQL rejects LIMIT inside IN-subquery (error 1235); JOIN derived table is supported.
                        $sql->joinSub($orderIdCap, 'order_id_cap', 'order_id_cap.id', '=', 'so.id');
                    }
                }

                $this->infoVV($sql->toRawSql());

                try {
                    $records = $sql->get()->unique('id');
                } catch (Exception $e) {
                    $this->error('Query failed: '.$e->getMessage());
                    throw $e;
                }

                $this->info('Found '.$records->count().' orders to import');

                if ($this->option('import-leads') && ! $dryRun) {
                    $this->importMissingLeadsForOrders($records, $connection);
                }

                if ($dryRun) {
                    $this->showDryRunResults($records, $connection);

                    return;
                }

                Order::withoutEvents(function () use ($records, $connection) {
                    $this->importRecords($records, $connection);
                });

                $this->importTasksForOrders($records, $connection);
            });
        } catch (Exception $e) {
            $this->error('Error: '.$e->getMessage());
            Log::error('SugarCRM order import failed', [
                'error'      => $e->getMessage(),
                'connection' => $connection,
                'table'      => $table,
            ]);
            report($e);

            return 1;
        }
    }

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
     * Import any linked Sugar leads (and their persons) that are not yet in the CRM.
     * Mirrors the --import-persons pattern in ImportLeadsFromSugarCRM.
     */
    private function importMissingLeadsForOrders(Collection $records, string $connection): void
    {
        $sugarLeadIds = $records
            ->pluck('sugar_lead_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($sugarLeadIds)) {
            $this->info('No Sugar lead UUIDs found for these orders — skipping lead import.');

            return;
        }

        $alreadyImported = Lead::whereIn('external_id', $sugarLeadIds)->pluck('external_id')->all();
        $missing = array_values(array_diff($sugarLeadIds, $alreadyImported));

        if (empty($missing)) {
            $this->info('All linked leads are already imported.');

            return;
        }

        $this->info(sprintf('Importing %d missing lead(s) (with persons)...', count($missing)));

        Artisan::call('import:leads', [
            '--connection'     => $connection,
            '--lead-ids'       => $missing,
            '--import-persons' => true,
        ], $this->output);
    }

    /**
     * Show dry run results.
     */
    private function showDryRunResults(Collection $records, string $connection): void
    {
        $this->info("\n=== DRY RUN RESULTS ===");
        $this->line('Type: Zakelijk = gekoppeld Sugar-account (naam niet begint met "Fam "); anders Particulier.');
        $this->newLine();

        $orderIds = $records->pluck('id')->all();
        $rowsByOrder = $this->fetchOrderRows($connection, $orderIds);

        foreach ($records as $record) {
            $label = $this->formatDryRunOrderTypeLabel($record);
            $num = $record->order_num ?? substr((string) $record->id, 0, 8).'…';
            $addr = $this->dryRunSugarOrganizationAddressHint($record);
            $this->line("  #{$num}  →  {$label}".($addr !== null ? '  |  '.$addr : ''));
        }
        $this->newLine();

        // Orders table (Type direct na Order# zodat het in smalle terminals zichtbaar blijft)
        $headers = ['External ID', 'Order#', 'Type', 'Name', 'Amount', 'Stage', 'Lost Reason', 'First exam', 'Rows', 'Status'];
        $rows = [];

        foreach ($records as $record) {
            $orderRows = $rowsByOrder->get($record->id, collect());
            $alreadyDone = Order::where('external_id', $record->id)->exists();

            $rows[] = [
                substr($record->id, 0, 8).'…',
                $record->order_num ?? 'N/A',
                $this->formatDryRunOrderTypeLabel($record),
                $record->name ?? 'N/A',
                $record->amount ?? '0',
                $record->sales_stage ?? 'N/A',
                $record->reden_afvoeren_c ?? '',
                $this->parseSugarExaminationAt($record->datum_onderzoek_1, $record->aankomsttijd_c) ?? '—',
                $orderRows->count(),
                $alreadyDone ? '✓ skip' : '✗ new',
            ];
        }

        $this->table($headers, $rows);
        $newCount = collect($rows)->filter(fn ($r) => $r[9] === '✗ new')->count();
        $this->info("Would import {$newCount} orders");

        // Order rows detail preview
        $this->showDryRunOrderRowsPreview($records, $rowsByOrder);

        // SalesLead preview for new orders only
        $newRecords = $records->filter(fn ($r) => ! Order::where('external_id', $r->id)->exists());
        if ($newRecords->isEmpty()) {
            return;
        }

        $this->info("\n=== SALESLEAD PREVIEW (new orders only) ===");

        $slHeaders = ['Order#', 'Type', 'SalesLead name', 'Sales stage', 'Lead ref (CRM)', 'Lead #', 'Lead name', 'Lead found?'];
        $slRows = [];

        foreach ($newRecords as $record) {
            $crmLead = ! empty($record->sugar_lead_id)
                ? Lead::with('department')->where('external_id', $record->sugar_lead_id)->first()
                : null;

            $salesStage = $this->mapSalesStageToSalesPipelineStage($record->sales_stage ?? '', $crmLead?->department);

            $slRows[] = [
                $record->order_num ?? 'N/A',
                $this->formatDryRunOrderTypeLabel($record),
                $this->stripOrderNumberFromName($record->name ?? '', $record->order_num ?? null),
                $salesStage->label(),
                $record->sugar_lead_id ? substr($record->sugar_lead_id, 0, 8).'…' : '—',
                $crmLead ? '#'.$crmLead->id : '—',
                $crmLead ? trim($crmLead->first_name.' '.$crmLead->last_name) : '—',
                $crmLead ? '✓' : '✗ missing',
            ];
        }

        $this->table($slHeaders, $slRows);

        $missing = collect($slRows)->filter(fn ($r) => $r[7] === '✗ missing')->count();
        if ($missing > 0) {
            $this->warn("{$missing} order(s) have no matching CRM Lead — these orders will be skipped during import. Run with --import-leads to import missing leads first.");
        }
    }

    /**
     * Show per-order row details in the dry run: product resolution and invoice settlement (afletteren) status.
     */
    private function showDryRunOrderRowsPreview(Collection $records, Collection $rowsByOrder): void
    {
        $allRows = $rowsByOrder->flatten(1);
        if ($allRows->isEmpty()) {
            return;
        }

        $this->info("\n=== ORDER ROWS PREVIEW ===");

        // Build product lookup collections once for all rows (same logic as importRecords)
        $productsByName = $this->productsByNameForSugarRows($allRows);
        $productsByNormalizedName = $productsByName->mapWithKeys(
            fn (Product $p, string $name) => [$this->normalizeProductName($name) => $p]
        );
        $partnerProductsByExternalId = $this->partnerProductsByExternalId();
        $partnerProductsByName = $this->partnerProductsByName();
        $partnerProductsByNormalizedName = $this->partnerProductsByNormalizedName();

        $headers = ['Order#', 'Naam', 'CRM product', 'Prijs', 'Status', 'Ink.totaal', 'Afl.other', 'Afl.cardio', 'Afl.clinic', 'Afl.radio', 'Afl.rd', 'Afl.doctor', 'Afl.totaal'];
        $tableRows = [];
        $noMatchCount = 0;

        foreach ($records as $record) {
            $orderRows = $rowsByOrder->get($record->id, collect());
            $orderNum = $record->order_num ?? 'N/A';
            $hasScheduledExamination = $this->parseSugarExaminationAt($record->datum_onderzoek_1, $record->aankomsttijd_c) !== null;

            foreach ($orderRows as $row) {
                $product = $this->resolveProductForSugarRow(
                    $row,
                    $productsByName,
                    $productsByNormalizedName,
                    $partnerProductsByExternalId,
                    $partnerProductsByName,
                    $partnerProductsByNormalizedName,
                );

                if ($product === null) {
                    $noMatchCount++;
                }

                $status = $this->mapRowSalesStageToOrderItemStatus($row->sales_stage ?? '', $hasScheduledExamination);
                $mainPayload = $this->orderItemMainPurchasePayloadFromSugarRow($row);

                $tableRows[] = [
                    $orderNum,
                    $row->name ?? '—',
                    $product ? $product->name : '✗ geen match',
                    number_format((float) ($row->sales_price ?? 0), 2),
                    $status->value,
                    number_format((float) ($mainPayload['purchase_price'] ?? 0), 2),
                    $this->dryRunAflettereCell($row, 'other'),
                    $this->dryRunAflettereCell($row, 'cardio'),
                    $this->dryRunAflettereCell($row, 'clinic'),
                    $this->dryRunAflettereCell($row, 'radio'),
                    $this->dryRunAflettereCell($row, 'rd'),
                    $this->dryRunAflettereCell($row, 'doctor'),
                    $this->dryRunAflettererTotaalCell($row),
                ];
            }
        }

        $this->table($headers, $tableRows);

        if ($noMatchCount > 0) {
            $this->warn("{$noMatchCount} order rule(s) hebben geen CRM product — deze regels worden overgeslagen bij import.");
        }
    }

    /**
     * Format a single invoice settlement (afletteren) component cell for the dry run.
     * Shows "amount (status)" when amount is present, or status alone, or "—" when empty.
     */
    private function dryRunAflettereCell(object $row, string $component): string
    {
        $amountField = "inv_purchase_{$component}_c";
        $statusField = "ink_{$component}_status_c";

        $amount = property_exists($row, $amountField) ? $this->sugarMoneyAmount(data_get($row, $amountField)) : null;
        $status = property_exists($row, $statusField) ? (data_get($row, $statusField) ?? '') : null;
        $allowed = $status === null || $this->sugarInkStatusAllowsInvoiceAmount($status);

        if ($amount === null && ($status === null || $status === '')) {
            return '—';
        }

        $statusLabel = ($status !== null && $status !== '') ? $status : 'ok';
        $prefix = $allowed ? '' : '✗ ';

        if ($amount !== null) {
            return $prefix.number_format($amount, 2).' ('.$statusLabel.')';
        }

        return $prefix.$statusLabel;
    }

    /**
     * Format the total invoice settlement (afletteren totaal) cell for the dry run.
     */
    private function dryRunAflettererTotaalCell(object $row): string
    {
        $totalAmount = property_exists($row, 'inv_purchase_total_c')
            ? $this->sugarMoneyAmount(data_get($row, 'inv_purchase_total_c'))
            : null;

        $effectiveTotal = $this->sugarInvoiceAggregatedTotalAmount($row);

        if ($totalAmount === null) {
            return '—';
        }

        if ($effectiveTotal === null) {
            return '✗ '.number_format($totalAmount, 2).' (geblokkeerd)';
        }

        return number_format($effectiveTotal, 2);
    }

    /**
     * Import all records.
     */
    private function importRecords(Collection $records, string $connection): void
    {
        $bar = $this->output->createProgressBar($records->count());
        $bar->start();

        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $skippedAlreadyExisting = 0;
        $firstErrors = [];

        // Batch-fetch all order rows for these orders upfront (avoid N+1)
        $orderIds = $records->pluck('id')->all();
        $rowsByOrder = $this->fetchOrderRows($connection, $orderIds);
        $allResourceExternalIds = $rowsByOrder->flatten()
            ->pluck('pcrm_partnerresources_id_c')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $resourcesByExternalId = ! empty($allResourceExternalIds)
            ? Resource::whereIn('external_id', $allResourceExternalIds)->get()->keyBy('external_id')
            : collect();

        $partnerProductsByExternalId = $this->partnerProductsByExternalId();
        $partnerProductsByName = $this->partnerProductsByName();
        $partnerProductsByNormalizedName = $this->partnerProductsByNormalizedName();

        foreach ($records as $record) {
            try {
                // Skip if already imported
                if (Order::where('external_id', $record->id)->exists()) {
                    $skipped++;
                    $skippedAlreadyExisting++;
                    $this->infoV("Skipping existing order external_id={$record->id}");
                    $bar->advance();

                    continue;
                }

                $orderRows = $rowsByOrder->get($record->id, collect());

                // Collect unique contact IDs from rows
                $contactIds = $orderRows->pluck('contact_id')->filter()->unique()->values()->all();

                // Look up existing persons and products
                $personsByExternalId = ! empty($contactIds)
                    ? Person::whereIn('external_id', $contactIds)->get()->keyBy('external_id')
                    : collect();

                $productsByName = $this->productsByNameForSugarRows($orderRows);
                $productsByNormalizedName = $productsByName->mapWithKeys(
                    fn (Product $p, string $name) => [$this->normalizeProductName($name) => $p]
                );

                // Look up the imported CRM Lead via the Sugar lead UUID
                $crmLead = ! empty($record->sugar_lead_id)
                    ? Lead::with('department')->where('external_id', $record->sugar_lead_id)->first()
                    : null;

                $department = $crmLead?->department;

                // Derive pipeline stages and lost reason
                $orderStage = $this->mapSalesStageToOrderPipelineStage($record->sales_stage ?? '', $department);
                $salesStage = $this->mapSalesStageToSalesPipelineStage($record->sales_stage ?? '', $department);
                $lostReason = $this->mapLostReason($record->reden_afvoeren_c ?? null);
                $closedAt = $this->parseSugarUtcDate($record->date_closed);

                if ($crmLead === null) {
                    $this->warn("Skipping order {$record->id} (order_num={$record->order_num}): no matching CRM Lead found for sugar_lead_id=".($record->sugar_lead_id ?? 'null').'. Run with --import-leads to import missing leads first.');
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                $isBusiness = $this->isSugarBusinessOrder($record);

                DB::transaction(function () use (
                    $record,
                    $orderRows,
                    $personsByExternalId,
                    $productsByName,
                    $productsByNormalizedName,
                    $partnerProductsByExternalId,
                    $partnerProductsByName,
                    $partnerProductsByNormalizedName,
                    $resourcesByExternalId,
                    $salesStage,
                    $orderStage,
                    $lostReason,
                    $closedAt,
                    $crmLead,
                    $isBusiness,
                ) {
                    $timestamps = $this->parseSugarTimestamps($record);

                    $organizationId = null;
                    if ($isBusiness) {
                        $org = Organization::firstOrCreate(['name' => $record->account_name]);
                        $organizationId = $org->id;

                        $addressPayload = $this->organizationAddressPayloadFromSugarAccountRecord($record);
                        if ($addressPayload !== null && ! $org->address_id) {
                            app(AddressRepository::class)->upsertForEntity($org, $addressPayload);
                            $org->refresh();
                        }
                    }

                    $salesName = $this->stripOrderNumberFromName($record->name ?? '', $record->order_num ?? null);
                    // Same path as createFromWonLead (copyFromLead → persons + anamnesis), without auto-created order
                    $salesLead = $this->salesLeadRepository->createFromLeadForOrderImport($crmLead, [
                        'name'              => $salesName,
                        'pipeline_stage_id' => $salesStage->id(),
                        'lost_reason'       => $lostReason,
                        'closed_at'         => $closedAt,
                    ], $timestamps);

                    $this->attachSugarOrderPersonsToSalesLead($salesLead, $personsByExternalId);

                    $examinationDateStr = $this->parseSugarUtcDate($record->datum_onderzoek_1);

                    // Create the Order
                    $order = $this->createEntityWithTimestamps(Order::class, [
                        'external_id'                  => $record->id,
                        'order_number'                 => $record->order_num ?? null,
                        'title'                        => $record->name ?? "Order {$record->order_num}",
                        'total_price'                  => $record->amount ?? 0,
                        'pipeline_stage_id'            => $orderStage->id(),
                        'lost_reason'                  => $lostReason,
                        'closed_at'                    => $closedAt,
                        'first_examination_at'         => $examinationDateStr !== null ? substr($examinationDateStr, 0, 10) : null,
                        'first_examination_time'       => ! empty($record->aankomsttijd_c) && $examinationDateStr !== null
                            ? $this->parseAankomsttijd($record->aankomsttijd_c)
                            : null,
                        'sales_lead_id'                => $salesLead->id,
                        'user_id'                      => $this->mapSugarUserToId($record->assigned_user_id ?? null),
                        'clinic_coordinator_user_id'   => $this->mapSugarUserToId($record->user_id_c ?? null),
                        'combine_order'                => (bool) ($record->op_een_factuur_c ?? false),
                        'is_business'                  => $isBusiness,
                        'organization_id'              => $organizationId,
                    ], $this->parseSugarTimestamps($record));

                    $examinationAt = $this->parseSugarExaminationAt($record->datum_onderzoek_1, $record->aankomsttijd_c);
                    $hasScheduledExamination = $examinationAt !== null;

                    // duration.
                    $itemsCreated = 0;

                    // Create OrderItems
                    foreach ($orderRows as $row) {
                        $person = $row->contact_id ? ($personsByExternalId->get($row->contact_id) ?? null) : null;
                        $product = $this->resolveProductForSugarRow(
                            $row,
                            $productsByName,
                            $productsByNormalizedName,
                            $partnerProductsByExternalId,
                            $partnerProductsByName,
                            $partnerProductsByNormalizedName,
                        );

                        if ($product === null) {
                            $this->warn(sprintf(
                                'Skipping Sugar order row (no CRM product): order=%s row=%s name="%s" pcrm_partnerproducts_id_c=%s template_name="%s"',
                                $record->id,
                                $row->id ?? '',
                                $row->name ?? '',
                                $row->pcrm_partnerproducts_id_c ?? '',
                                $row->product_template_name ?? ''
                            ));

                            continue;
                        }

                        $orderItem = OrderItem::create([
                            'order_id'        => $order->id,
                            'external_id'     => $row->id ?? null,
                            'person_id'       => $person?->id,
                            'product_id'      => $product->id,
                            'name'            => $row->name ?? null,
                            'afb_description' => ! empty(data_get($row, 'afb_description_c')) ? trim((string) data_get($row, 'afb_description_c')) : null,
                            'total_price'     => $row->sales_price ?? 0,
                            'quantity'        => 1,
                            'status'          => $this->mapRowSalesStageToOrderItemStatus($row->sales_stage ?? '', $hasScheduledExamination),
                        ]);

                        if (! empty($row->pcrm_partnerresources_id_c) && $row->duration !== null) {
                            $resource = $resourcesByExternalId->get($row->pcrm_partnerresources_id_c);
                            if ($resource === null) {
                                Log::warning('ImportOrdersFromSugarCRM: resource not found for order row', [
                                    'order_id'             => $record->id,
                                    'order_row_id'         => $row->id,
                                    'resource_external_id' => $row->pcrm_partnerresources_id_c,
                                ]);
                            } else {
                                $rowExaminationAt = $this->parseSugarUtcAsDate($row->datum_onderzoek ?? null);
                                if ($rowExaminationAt !== null) {
                                    $from = $rowExaminationAt;
                                    $to = $from->copy()->addMinutes((int) $row->duration);
                                    ResourceOrderItem::create([
                                        'resource_id'  => $resource->id,
                                        'orderitem_id' => $orderItem->id,
                                        'from'         => $from,
                                        'to'           => $to,
                                    ]);
                                }
                            }
                        }

                        $invoicePurchasePayload = $this->orderItemInvoicePurchasePayloadFromSugarRow($row);
                        $mainPurchasePayload = $this->orderItemMainPurchasePayloadFromSugarRow($row);
                        $orderItem->invoicePurchasePrice()->updateOrCreate(
                            ['type' => PurchasePriceType::INVOICE],
                            array_merge(['type' => PurchasePriceType::INVOICE, 'force_received' => true], $invoicePurchasePayload)
                        );
                        $orderItem->purchasePrice()->updateOrCreate(
                            ['type' => PurchasePriceType::MAIN],
                            array_merge(['type' => PurchasePriceType::MAIN], $mainPurchasePayload)
                        );

                        $itemsCreated++;
                    }

                    if ($itemsCreated === 0 && $orderRows->isNotEmpty()) {
                        $this->warn("Order {$record->id}: imported with 0 line items (no matching products for any row).");
                    }

                    $this->syncOrderPaymentsFromSugar($order, $record);

                    // Align partner-rapportage checks with order lines (same as {@see OrderItemObserver} after full import).
                    $this->orderCheckService->updatePartnerProductChecks($order);
                });

                $imported++;
                $bar->advance();
            } catch (Exception $e) {
                $errors++;
                $this->logError('Failed to import order', [
                    'record_id'    => $record->id ?? 'unknown',
                    'order_num'    => $record->order_num ?? 'unknown',
                    'record_label' => $record->name ?? 'unknown',
                    'error'        => $e->getMessage(),
                ]);
                report($e);
                if (count($firstErrors) < 5) {
                    $firstErrors[] = [
                        'id'        => $record->id ?? 'unknown',
                        'order_num' => $record->order_num ?? 'unknown',
                        'message'   => $e->getMessage(),
                    ];
                }
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Import completed!');
        $this->info("✓ Imported: {$imported}");
        $this->info("⚠ Skipped: {$skipped}");
        $this->info("✗ Errors: {$errors}");

        $this->completeImportRun([
            'processed' => $imported + $skipped + $errors,
            'imported'  => $imported,
            'skipped'   => $skipped,
            'errored'   => $errors,
        ]);

        $this->line('');
        $this->info('Skip breakdown:');
        $this->info("- Already existing (external_id present): {$skippedAlreadyExisting}");

        if (! empty($firstErrors)) {
            $this->line('');
            $this->warn('First errors:');
            foreach ($firstErrors as $err) {
                $this->warn("  - ID={$err['id']} (order_num={$err['order_num']}): {$err['message']}");
            }
        }
    }

    /**
     * Combine datum_onderzoek_1 (date) with aankomsttijd_c (HH:MM varchar) into a full datetime string.
     * Falls back to date-only when the time field is absent or malformed.
     */
    private function parseSugarExaminationAt(mixed $date, mixed $time): ?string
    {
        $parsed = $this->parseSugarUtcDate($date);
        if ($parsed === null) {
            return null;
        }

        // parseSugarUtcDate returns 'Y-m-d H:i:s'; take only the date part before appending HH:MM time
        $dateOnly = substr($parsed, 0, 10);

        // Accept HH:MM, H:MM, HH.MM, H.MM (dot or colon separator, optional leading zero)
        if (! empty($time) && preg_match('/^(\d{1,2})[.:](\d{2})$/', trim((string) $time), $m)) {
            return $dateOnly.' '.str_pad($m[1], 2, '0', STR_PAD_LEFT).':'.$m[2].':00';
        }

        return $parsed;
    }

    private function parseAankomsttijd(mixed $time): ?string
    {
        if (empty($time) || ! preg_match('/^(\d{1,2})[.:](\d{2})$/', trim((string) $time), $m)) {
            return null;
        }

        return str_pad($m[1], 2, '0', STR_PAD_LEFT).':'.$m[2];
    }

    /**
     * Map SugarCRM sales_stage to the Order pipeline stage.
     * Hernia orders use a separate pipeline with different stages.
     */
    private function mapSalesStageToOrderPipelineStage(string $salesStage, ?Department $department = null): PipelineStage
    {
        $isHernia = $department?->isHernia() ?? false;

        return match (strtolower(trim($salesStage))) {
            'gewonnen' => $isHernia ? PipelineStage::ORDER_GEWONNEN_HERNIA : PipelineStage::ORDER_GEWONNEN,
            'verloren' => $isHernia ? PipelineStage::ORDER_VERLOREN_HERNIA : PipelineStage::ORDER_VERLOREN,
            default    => $isHernia ? PipelineStage::ORDER_VOORBEREIDEN_HERNIA : PipelineStage::ORDER_CONFIRM,
        };
    }

    /**
     * Map SugarCRM sales_stage to the SalesLead (sales pipeline) stage.
     * Hernia leads use a separate pipeline with different terminal stages.
     */
    private function mapSalesStageToSalesPipelineStage(string $salesStage, ?Department $department = null): PipelineStage
    {
        $isHernia = $department?->isHernia() ?? false;

        return match (strtolower(trim($salesStage))) {
            'gewonnen' => $isHernia ? PipelineStage::SALES_COMPLETE_HERNIA : PipelineStage::SALES_MET_SUCCES_AFGEROND,
            'verloren' => $isHernia ? PipelineStage::SALES_COMPLETE_NOT_SUCCESSFULLY_HERNIA : PipelineStage::SALES_NIET_SUCCESVOL_AFGEROND,
            default    => $isHernia ? PipelineStage::SALES_DOCTOR_ASSESSMENT_HERNIA : PipelineStage::SALES_IN_BEHANDELING,
        };
    }

    /**
     * Map SugarCRM sales_stage on an order row to an OrderItemStatus.
     * When the order has an imported examination datetime, non-lost rows become {@see OrderItemStatus::PLANNED}.
     */
    private function mapRowSalesStageToOrderItemStatus(string $salesStage, bool $hasScheduledExamination = false): OrderItemStatus
    {
        $base = match (strtolower(trim($salesStage))) {
            'gewonnen' => OrderItemStatus::WON,
            'verloren' => OrderItemStatus::LOST,
            default    => OrderItemStatus::NEW,
        };

        if ($base === OrderItemStatus::LOST) {
            return OrderItemStatus::LOST;
        }

        if ($hasScheduledExamination) {
            return OrderItemStatus::PLANNED;
        }

        return $base;
    }

    /**
     * Map SugarCRM reden_afvoeren_c to LostReason enum.
     * The SugarCRM values align with LostReason enum string values; unknown values map to null.
     */
    private function mapLostReason(?string $reden): ?LostReason
    {
        if ($reden === null || $reden === '') {
            return null;
        }

        return LostReason::tryFrom($reden);
    }

    /**
     * INVOICE purchase row: amounts from invoice matching (inv_purchase_*).
     * A component is ignored when the matching ink_*_status_c blocks invoice amounts
     * (e.g. geen, open, teontvangen — SuiteCRM / invoice-app export semantics).
     *
     * @return array<string, float>
     */
    private function orderItemInvoicePurchasePayloadFromSugarRow(object $row): array
    {
        return $this->buildPurchasePayloadFromSugarAmounts(
            $this->sugarInvoiceComponentAmount(data_get($row, 'inv_purchase_other_c'), data_get($row, 'ink_other_status_c')),
            $this->sugarInvoiceComponentAmount(data_get($row, 'inv_purchase_cardio_c'), data_get($row, 'ink_cardio_status_c')),
            $this->sugarInvoiceComponentAmount(data_get($row, 'inv_purchase_clinic_c'), data_get($row, 'ink_clinic_status_c')),
            $this->sugarInvoiceComponentAmount(data_get($row, 'inv_purchase_radio_c'), data_get($row, 'ink_radio_status_c')),
            $this->sugarInvoiceAggregatedTotalAmount($row),
            $this->sugarInvoiceComponentAmount(data_get($row, 'inv_purchase_doctor_c'), data_get($row, 'ink_doctor_status_c')),
        );
    }

    /**
     * inv_purchase_total_c must not drive afletteren when no invoice bucket is active.
     * Prefer ink_total_status_c (same semantics as invoice app); otherwise if every non-empty
     * per-bucket ink_*_status_c blocks invoice amounts, ignore the total so remainder does not land in misc.
     */
    private function sugarInvoiceAggregatedTotalAmount(object $row): ?float
    {
        $rawTotal = $this->sugarMoneyAmount(data_get($row, 'inv_purchase_total_c'));
        if ($rawTotal === null) {
            return null;
        }

        if (property_exists($row, 'ink_total_status_c')) {
            $totalStatus = $row->ink_total_status_c;
            if ($totalStatus !== null && $totalStatus !== '') {
                return $this->sugarInkStatusAllowsInvoiceAmount($totalStatus) ? $rawTotal : null;
            }
        }

        $statusFields = ['ink_other_status_c', 'ink_cardio_status_c', 'ink_clinic_status_c', 'ink_radio_status_c', 'ink_doctor_status_c'];
        $nonEmptyStatuses = [];
        foreach ($statusFields as $field) {
            if (! property_exists($row, $field)) {
                continue;
            }
            $value = $row->{$field};
            if ($value === null || $value === '') {
                continue;
            }
            $nonEmptyStatuses[] = $value;
        }

        if ($nonEmptyStatuses !== [] && $this->sugarInkEveryNonEmptyStatusBlocksInvoiceAmount($nonEmptyStatuses)) {
            return null;
        }

        return $rawTotal;
    }

    /**
     * @param  list<mixed>  $statusValues
     */
    private function sugarInkEveryNonEmptyStatusBlocksInvoiceAmount(array $statusValues): bool
    {
        foreach ($statusValues as $value) {
            if ($this->sugarInkStatusAllowsInvoiceAmount($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * When Sugar sends no values, returns explicit zeros so {@see OrderItem::resolvedPurchasePrice()}
     * uses the line only and does not fall back to catalog product / partner product prices.
     *
     * Aligns component sum to Sugar total (remainder in misc).
     *
     * @return array<string, float>
     */
    private function buildPurchasePayloadFromSugarAmounts(
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

    private function sugarInvoiceComponentAmount(mixed $amount, mixed $status): ?float
    {
        if (! $this->sugarInkStatusAllowsInvoiceAmount($status)) {
            return null;
        }

        return $this->sugarMoneyAmount($amount);
    }

    private function sugarInkStatusAllowsInvoiceAmount(mixed $status): bool
    {
        if ($status === null || $status === '') {
            return true;
        }

        $normalized = strtolower(str_replace(' ', '', trim((string) $status)));

        return ! in_array($normalized, ['geen', 'open', 'teontvangen'], true);
    }

    private function sugarMoneyAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    /**
     * Create {@see OrderPayment} rows from SuiteCRM order custom fields (pcrm_salesorder_cstm).
     */
    private function syncOrderPaymentsFromSugar(Order $order, object $record): void
    {
        if ($this->output->isVeryVerbose() && ! empty($record->betaal_status_c)) {
            $this->line("  Sugar betaal_status_c={$record->betaal_status_c} (order {$record->id})");
        }

        $advance = $this->sugarMoneyAmount($record->betaald_vooruit_c ?? null);
        if ($advance !== null && $advance > 0) {
            $enteredDate = $this->parseSugarUtcDate($record->datum_betaling_vr_c ?? null);

            OrderPayment::create([
                'order_id' => $order->id,
                'amount'   => $advance,
                'type'     => PaymentType::ADVANCE,
                'method'   => PaymentMethod::BANK,
                'paid_at'  => $enteredDate ? substr($enteredDate, 0, 10) : null,
                'currency' => 'EUR',
            ]);
        }

        $clinic = $this->sugarMoneyAmount($record->betaald_kliniek_c ?? null);
        if ($clinic !== null && $clinic > 0) {
            $examDate = $this->parseSugarUtcDate($record->datum_onderzoek_1 ?? null);

            OrderPayment::create([
                'order_id' => $order->id,
                'amount'   => $clinic,
                'type'     => PaymentType::PAYED_IN_CLINIC,
                'method'   => $this->paymentMethodFromSugarPinContant($record->pin_contant_c ?? null),
                'paid_at'  => $examDate ? substr($examDate, 0, 10) : null,
                'currency' => 'EUR',
            ]);
        }

        if ($this->output->isVerbose()) {
            $this->maybeLogOpenstaandVersusOrderTotal($order, $record, ($advance ?? 0.0) + ($clinic ?? 0.0));
        }
    }

    /**
     * Sugar pin_contant_c: tekst ("pin"/"contant") of vlag; default PIN bij twijfel.
     */
    private function paymentMethodFromSugarPinContant(mixed $value): PaymentMethod
    {
        if ($value === null || $value === '') {
            return PaymentMethod::PIN;
        }

        $s = strtolower(trim((string) $value));

        if ($s === '' || is_numeric($s)) {
            return PaymentMethod::PIN;
        }

        return match (true) {
            str_contains($s, 'contant'),
            str_contains($s, 'cash'),
            $s === 'c' => PaymentMethod::CASH,
            str_contains($s, 'pin'),
            str_contains($s, 'debit'),
            $s === 'p' => PaymentMethod::PIN,
            default    => PaymentMethod::PIN,
        };
    }

    private function maybeLogOpenstaandVersusOrderTotal(Order $order, object $record, float $importedPaidSum): void
    {
        $openstaand = $this->sugarMoneyAmount($record->openstaand_c ?? null);
        if ($openstaand === null) {
            return;
        }

        $total = round((float) ($order->total_price ?? 0), 2);
        if ($total <= 0) {
            return;
        }

        $expectedOutstanding = round(max(0, $total - $importedPaidSum), 2);
        if (abs($openstaand - $expectedOutstanding) > 0.02) {
            $this->warn(sprintf(
                'Order %s: openstaand_c=%s vs (total_price - imported payments)≈%s — check data.',
                $order->external_id ?? $order->id,
                $openstaand,
                $expectedOutstanding
            ));
        }
    }

    /**
     * Sugar order rows can reference contacts not on the CRM lead; attach them and create anamnesis via {@see SalesLead::attachPersons}.
     */
    private function mapSugarUserToId(?string $sugarUserId): ?int
    {
        if (empty($sugarUserId)) {
            return null;
        }

        return User::where('external_id', $sugarUserId)->first()?->id;
    }

    private function attachSugarOrderPersonsToSalesLead(SalesLead $salesLead, Collection $personsByExternalId): void
    {
        if ($personsByExternalId->isEmpty()) {
            return;
        }

        $alreadyAttached = $salesLead->persons()->pluck('persons.id')->all();
        $extraIds = $personsByExternalId->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->diff(array_map('intval', $alreadyAttached))
            ->values()
            ->all();

        if ($extraIds !== []) {
            $salesLead->attachPersons($extraIds);
        }
    }

    /**
     * @return array{street: ?string, house_number: ?string, house_number_suffix: ?string}
     */
    private function splitSugarBillingStreetForOrganization(?string $raw): array
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim((string) ($raw ?? '')));
        $collapsed = is_string($collapsed) ? trim($collapsed) : '';
        if ($collapsed === '') {
            return ['street' => null, 'house_number' => null, 'house_number_suffix' => null];
        }

        /**
         * Last segment looks like house number when billing_address_street combines
         * name + digits (often Dutch): "Schermerhoek 500", optional "-B"/"42a"-style suffix.
         */
        if (preg_match(
            '/^(.+?)\s+(\d{1,6})(?:[-\s]([a-zA-Z0-9]{1,10})|([a-zA-Z]{1,10}))?$/u',
            $collapsed,
            $matches
        )) {
            $streetPart = trim($matches[1]);
            if ($streetPart !== '') {
                $suffix = $matches[3] ?? '';
                if ($suffix === '' && (($matches[4] ?? '') !== '')) {
                    $suffix = $matches[4];
                }

                return [
                    'street'               => $streetPart,
                    'house_number'         => $matches[2],
                    'house_number_suffix'  => $suffix !== '' ? substr($suffix, 0, 10) : null,
                ];
            }
        }

        return [
            'street'              => $collapsed,
            'house_number'        => $this->sugarOrganizationFallbackHouseNumber(),
            'house_number_suffix' => null,
        ];
    }

    /**
     * @return non-empty-string
     */
    private function sugarOrganizationFallbackHouseNumber(): string
    {
        return '9999';
    }

    private function nonEmptyTrimmedString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $t = trim((string) $value);

        return $t === '' ? null : $t;
    }

    /**
     * Builds address data for CRM {@see Address} when Sugar billing fields allow it.
     *
     * @return array<string, mixed>|null
     */
    private function organizationAddressPayloadFromSugarAccountRecord(object $record): ?array
    {
        $postalRaw = $this->nonEmptyTrimmedString(data_get($record, 'account_billing_postalcode'));
        if ($postalRaw === null) {
            return null;
        }

        $streetRaw = $this->nonEmptyTrimmedString(data_get($record, 'account_billing_street')) ?? '';

        $split = $this->splitSugarBillingStreetForOrganization($streetRaw !== '' ? $streetRaw : null);
        $street = (($split['street'] ?? '') !== '') ? ($split['street'] ?? '') : 'Onbekend';

        $suffixFromCstm = $this->nonEmptyTrimmedString(data_get($record, 'account_billing_huisnr_toevoeging_c'));
        $suffixFromCstm = $suffixFromCstm !== null ? substr($suffixFromCstm, 0, 10) : null;
        $suffix = $suffixFromCstm ?? ($split['house_number_suffix'] ?? null);

        $houseFromCstm = $this->nonEmptyTrimmedString(data_get($record, 'account_billing_huisnr_c'));
        if ($houseFromCstm !== null) {
            $houseNumber = $houseFromCstm;
        } else {
            $houseNumber = (($split['house_number'] ?? '') !== '')
                ? ($split['house_number'] ?? '')
                : $this->sugarOrganizationFallbackHouseNumber();
        }

        $cityBilling = $this->nonEmptyTrimmedString(data_get($record, 'account_billing_city'));
        $cityShipping = $this->nonEmptyTrimmedString(data_get($record, 'account_shipping_city'));
        $city = $cityBilling ?? $cityShipping;

        return [
            'street'               => $street,
            'house_number'         => $houseNumber,
            'house_number_suffix'  => $suffix,
            'postal_code'          => preg_replace('/\s+/', '', $postalRaw),
            'city'                 => $city,
            'state'                => $this->nonEmptyTrimmedString(data_get($record, 'account_billing_state')),
            'country'              => $this->nonEmptyTrimmedString(data_get($record, 'account_billing_country')),
        ];
    }

    /**
     * One-line Sugar org address preview for zakelijk orders in dry-run.
     */
    private function dryRunSugarOrganizationAddressHint(object $record): ?string
    {
        if (! $this->isSugarBusinessOrder($record)) {
            return null;
        }

        $payload = $this->organizationAddressPayloadFromSugarAccountRecord($record);
        if ($payload === null) {
            return 'org-adres: (geen postcode in Sugar)';
        }

        return 'org-adres: '.trim(implode(', ', array_filter([
            implode(' ', array_filter([
                $payload['street'] ?? '',
                ($payload['house_number'] ?? '')
                    .(! empty($payload['house_number_suffix']) ? '-'.$payload['house_number_suffix'] : ''),
            ])),
            trim(($payload['postal_code'] ?? '').' '.($payload['city'] ?? '')),
            $payload['country'] ?? null,
        ])));
    }

    private function isSugarBusinessOrder(object $record): bool
    {
        return ! empty($record->account_name)
            && ! str_starts_with($record->account_name, 'Fam ');
    }

    private function stripOrderNumberFromName(string $name, ?string $orderNum): string
    {
        if ($orderNum !== null && $orderNum !== '' && str_starts_with($name, $orderNum)) {
            $stripped = ltrim(substr($name, strlen($orderNum)), " \t_-");

            return $stripped !== '' ? $stripped : $name;
        }

        return $name;
    }

    private function formatDryRunOrderTypeLabel(object $record): string
    {
        if ($this->isSugarBusinessOrder($record)) {
            return 'Zakelijk ('.$record->account_name.')';
        }

        return 'Particulier';
    }

    private function importTasksForOrders(Collection $records, string $connection): void
    {
        $sugarOrderIds = $records->pluck('id')->filter()->values()->all();
        if (empty($sugarOrderIds)) {
            return;
        }

        $parentType = (string) $this->option('tasks-parent-type');
        $this->info('Importing tasks for '.count($sugarOrderIds)." order(s) (parent_type={$parentType})...");

        $activityImporter = new ActivityImporter($this, $connection);

        try {
            $taskActivities = $activityImporter->extractTaskActivitiesForOrders($sugarOrderIds, $parentType);
        } catch (Exception $e) {
            $this->warn('Task import skipped: '.$e->getMessage());

            return;
        }

        // Import tasks for every matching order regardless of pipeline stage: these are
        // historical records and a completed (won/lost) order can still have relevant tasks.
        $ordersByExternalId = Order::whereIn('external_id', $sugarOrderIds)
            ->get()
            ->keyBy('external_id');

        $allTaskIds = collect($taskActivities)->flatten()->pluck('id')->filter()->values()->all();
        $existingActivities = ! empty($allTaskIds)
            ? Activity::whereIn('external_id', $allTaskIds)->get()->keyBy('external_id')
            : collect();

        $totalImported = 0;
        $totalSkipped = 0;

        foreach ($sugarOrderIds as $sugarOrderId) {
            $order = $ordersByExternalId->get($sugarOrderId);
            if (! $order) {
                continue;
            }

            $stats = $activityImporter->importTaskActivitiesForOrder($order, $taskActivities, $existingActivities);
            $totalImported += $stats['imported'];
            $totalSkipped += $stats['skipped'];
        }

        $this->info("Tasks: imported={$totalImported}, skipped={$totalSkipped}");

        $this->importOrderActivities($activityImporter, $sugarOrderIds, $parentType);
    }

    private function importTasksForExistingOrders(string $connection): void
    {
        $parentType = (string) $this->option('tasks-parent-type');

        $sugarOrderIds = Order::whereNotNull('external_id')
            ->pluck('external_id')
            ->filter()
            ->values()
            ->all();

        if (empty($sugarOrderIds)) {
            $this->info('No orders with external_id found.');

            return;
        }

        $this->info('Importing tasks for '.count($sugarOrderIds)." existing order(s) (parent_type={$parentType})...");

        $activityImporter = new ActivityImporter($this, $connection);

        try {
            $taskActivities = $activityImporter->extractTaskActivitiesForOrders($sugarOrderIds, $parentType);
        } catch (Exception $e) {
            $this->warn('Task import skipped: '.$e->getMessage());

            return;
        }

        $ordersByExternalId = Order::whereIn('external_id', $sugarOrderIds)->get()->keyBy('external_id');

        $allTaskIds = collect($taskActivities)->flatten()->pluck('id')->filter()->values()->all();
        $existingActivities = ! empty($allTaskIds)
            ? Activity::whereIn('external_id', $allTaskIds)->get()->keyBy('external_id')
            : collect();

        $totalImported = 0;
        $totalSkipped = 0;

        foreach ($sugarOrderIds as $sugarOrderId) {
            $order = $ordersByExternalId->get($sugarOrderId);
            if (! $order) {
                continue;
            }

            $stats = $activityImporter->importTaskActivitiesForOrder($order, $taskActivities, $existingActivities);
            $totalImported += $stats['imported'];
            $totalSkipped += $stats['skipped'];
        }

        $this->info("Tasks: imported={$totalImported}, skipped={$totalSkipped}");

        $this->importOrderActivities($activityImporter, $sugarOrderIds, $parentType);
    }

    /**
     * Import emails, notes and calls that are linked directly to a Sugar order
     * (parent_type = PCRM_SalesOrder). These are historical records, so — unlike
     * task import — they are imported for every matching order regardless of the
     * order's pipeline stage (won/lost included).
     *
     * @param  string[]  $sugarOrderIds  Sugar order UUIDs
     */
    private function importOrderActivities(
        ActivityImporter $activityImporter,
        array $sugarOrderIds,
        string $parentType,
    ): void {
        $sugarOrderIds = array_values(array_filter($sugarOrderIds));
        if (empty($sugarOrderIds)) {
            return;
        }

        $ordersByExternalId = Order::whereIn('external_id', $sugarOrderIds)->get()->keyBy('external_id');
        if ($ordersByExternalId->isEmpty()) {
            return;
        }

        // Emails
        try {
            $emailActivities = $activityImporter->extractEmailActivitiesForOrders($sugarOrderIds, $parentType);
            $ids = collect($emailActivities)->flatten(1)->pluck('id')->filter()->values()->all();
            $existingEmails = ! empty($ids)
                ? Email::whereIn('unique_id', $ids)->get()->keyBy('unique_id')
                : collect();

            $imported = 0;
            $skipped = 0;
            foreach ($sugarOrderIds as $sugarOrderId) {
                $order = $ordersByExternalId->get($sugarOrderId);
                if (! $order) {
                    continue;
                }
                $stats = $activityImporter->importEmailsForOrder($order, $emailActivities, $existingEmails);
                $imported += $stats['imported'];
                $skipped += $stats['skipped'];
            }
            $this->info("Order emails: imported={$imported}, skipped={$skipped}");
        } catch (Exception $e) {
            $this->warn('Order email import skipped: '.$e->getMessage());
        }

        // Notes
        try {
            $noteActivities = $activityImporter->extractNoteActivitiesForOrders($sugarOrderIds, $parentType);
            $ids = collect($noteActivities)->flatten(1)->pluck('id')->filter()->values()->all();
            $existingNotes = ! empty($ids)
                ? Activity::whereIn('external_id', $ids)->get()->keyBy('external_id')
                : collect();

            $imported = 0;
            $skipped = 0;
            foreach ($sugarOrderIds as $sugarOrderId) {
                $order = $ordersByExternalId->get($sugarOrderId);
                if (! $order) {
                    continue;
                }
                $stats = $activityImporter->importNoteActivitiesForOrder($order, $noteActivities, $existingNotes);
                $imported += $stats['imported'];
                $skipped += $stats['skipped'];
            }
            $this->info("Order notes: imported={$imported}, skipped={$skipped}");
        } catch (Exception $e) {
            $this->warn('Order note import skipped: '.$e->getMessage());
        }

        // Calls
        try {
            $callActivities = $activityImporter->extractCallActivitiesForOrders($sugarOrderIds, $parentType);
            $ids = collect($callActivities)->flatten(1)->pluck('id')->filter()->values()->all();
            $existingCalls = ! empty($ids)
                ? Activity::whereIn('external_id', $ids)->get()->keyBy('external_id')
                : collect();

            $imported = 0;
            $skipped = 0;
            foreach ($sugarOrderIds as $sugarOrderId) {
                $order = $ordersByExternalId->get($sugarOrderId);
                if (! $order) {
                    continue;
                }
                $stats = $activityImporter->importCallActivitiesForOrder($order, $callActivities, $existingCalls);
                $imported += $stats['imported'];
                $skipped += $stats['skipped'];
            }
            $this->info("Order calls: imported={$imported}, skipped={$skipped}");
        } catch (Exception $e) {
            $this->warn('Order call import skipped: '.$e->getMessage());
        }
    }
}
