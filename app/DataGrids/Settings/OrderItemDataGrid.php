<?php

namespace App\DataGrids\Settings;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class OrderItemDataGrid extends DataGrid
{
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('order_items')
            ->addSelect(
                'order_items.id',
                'order_items.order_id',
                'order_items.product_id',
                'order_items.quantity',
                'order_items.total_price',
                'order_items.status'
            );

        $this->addFilter('id', 'order_items.id');

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
            'index'      => 'order_id',
            'type'       => 'integer',
            'label'      => 'Order',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'product_id',
            'type'       => 'integer',
            'label'      => 'Product',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'quantity',
            'type'       => 'integer',
            'label'      => 'Aantal',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'total_price',
            'type'       => 'float',
            'label'      => 'Totale prijs',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'status',
            'type'       => 'string',
            'label'      => 'Status',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => function ($row) {
                $classes = match ($row->status) {
                    'planned' => 'bg-green-100 text-green-800',
                    'won'     => 'bg-blue-100 text-blue-800',
                    'lost'    => 'bg-red-100 text-red-800',
                    default   => 'bg-neutral-bg text-gray-800',
                };
                $labels = [
                    'new'     => 'Nieuw',
                    'planned' => 'Ingepland',
                    'won'     => 'Gewonnen',
                    'lost'    => 'Verloren',
                ];
                $label = $labels[$row->status] ?? ($row->status ?? '-');

                return $row->status
                    ? "<span class=\"inline-flex items-center px-2 py-1 text-xs font-medium rounded-full {$classes}\">{$label}</span>"
                    : '<span class="text-gray-400 text-xs">-</span>';
            },
        ]);
    }

    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('settings.order_items.edit')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => 'Bewerken',
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.order_items.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('settings.order_items.delete')) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => 'Verwijderen',
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.order_items.delete', $row->id),
            ]);
        }
    }
}
