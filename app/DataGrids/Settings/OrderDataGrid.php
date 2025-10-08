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
            ->addSelect(
                'orders.id',
                'orders.title',
                'orders.total_price'
            );

        $this->addFilter('id', 'orders.id');

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
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);
    }

    public function prepareActions(): void
    {
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
