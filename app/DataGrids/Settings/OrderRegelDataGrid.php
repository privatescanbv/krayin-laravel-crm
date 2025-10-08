<?php

namespace App\DataGrids\Settings;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class OrderRegelDataGrid extends DataGrid
{
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('order_regels')
            ->addSelect(
                'order_regels.id',
                'order_regels.order_id',
                'order_regels.product_id',
                'order_regels.quantity',
                'order_regels.total_price'
            );

        $this->addFilter('id', 'order_regels.id');

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
            'type'       => 'decimal',
            'label'      => 'Totale prijs',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);
    }

    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('settings.order_regels.edit')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => 'Bewerken',
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.settings.order_regels.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('settings.order_regels.delete')) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => 'Verwijderen',
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.settings.order_regels.delete', $row->id),
            ]);
        }
    }
}

