<?php

namespace App\DataGrids\Settings;

use App\Enums\OrderStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class OrderDataGrid extends DataGrid
{
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('orders')
            ->addSelect(
                'orders.id',
                'orders.title',
                'orders.total_price',
                'orders.status'
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
         * - open: active/in-progress orders
         * - completed: finished/closed orders
         */
        $statusBucket = request('status_bucket');
        if ($statusBucket === 'open') {
            $queryBuilder->whereNotIn('orders.status', OrderStatus::getCloseStatuses());
        } elseif ($statusBucket === 'completed') {
            $queryBuilder->whereIn('orders.status', OrderStatus::getCloseStatuses());
        }

        /**
         * Prefer open/in-progress orders first. Datagrid may add an additional default orderBy later.
         */
        $queryBuilder->orderByRaw("
            CASE orders.status
                WHEN 'new' THEN 0
                WHEN 'planned' THEN 1
                WHEN 'sent' THEN 2
                WHEN 'rejected' THEN 3
                WHEN 'approved' THEN 4
                ELSE 99
            END ASC
        ");

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
            'index'              => 'status',
            'label'              => 'Status',
            'type'               => 'string',
            'searchable'         => false,
            'sortable'           => true,
            'filterable'         => true,
            'filterable_type'    => 'dropdown',
            'filterable_options' => collect(OrderStatus::cases())
                ->map(function (OrderStatus $orderStatus) {
                    return [
                        'value' => $orderStatus->value,
                        'label' => $orderStatus->label(),
                    ];
                })
                ->values()
                ->all(),
            'closure' => function ($row) {
                return OrderStatus::tryFrom($row->status)?->label() ?? $row->status;
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
}
