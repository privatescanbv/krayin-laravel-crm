<?php

namespace App\DataGrids\Settings;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class ClinicPartnerProductDataGrid extends DataGrid
{
    protected $sortColumn = 'partner_products.name';

    protected $sortOrder = 'asc';

    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('partner_products')
            ->join('clinic_partner_product', 'partner_products.id', '=', 'clinic_partner_product.partner_product_id')
            ->addSelect(
                'partner_products.id',
                'partner_products.name',
                'partner_products.currency',
                'partner_products.sales_price',
                'partner_products.active',
                'partner_products.duration'
            );

        // Filter by clinic_id from route parameter
        if ($clinicId = request()->route('id')) {
            $queryBuilder->where('clinic_partner_product.clinic_id', $clinicId);
        }

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
            'searchable' => false,
            'filterable' => true,
            'sortable'   => false,
            'visibility' => false,
        ]);

        $this->addColumn([
            'index'      => 'name',
            'type'       => 'string',
            'label'      => trans('admin::app.settings.clinics.view.partner-products.table.name'),
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'sales_price',
            'type'       => 'string',
            'label'      => trans('admin::app.settings.clinics.view.partner-products.table.price'),
            'searchable' => false,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => function ($row) {
                if ($row->sales_price) {
                    $currency = $row->currency ?? '€';

                    return $currency.' '.number_format($row->sales_price, 2);
                }

                return '-';
            },
        ]);

        $this->addColumn([
            'index'      => 'duration',
            'type'       => 'string',
            'label'      => trans('admin::app.settings.partner_products.index.datagrid.duration'),
            'searchable' => false,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => function ($row) {
                return $row->duration ? $row->duration.' '.trans('admin::app.settings.partner_products.index.datagrid.minutes') : '-';
            },
        ]);

        $this->addColumn([
            'index'      => 'active',
            'type'       => 'boolean',
            'label'      => trans('admin::app.settings.clinics.view.partner-products.table.status'),
            'searchable' => false,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => function ($row) {
                if ($row->active) {
                    return '<span class="rounded bg-green-100 px-2 py-1 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-200">'
                        .trans('admin::app.settings.clinics.view.partner-products.table.active')
                        .'</span>';
                }

                return '<span class="rounded bg-gray-100 px-2 py-1 text-xs font-medium text-gray-800 dark:bg-gray-900 dark:text-gray-200">'
                    .trans('admin::app.settings.clinics.view.partner-products.table.inactive')
                    .'</span>';
            },
        ]);
    }

    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('settings.partner_products.view')) {
            $this->addAction([
                'index'  => 'view',
                'icon'   => 'icon-eye',
                'title'  => trans('admin::app.settings.clinics.view.partner-products.table.view'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.settings.partner_products.view', $row->id),
            ]);
        }
        if (bouncer()->hasPermission('settings.partner_products.edit')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => trans('admin::app.settings.clinics.view.partner-products.table.edit'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.settings.partner_products.edit', $row->id),
            ]);
        }
        if (bouncer()->hasPermission('settings.clinics.edit')) {
            $this->addAction([
                'index'  => 'detach',
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.settings.clinics.view.partner-products.table.detach'),
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.settings.clinics.partner_products.detach', [
                    'id' => request()->route('id'),
                    'partner_product_id' => $row->id,
                ]),
            ]);
        }
    }
}
