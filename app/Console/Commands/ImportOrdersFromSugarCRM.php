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
use App\Models\SalesLead;
use App\Repositories\SalesLeadRepository;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Product\Models\Product;

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
                            {--import-leads : Import missing linked leads (with persons) before importing orders}
                            {--dry-run : Show what would be imported without actually importing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import orders from SugarCRM database, creating SalesLeads and OrderItems per order';

    public function __construct(
        private readonly SalesLeadRepository $salesLeadRepository
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
        $this->info('Starting order import from SugarCRM...');
        $this->infoV("Connection: {$connection}");
        $this->infoV("Table: {$table}");
        if (! empty($orderIds)) {
            $this->infoV('Order numbers: '.(is_array($orderIds) ? implode(', ', $orderIds) : $orderIds));
        } else {
            $this->infoV("Limit: {$limit}");
        }
        $this->infoV('Dry run: '.($dryRun ? 'Yes' : 'No'));

        try {
            return $this->executeImport($dryRun, function () use ($connection, $table, $limit, $orderIds, $dryRun) {
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
                        'cstm.betaald_kliniek_c',
                        'cstm.openstaand_c',
                        'cstm.betaal_status_c',
                        'cstm.pin_contant_c',
                        'lead_rel.sugar_lead_id',
                    ])
                    ->where('so.deleted', 0)
                    ->whereNotNull('so.id')
                    ->where('so.id', '!=', '')
                    ->where('so.date_entered', '>=', '2025-01-01 00:00:00');

                if (! empty($orderIds)) {
                    if (is_array($orderIds)) {
                        $orderIds = implode(' ', $orderIds);
                    }
                    $normalizedIds = preg_split('/[\s,]+/', (string) $orderIds, -1, PREG_SPLIT_NO_EMPTY);
                    $sql = $sql->whereIn('so.order_num', $normalizedIds);
                } else {
                    $sql = $sql->orderBy('so.date_entered', 'desc');
                    if ($limit > 0) {
                        $sql = $sql->limit($limit);
                    }
                }

                $this->infoVV($sql->toRawSql());

                try {
                    $records = $sql->get();
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

                $this->importRecords($records, $connection);
            });
        } catch (Exception $e) {
            $this->error('Error: '.$e->getMessage());
            Log::error('SugarCRM order import failed', [
                'error'      => $e->getMessage(),
                'connection' => $connection,
                'table'      => $table,
            ]);

            return 1;
        }
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

        $orderIds = $records->pluck('id')->all();
        $rowsByOrder = $this->fetchOrderRows($connection, $orderIds);

        // Orders table
        $headers = ['External ID', 'Order#', 'Name', 'Amount', 'Stage', 'Lost Reason', 'First exam', 'Rows', 'Status'];
        $rows = [];

        foreach ($records as $record) {
            $orderRows = $rowsByOrder->get($record->id, collect());
            $alreadyDone = Order::where('external_id', $record->id)->exists();

            $rows[] = [
                substr($record->id, 0, 8).'…',
                $record->order_num ?? 'N/A',
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
        $newCount = collect($rows)->filter(fn ($r) => $r[7] === '✗ new')->count();
        $this->info("Would import {$newCount} orders");

        // SalesLead preview for new orders only
        $newRecords = $records->filter(fn ($r) => ! Order::where('external_id', $r->id)->exists());
        if ($newRecords->isEmpty()) {
            return;
        }

        $this->info("\n=== SALESLEAD PREVIEW (new orders only) ===");

        $slHeaders = ['Order#', 'SalesLead name', 'Sales stage', 'Lead ref (CRM)', 'Lead #', 'Lead name', 'Lead found?'];
        $slRows = [];

        foreach ($newRecords as $record) {
            $crmLead = ! empty($record->sugar_lead_id)
                ? Lead::with('department')->where('external_id', $record->sugar_lead_id)->first()
                : null;

            $salesStage = $this->mapSalesStageToSalesPipelineStage($record->sales_stage ?? '', $crmLead?->department);

            $slRows[] = [
                $record->order_num ?? 'N/A',
                $record->name ?? 'N/A',
                $salesStage->label(),
                $record->sugar_lead_id ? substr($record->sugar_lead_id, 0, 8).'…' : '—',
                $crmLead ? '#'.$crmLead->id : '—',
                $crmLead ? trim($crmLead->first_name.' '.$crmLead->last_name) : '—',
                $crmLead ? '✓' : '✗ missing',
            ];
        }

        $this->table($slHeaders, $slRows);

        $missing = collect($slRows)->filter(fn ($r) => $r[6] === '✗ missing')->count();
        if ($missing > 0) {
            $this->warn("{$missing} order(s) have no matching CRM Lead — SalesLead will be created with lead_id=null.");
        }
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

                // Collect unique contact IDs and product template IDs from rows
                $contactIds = $orderRows->pluck('contact_id')->filter()->unique()->values()->all();
                $productIds = $orderRows->pluck('producttemplate_id_c')->filter()->unique()->values()->all();

                // Look up existing persons and products
                $personsByExternalId = ! empty($contactIds)
                    ? Person::whereIn('external_id', $contactIds)->get()->keyBy('external_id')
                    : collect();

                $productsByExternalId = ! empty($productIds)
                    ? Product::whereIn('external_id', $productIds)->get()->keyBy('external_id')
                    : collect();

                $productsByName = $this->productsByNameForSugarRows($orderRows);

                // Look up the imported CRM Lead via the Sugar lead UUID
                $crmLead = ! empty($record->sugar_lead_id)
                    ? Lead::with('department')->where('external_id', $record->sugar_lead_id)->first()
                    : null;

                $department = $crmLead?->department;

                // Derive pipeline stages and lost reason
                $orderStage = $this->mapSalesStageToOrderPipelineStage($record->sales_stage ?? '', $department);
                $salesStage = $this->mapSalesStageToSalesPipelineStage($record->sales_stage ?? '', $department);
                $lostReason = $this->mapLostReason($record->reden_afvoeren_c ?? null);
                $closedAt = $this->parseSugarDate($record->date_closed);

                if ($crmLead === null) {
                    $this->warn("Order {$record->id}: no matching CRM Lead found for sugar_lead_id=".($record->sugar_lead_id ?? 'null').'. SalesLead will have lead_id=null.');
                }

                DB::transaction(function () use (
                    $record,
                    $orderRows,
                    $personsByExternalId,
                    $productsByExternalId,
                    $productsByName,
                    $salesStage,
                    $orderStage,
                    $lostReason,
                    $closedAt,
                    $crmLead
                ) {
                    $timestamps = [
                        'created_at' => $this->parseSugarDate($record->date_entered),
                        'updated_at' => $this->parseSugarDate($record->date_modified),
                    ];

                    if ($crmLead !== null) {
                        // Same path as createFromWonLead (copyFromLead → persons + anamnesis), without auto-created order
                        $salesLead = $this->salesLeadRepository->createFromLeadForOrderImport($crmLead, [
                            'name'              => $record->name ?? "Order {$record->order_num}",
                            'pipeline_stage_id' => $salesStage->id(),
                            'lost_reason'       => $lostReason,
                            'closed_at'         => $closedAt,
                        ], $timestamps);

                        $this->attachSugarOrderPersonsToSalesLead($salesLead, $personsByExternalId);
                    } else {
                        $salesLead = $this->createEntityWithTimestamps(SalesLead::class, [
                            'name'              => $record->name ?? "Order {$record->order_num}",
                            'pipeline_stage_id' => $salesStage->id(),
                            'lost_reason'       => $lostReason,
                            'closed_at'         => $closedAt,
                            'lead_id'           => null,
                        ], $timestamps);

                        if ($personsByExternalId->isNotEmpty()) {
                            foreach ($personsByExternalId->pluck('id') as $personId) {
                                DB::table('saleslead_persons')->insertOrIgnore([
                                    'saleslead_id' => $salesLead->id,
                                    'person_id'    => $personId,
                                ]);
                            }
                        }
                    }

                    // Create the Order
                    $order = $this->createEntityWithTimestamps(Order::class, [
                        'external_id'          => $record->id,
                        'order_number'         => $record->order_num ?? null,
                        'title'                => $record->name ?? "Order {$record->order_num}",
                        'total_price'          => $record->amount ?? 0,
                        'pipeline_stage_id'    => $orderStage->id(),
                        'lost_reason'          => $lostReason,
                        'closed_at'            => $closedAt,
                        'first_examination_at' => $this->parseSugarExaminationAt($record->datum_onderzoek_1, $record->aankomsttijd_c),
                        'sales_lead_id'        => $salesLead->id,
                        'combine_order'        => (bool) ($record->op_een_factuur_c ?? false),
                    ], [
                        'created_at' => $this->parseSugarDate($record->date_entered),
                        'updated_at' => $this->parseSugarDate($record->date_modified),
                    ]);

                    $itemsCreated = 0;

                    // Create OrderItems
                    foreach ($orderRows as $row) {
                        $person = $row->contact_id ? ($personsByExternalId->get($row->contact_id) ?? null) : null;
                        $product = $this->resolveProductForSugarRow($row, $productsByExternalId, $productsByName);

                        if ($product === null) {
                            $this->warn(sprintf(
                                'Skipping Sugar order row (no CRM product): order=%s row=%s name=%s template_id=%s',
                                $record->id,
                                $row->id ?? '',
                                $row->name ?? '',
                                $row->producttemplate_id_c ?? ''
                            ));

                            continue;
                        }

                        $orderItem = OrderItem::create([
                            'order_id'    => $order->id,
                            'person_id'   => $person?->id,
                            'product_id'  => $product->id,
                            'name'        => $row->name ?? null,
                            'total_price' => $row->sales_price ?? 0,
                            'quantity'    => 1,
                            'status'      => $this->mapRowSalesStageToOrderItemStatus($row->sales_stage ?? ''),
                        ]);

                        $purchasePayload = $this->orderItemPurchasePayloadFromSugarRow($row);
                        if ($purchasePayload !== null) {
                            $orderItem->invoicePurchasePrice()->updateOrCreate(
                                ['type' => PurchasePriceType::INVOICE],
                                array_merge(['type' => PurchasePriceType::INVOICE], $purchasePayload)
                            );
                            $orderItem->purchasePrice()->updateOrCreate(
                                ['type' => PurchasePriceType::MAIN],
                                array_merge(['type' => PurchasePriceType::MAIN], $purchasePayload)
                            );
                        }

                        $itemsCreated++;
                    }

                    if ($itemsCreated === 0 && $orderRows->isNotEmpty()) {
                        $this->warn("Order {$record->id}: imported with 0 line items (no matching products for any row).");
                    }

                    $this->syncOrderPaymentsFromSugar($order, $record);
                });

                $imported++;
                $bar->advance();
            } catch (Exception $e) {
                $errors++;
                $this->logError('Failed to import order', [
                    'record_id'    => $record->id ?? 'unknown',
                    'record_label' => $record->name ?? 'unknown',
                    'error'        => $e->getMessage(),
                ]);
                if (count($firstErrors) < 5) {
                    $firstErrors[] = [
                        'id'      => $record->id ?? 'unknown',
                        'message' => $e->getMessage(),
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
                $this->warn("  - ID={$err['id']}: {$err['message']}");
            }
        }
    }

    /**
     * Batch-fetch all order rows for the given SugarCRM order UUIDs.
     * Returns a Collection keyed by the SugarCRM order UUID, each value being
     * a Collection of row objects (one row per contact, due to the LEFT JOIN).
     */
    private function fetchOrderRows(string $connection, array $orderIds): Collection
    {
        if (empty($orderIds)) {
            return collect();
        }

        $rows = DB::connection($connection)
            ->table('pcrm_salesoalesorderrow_c as rel')
            ->join('pcrm_salesorderrow as sor', 'sor.id', '=', 'rel.pcrm_sales509drderrow_idb')
            ->leftJoin('pcrm_salesorderrow_cstm as row_cstm', 'row_cstm.id_c', '=', 'sor.id')
            ->leftJoin('pcrm_salesorow_contacts_c as rc', function ($join) {
                $join->on('rc.pcrm_sales80b3rderrow_idb', '=', 'sor.id')
                    ->where('rc.deleted', '=', 0);
            })
            ->select([
                'rel.pcrm_salesb9a7esorder_ida as order_id',
                'sor.id',
                'sor.name',
                'sor.sales_price',
                'sor.sales_stage',
                'sor.resource_type',
                'sor.producttemplate_id_c',
                'rc.pcrm_sales4bd9ontacts_ida as contact_id',
                'row_cstm.inv_purchase_other_c as inv_purchase_other_c',
                'row_cstm.inv_purchase_cardio_c as inv_purchase_cardio_c',
                'row_cstm.inv_purchase_clinic_c as inv_purchase_clinic_c',
                'row_cstm.inv_purchase_radio_c as inv_purchase_radio_c',
                'row_cstm.inv_purchase_total_c as inv_purchase_total_c',
            ])
            ->where('sor.deleted', 0)
            ->whereIn('rel.pcrm_salesb9a7esorder_ida', $orderIds)
            ->get();

        return $rows->groupBy('order_id');
    }

    /**
     * Combine datum_onderzoek_1 (date) with aankomsttijd_c (HH:MM varchar) into a full datetime string.
     * Falls back to date-only when the time field is absent or malformed.
     */
    private function parseSugarExaminationAt(mixed $date, mixed $time): ?string
    {
        $parsed = $this->parseSugarDate($date);
        if ($parsed === null) {
            return null;
        }

        // parseSugarDate returns 'Y-m-d H:i:s'; take only the date part before appending HH:MM time
        $dateOnly = substr($parsed, 0, 10);

        // Accept HH:MM, H:MM, HH.MM, H.MM (dot or colon separator, optional leading zero)
        if (! empty($time) && preg_match('/^(\d{1,2})[.:](\d{2})$/', trim((string) $time), $m)) {
            return $dateOnly.' '.str_pad($m[1], 2, '0', STR_PAD_LEFT).':'.$m[2].':00';
        }

        return $parsed;
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
     */
    private function mapRowSalesStageToOrderItemStatus(string $salesStage): OrderItemStatus
    {
        return match (strtolower(trim($salesStage))) {
            'gewonnen' => OrderItemStatus::WON,
            'verloren' => OrderItemStatus::LOST,
            default    => OrderItemStatus::NEW,
        };
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
     * Build order-item purchase price attributes from SuiteCRM row custom fields (authoritative inkoop),
     * or null if nothing to import.
     *
     * Aligns component sum to Sugar total (remainder in misc). Uses explicit 0.0 for empty buckets so
     * {@see OrderItem::resolvedPurchasePrice()} does not fall back to product/partner defaults.
     *
     * @return array<string, float>|null
     */
    private function orderItemPurchasePayloadFromSugarRow(object $row): ?array
    {
        $miscRaw = $this->sugarMoneyAmount($row->inv_purchase_other_c ?? null);
        $cardio = $this->sugarMoneyAmount($row->inv_purchase_cardio_c ?? null);
        $clinic = $this->sugarMoneyAmount($row->inv_purchase_clinic_c ?? null);
        $radio = $this->sugarMoneyAmount($row->inv_purchase_radio_c ?? null);
        $totalFromSugar = $this->sugarMoneyAmount($row->inv_purchase_total_c ?? null);

        $hasComponent = $miscRaw !== null || $cardio !== null || $clinic !== null || $radio !== null;
        $hasTotal = $totalFromSugar !== null;

        if (! $hasComponent && ! $hasTotal) {
            return null;
        }

        $sumSugarComponents = ($miscRaw ?? 0.0) + ($cardio ?? 0.0) + ($clinic ?? 0.0) + ($radio ?? 0.0);
        $total = $totalFromSugar !== null ? $totalFromSugar : round($sumSugarComponents, 2);

        $remainder = round($total - $sumSugarComponents, 2);
        $misc = round(($miscRaw ?? 0.0) + $remainder, 2);

        return [
            'purchase_price_misc'        => $misc,
            'purchase_price_doctor'      => 0.0,
            'purchase_price_cardiology'  => $cardio ?? 0.0,
            'purchase_price_clinic'      => $clinic ?? 0.0,
            'purchase_price_radiology'   => $radio ?? 0.0,
            'purchase_price'             => $total,
        ];
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

        $paidAt = $this->orderPaymentPaidAtFromSugar($order, $record);

        $advance = $this->sugarMoneyAmount($record->betaald_vooruit_c ?? null);
        if ($advance !== null && $advance > 0) {
            OrderPayment::create([
                'order_id' => $order->id,
                'amount'   => $advance,
                'type'     => PaymentType::ADVANCE,
                'method'   => PaymentMethod::BANK,
                'paid_at'  => $paidAt,
                'currency' => 'EUR',
            ]);
        }

        $clinic = $this->sugarMoneyAmount($record->betaald_kliniek_c ?? null);
        if ($clinic !== null && $clinic > 0) {
            OrderPayment::create([
                'order_id' => $order->id,
                'amount'   => $clinic,
                'type'     => PaymentType::PAYED_IN_CLINIC,
                'method'   => $this->paymentMethodFromSugarPinContant($record->pin_contant_c ?? null),
                'paid_at'  => $paidAt,
                'currency' => 'EUR',
            ]);
        }

        if ($this->output->isVerbose()) {
            $this->maybeLogOpenstaandVersusOrderTotal($order, $record, ($advance ?? 0.0) + ($clinic ?? 0.0));
        }
    }

    private function orderPaymentPaidAtFromSugar(Order $order, object $record): ?string
    {
        if ($order->closed_at) {
            return $order->closed_at->format('Y-m-d');
        }

        $entered = $this->parseSugarDate($record->date_entered ?? null);

        return $entered ? substr($entered, 0, 10) : null;
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
     * Load CRM products keyed by exact {@see Product::name} for all non-empty Sugar row labels.
     *
     * @return Collection<string, Product>
     */
    private function productsByNameForSugarRows(Collection $orderRows): Collection
    {
        $names = $orderRows->pluck('name')
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($names === []) {
            return collect();
        }

        return Product::query()
            ->whereIn('name', $names)
            ->get()
            ->keyBy(fn (Product $p) => $p->name);
    }

    /**
     * Resolve CRM product: Sugar product template UUID → {@see Product::external_id}, else exact row name → {@see Product::name}.
     *
     * @param  Collection<string, Product>  $productsByExternalId
     * @param  Collection<string, Product>  $productsByName
     */
    private function resolveProductForSugarRow(object $row, Collection $productsByExternalId, Collection $productsByName): ?Product
    {
        if (! empty($row->producttemplate_id_c)) {
            $byId = $productsByExternalId->get($row->producttemplate_id_c);
            if ($byId !== null) {
                return $byId;
            }
        }

        $label = trim((string) ($row->name ?? ''));
        if ($label !== '') {
            return $productsByName->get($label);
        }

        return null;
    }

    /**
     * Sugar order rows can reference contacts not on the CRM lead; attach them and create anamnesis via {@see SalesLead::attachPersons}.
     */
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
}
