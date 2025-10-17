<?php

namespace App\DataGrids\Settings;

use App\Enums\Currency;
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
            'index'      => 'name',
            'type'       => 'string',
            'label'      => trans('admin::app.settings.partner_products.index.datagrid.name'),
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => function ($row) {
                $partnerProductRepository = app(\App\Repositories\PartnerProductRepository::class);
                $partnerProduct = \App\Models\PartnerProduct::with('clinics:id,name')->find($row->id);
                return $partnerProduct ? $partnerProductRepository->formatDisplayName($partnerProduct) : $row->name;
            },
        ]);

        $this->addColumn([
            'index'      => 'purchase_price',
            'type'       => 'string',
            'label'      => trans('admin::app.settings.partner_products.index.datagrid.purchase_price'),
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => function ($row) {
                return Currency::formatMoney($row->currency, (float) $row->sales_price);
            },
        ]);

        $this->addColumn([
            'index'      => 'sales_price',
            'type'       => 'string',
            'label'      => trans('admin::app.settings.partner_products.index.datagrid.sales_price'),
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => function ($row) {
                return Currency::formatMoney($row->currency, (float) $row->sales_price);
            },
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
        $this->addAction([
            'index'  => 'view',
            'icon'   => 'icon-eye',
            'title'  => trans('admin::app.settings.partner_products.index.datagrid.view'),
            'method' => 'GET',
            'url'    => fn ($row) => route('admin.settings.partner_products.view', $row->id),
        ]);

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

    // Money formatting centralized in App\Enums\Currency::formatMoney
}
