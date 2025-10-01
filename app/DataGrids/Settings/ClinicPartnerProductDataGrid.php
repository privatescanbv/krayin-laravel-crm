<?php

namespace App\DataGrids\Settings;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class ClinicPartnerProductDataGrid extends DataGrid
{
    protected int $clinicId;

    public function __construct(int $clinicId)
    {
        $this->clinicId = $clinicId;

        parent::__construct();
    }

    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('partner_products')
            ->join('clinic_partner_product', 'partner_products.id', '=', 'clinic_partner_product.partner_product_id')
            ->where('clinic_partner_product.clinic_id', $this->clinicId)
            ->addSelect(
                'partner_products.id',
                'partner_products.name',
                'partner_products.currency',
                'partner_products.sales_price',
                'partner_products.active',
                'partner_products.duration',
                'partner_products.description'
            );

        $this->addFilter('id', 'partner_products.id');
        $this->addFilter('name', 'partner_products.name');

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
        ]);

        $this->addColumn([
            'index'      => 'sales_price',
            'type'       => 'string',
            'label'      => trans('admin::app.settings.partner_products.index.datagrid.sales_price'),
            'searchable' => false,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => fn ($row) => ($row->currency ?? 'EUR').' '.number_format((float) $row->sales_price, 2),
        ]);

        $this->addColumn([
            'index'      => 'duration',
            'type'       => 'string',
            'label'      => trans('admin::app.settings.partner_products.index.datagrid.duration'),
            'searchable' => false,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => fn ($row) => $row->duration ? $row->duration.' '.trans('admin::app.settings.partner_products.index.datagrid.minutes') : '-',
        ]);

        $this->addColumn([
            'index'      => 'active',
            'type'       => 'boolean',
            'label'      => trans('admin::app.settings.partner_products.index.datagrid.active'),
            'searchable' => false,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => fn ($row) => $row->active
                ? '<span class="label-active">'.trans('admin::app.settings.partner_products.index.datagrid.active').'</span>'
                : '<span class="label-inactive">'.trans('admin::app.settings.partner_products.index.datagrid.inactive').'</span>',
        ]);
    }

    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('settings.partner_products.view')) {
            $this->addAction([
                'index'  => 'view',
                'icon'   => 'icon-eye',
                'title'  => trans('admin::app.settings.partner_products.index.datagrid.view'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.settings.partner_products.view', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('settings.partner_products.edit')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => trans('admin::app.settings.partner_products.index.datagrid.edit'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.settings.partner_products.edit', $row->id),
            ]);
        }
    }
}
