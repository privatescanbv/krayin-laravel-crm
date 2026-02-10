<?php

namespace App\DataGrids\Settings;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class OrderDataGrid extends DataGrid
{
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('orders')
            ->leftJoin('lead_pipeline_stages', 'orders.pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->addSelect(
                'orders.id',
                'orders.title',
                'orders.total_price',
                'orders.pipeline_stage_id',
                'lead_pipeline_stages.name as stage_name',
                'lead_pipeline_stages.is_won',
                'lead_pipeline_stages.is_lost',
                'lead_pipeline_stages.sort_order as stage_sort_order'
            );

        $this->addFilter('id', 'orders.id');

        /**
         * Optional context filters (used when embedding this datagrid in other screens).
         */
        if ($salesLeadId = request('sales_lead_id')) {
            $queryBuilder->where('orders.sales_lead_id', (int) $salesLeadId);
        }

        /**
         * Optional status bucket filter:
         * - open: active/in-progress orders (not won/lost)
         * - completed: finished/closed orders (won or lost)
         */
        $statusBucket = request('status_bucket');
        if ($statusBucket === 'open') {
            $queryBuilder->where(function ($q) {
                $q->where('lead_pipeline_stages.is_won', false)
                    ->where('lead_pipeline_stages.is_lost', false);
            })->orWhereNull('orders.pipeline_stage_id');
        } elseif ($statusBucket === 'completed') {
            $queryBuilder->where(function ($q) {
                $q->where('lead_pipeline_stages.is_won', true)
                    ->orWhere('lead_pipeline_stages.is_lost', true);
            });
        }

        /**
         * Prefer open/in-progress orders first, by stage sort_order.
         */
        $queryBuilder->orderByRaw('
            CASE
                WHEN lead_pipeline_stages.is_lost = 1 THEN 2
                WHEN lead_pipeline_stages.is_won = 1 THEN 1
                ELSE 0
            END ASC,
            COALESCE(lead_pipeline_stages.sort_order, 0) ASC
        ');

        return $queryBuilder;
    }

    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'id',
            'label'      => 'ID',
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'title',
            'type'       => 'string',
            'label'      => 'Titel',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'total_price',
            'type'       => 'float',
            'label'      => 'Totale prijs',
            'searchable' => false,
            'filterable' => false,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'stage_name',
            'label'      => 'Status',
            'type'       => 'string',
            'searchable' => false,
            'sortable'   => true,
            'filterable' => false,
            'closure'    => function ($row) {
                return $row->stage_name ?? 'Onbekend';
            },
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
        if (bouncer()->hasPermission('orders.edit')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => 'Bewerken',
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.orders.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('orders.delete')) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => 'Verwijderen',
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.orders.delete', $row->id),
            ]);
        }
    }

    /**
     * Map ambiguous sort keys to fully-qualified columns.
     *
     * The orders datagrid joins `lead_pipeline_stages`, so unqualified `id` (and similar)
     * can become ambiguous in SQLite (tests) and some SQL modes.
     */
    protected function processRequestedSorting($requestedSort)
    {
        $column = $requestedSort['column'] ?? $this->sortColumn ?? $this->primaryColumn;

        $columnMap = [
            'id'          => 'orders.id',
            'title'       => 'orders.title',
            'total_price' => 'orders.total_price',
            'stage_name'  => 'lead_pipeline_stages.name',
        ];

        $column = $columnMap[$column] ?? $column;

        return $this->queryBuilder->orderBy(
            $column,
            $requestedSort['order'] ?? $this->sortOrder
        );
    }
}
