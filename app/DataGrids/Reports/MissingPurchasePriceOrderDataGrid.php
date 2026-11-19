<?php

namespace App\DataGrids\Reports;

use App\Enums\Departments;
use App\Enums\OrderItemStatus;
use App\Enums\PipelineDefaultKeys;
use App\Enums\PurchasePriceType;
use App\Models\OrderItem;
use App\Models\PurchasePrice;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

/**
 * Orders met gewonnen, planbare orderregels zonder inkoopprijs (rapport "Orders zonder inkoopprijs").
 * Kolomfilters `plannable` en `sugar_import` staan standaard op "Ja" resp. "Nee" zolang er geen eigen
 * filter op die kolom is gekozen; kies beide waarden om alles te zien.
 */
class MissingPurchasePriceOrderDataGrid extends DataGrid
{
    public const PLANNABLE_TYPES = ['mri scanner', 'ct scanner', 'pet ct scanner', 'artsen', 'cardiologie', 'rontgen'];

    protected $sortColumn = 'created_at';

    protected $sortOrder = 'asc';

    public function prepareQueryBuilder(): Builder
    {
        $requested = request('filters', []);

        $resourceTypeId = <<<'SQL'
            COALESCE(
                order_items.resource_type_id,
                products.resource_type_id,
                (SELECT pp.resource_type_id FROM partner_products pp
                  WHERE pp.product_id = products.id AND pp.deleted_at IS NULL
                  ORDER BY pp.id LIMIT 1)
            )
        SQL;

        $queryBuilder = DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('resource_types', 'resource_types.id', '=', DB::raw($resourceTypeId))
            ->leftJoin('purchase_prices', function ($join) {
                $join->on('purchase_prices.priceable_id', '=', 'order_items.id')
                    ->where('purchase_prices.priceable_type', (new OrderItem)->getMorphClass())
                    ->where('purchase_prices.type', PurchasePriceType::MAIN->value);
            })
            ->leftJoin('lead_pipeline_stages', 'lead_pipeline_stages.id', '=', 'orders.pipeline_stage_id')
            ->where('order_items.status', OrderItemStatus::WON->value)
            // product zonder (actief) partnerproduct = geen inkoop verwacht
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('partner_products')
                ->whereColumn('partner_products.product_id', 'order_items.product_id')
                ->where('partner_products.active', true)
                ->whereNull('partner_products.deleted_at'))
            // standaard: alleen planbaar en geen Sugar CRM-import (tenzij zelf gefilterd op die kolom)
            ->when(! isset($requested['plannable']), fn ($q) => $q->where(new Expression($this->plannableSql()), 'Ja'))
            ->when(! isset($requested['sugar_import']), fn ($q) => $q->where(new Expression($this->sugarSql()), 'Nee'))
            ->where(function ($query) {
                foreach (['purchase_price', ...PurchasePrice::priceableFieldNames()] as $column) {
                    $query->whereRaw("COALESCE(purchase_prices.{$column}, 0) = 0");
                }
            })
            ->groupBy('orders.id', 'orders.order_number', 'orders.title', 'orders.created_at', 'orders.total_price', 'lead_pipeline_stages.name', 'lead_pipeline_stages.lead_pipeline_id')
            ->select(
                'orders.id',
                'orders.order_number',
                'orders.title',
                'orders.created_at',
                'orders.total_price',
                'lead_pipeline_stages.name as status',
                DB::raw($this->departmentSql().' as department'),
                DB::raw("CASE WHEN MAX(CASE WHEN {$this->plannableSql()} = 'Ja' THEN 1 ELSE 0 END) = 1 THEN 'Ja' ELSE 'Nee' END as plannable"),
                DB::raw($this->sugarSql().' as sugar_import'),
                DB::raw('COUNT(order_items.id) as items_count'),
            )
            ->selectSub($this->totalPurchaseQuery(), 'total_purchase');

        $this->addFilter('order_number', 'orders.order_number');
        $this->addFilter('title', 'orders.title');
        $this->addFilter('created_at', 'orders.created_at');
        $this->addFilter('total_price', 'orders.total_price');
        $this->addFilter('status', 'lead_pipeline_stages.name');
        $this->addFilter('plannable', new Expression($this->plannableSql()));
        $this->addFilter('sugar_import', new Expression($this->sugarSql()));
        $this->addFilter('department', new Expression($this->departmentSql()));
        $this->addFilter('total_purchase', new Expression('('.$this->totalPurchaseQuery()->toRawSql().')'));

        return $queryBuilder;
    }

    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'order_number',
            'label'      => 'Order',
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => fn ($row) => $row->order_number ?: '#'.$row->id,
        ]);

        $this->addColumn([
            'index'      => 'title',
            'label'      => 'Titel',
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'              => 'department',
            'label'              => 'Afdeling',
            'type'               => 'string',
            'searchable'         => false,
            'filterable'         => true,
            'filterable_type'    => 'dropdown',
            'filterable_options' => collect(Departments::cases())
                ->map(fn (Departments $department) => ['label' => $department->value, 'value' => $department->value])
                ->all(),
            'sortable' => true,
        ]);

        foreach (['plannable' => 'Planbaar', 'sugar_import' => 'Sugar CRM-import'] as $index => $label) {
            $this->addColumn([
                'index'              => $index,
                'label'              => $label,
                'type'               => 'string',
                'searchable'         => false,
                'filterable'         => true,
                'filterable_type'    => 'dropdown',
                'filterable_options' => [['label' => 'Ja', 'value' => 'Ja'], ['label' => 'Nee', 'value' => 'Nee']],
                'sortable'           => true,
            ]);
        }

        $this->addColumn([
            'index'      => 'status',
            'label'      => 'Status',
            'type'       => 'string',
            'searchable' => false,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'           => 'total_price',
            'label'           => 'Totaal verkoop',
            'type'            => 'float',
            'searchable'      => false,
            'filterable'      => true,
            'filterable_type' => 'number_range',
            'sortable'        => true,
            'closure'         => fn ($row) => $this->formatAmount($row->total_price),
        ]);

        $this->addColumn([
            'index'           => 'total_purchase',
            'label'           => 'Totaal inkoop',
            'type'            => 'float',
            'searchable'      => false,
            'filterable'      => true,
            'filterable_type' => 'number_range',
            'sortable'        => true,
            'closure'         => fn ($row) => $this->formatAmount($row->total_purchase),
        ]);

        $this->addColumn([
            'index'           => 'created_at',
            'label'           => 'Aangemaakt',
            'type'            => 'date',
            'searchable'      => false,
            'filterable'      => true,
            'filterable_type' => 'date_range',
            'sortable'        => true,
            'closure'         => fn ($row) => $row->created_at ? date('d-m-Y H:i', strtotime($row->created_at)) : '',
        ]);

        $this->addColumn([
            'index'      => 'items_count',
            'label'      => 'Regels',
            'type'       => 'integer',
            'searchable' => false,
            'filterable' => false,
            'sortable'   => true,
        ]);
    }

    public function prepareActions(): void
    {
        $this->addAction([
            'index'  => 'view',
            'icon'   => 'icon-eye',
            'title'  => 'Bekijken',
            'method' => 'GET',
            'url'    => fn ($row) => route('admin.orders.view', $row->id),
        ]);
    }

    /**
     * Sorting on aggregated/aliased columns must use the alias, not a table column.
     */
    protected function processRequestedSorting($requestedSort)
    {
        $column = $requestedSort['column'] ?? $this->sortColumn;

        $columnMap = [
            'order_number' => 'orders.order_number',
            'title'        => 'orders.title',
            'created_at'   => 'orders.created_at',
            'total_price'  => 'orders.total_price',
            'status'       => 'lead_pipeline_stages.name',
        ];

        return $this->queryBuilder->orderBy($columnMap[$column] ?? $column, $requestedSort['order'] ?? $this->sortOrder);
    }

    /**
     * Planbaar = alle resource types behalve 'Other' (onbekend/leeg telt als niet planbaar).
     */
    private function plannableSql(): string
    {
        $types = "'".implode("','", self::PLANNABLE_TYPES)."'";

        return "CASE WHEN LOWER(resource_types.name) IN ({$types}) THEN 'Ja' ELSE 'Nee' END";
    }

    /**
     * Sugar CRM-import = orders.external_id gevuld.
     */
    private function sugarSql(): string
    {
        return "CASE WHEN orders.external_id IS NULL THEN 'Nee' ELSE 'Ja' END";
    }

    private function departmentSql(): string
    {
        return sprintf(
            "CASE WHEN lead_pipeline_stages.lead_pipeline_id = %d THEN '%s' ELSE '%s' END",
            PipelineDefaultKeys::PIPELINE_HERNIA_ORDERS_ID->value,
            Departments::HERNIA->value,
            Departments::PRIVATESCAN->value,
        );
    }

    /**
     * Total purchase price of the whole order (not only the lines without one), excluding lost/incorrect lines.
     */
    private function totalPurchaseQuery(): Builder
    {
        return DB::table('purchase_prices as tp')
            ->join('order_items as toi', 'toi.id', '=', 'tp.priceable_id')
            ->whereColumn('toi.order_id', 'orders.id')
            ->where('tp.priceable_type', (new OrderItem)->getMorphClass())
            ->where('tp.type', PurchasePriceType::MAIN->value)
            ->whereNotIn('toi.status', OrderItemStatus::lostValues())
            ->selectRaw('COALESCE(SUM(tp.purchase_price), 0)');
    }

    private function formatAmount(mixed $amount): string
    {
        return '€ '.number_format((float) $amount, 2, ',', '.');
    }
}
