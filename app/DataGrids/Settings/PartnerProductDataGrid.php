<?php

namespace App\DataGrids\Settings;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class PartnerProductDataGrid extends DataGrid
{
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('partner_products')
            ->addSelect(
                'partner_products.id',
                'partner_products.partner_name',
                'partner_products.name',
                'partner_products.currency',
                'partner_products.sales_price',
                'partner_products.active'
            );

        $this->addFilter('id', 'partner_products.id');

        return $queryBuilder;
    }

    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'id',
            'label'      => trans('admin::app.settings.partner_products.index.datagrid.id'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'partner_name',
            'type'       => 'string',
            'label'      => trans('admin::app.settings.partner_products.index.datagrid.partner_name'),
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'name',
            'type'       => 'string',
            'label'      => trans('admin::app.settings.partner_products.index.datagrid.name'),
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'currency',
            'type'       => 'string',
            'label'      => trans('admin::app.settings.partner_products.index.datagrid.currency'),
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'sales_price',
            'type'       => 'price',
            'label'      => trans('admin::app.settings.partner_products.index.datagrid.sales_price'),
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'active',
            'type'       => 'boolean',
            'label'      => trans('admin::app.settings.partner_products.index.datagrid.active'),
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);
    }

    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('settings.partner_products.edit')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => trans('admin::app.settings.partner_products.index.datagrid.edit'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.settings.partner_products.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('settings.partner_products.delete')) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.settings.partner_products.index.datagrid.delete'),
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.settings.partner_products.delete', $row->id),
            ]);
        }
    }
}

