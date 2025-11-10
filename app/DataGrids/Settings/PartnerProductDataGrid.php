<?php

namespace App\DataGrids\Settings;

use App\Enums\Currency;
use App\Enums\ProductReports;
use App\Models\PartnerProduct;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class PartnerProductDataGrid extends DataGrid
{
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('partner_products')
            ->leftJoin('clinic_partner_product', 'partner_products.id', '=', 'clinic_partner_product.partner_product_id')
            ->leftJoin('clinics', 'clinics.id', '=', 'clinic_partner_product.clinic_id')
            ->leftJoin('partner_product_resource', 'partner_products.id', '=', 'partner_product_resource.partner_product_id')
            ->leftJoin('resources', 'resources.id', '=', 'partner_product_resource.resource_id')
            ->leftJoin('resource_types', 'resource_types.id', '=', 'partner_products.resource_type_id')
            ->whereNull('partner_products.deleted_at')
            ->addSelect(
                'partner_products.id',
                'partner_products.name',
                'partner_products.currency',
                'partner_products.sales_price',
                'partner_products.related_sales_price',
                'partner_products.active',
                'partner_products.reporting',
                DB::raw('MIN(clinics.name) as clinic_name'),
                DB::raw('COUNT(DISTINCT resources.id) as resources_count'),
                'resource_types.name as resource_type_name'
            )
            ->groupBy('partner_products.id');

        $this->addFilter('id', 'partner_products.id');
        $this->addFilter('active', 'partner_products.active');

        // Default filter: only active resources unless user provides a filter
        $requestedFilters = request()->input('filters', []);
        if (! array_key_exists('active', $requestedFilters)) {
            $queryBuilder->where('partner_products.active', 1);
        }

        return $queryBuilder;
    }

    public function prepareColumns(): void
    {
        // Removed ID column per requirements

        $this->addColumn([
            'index'      => 'name',
            'columnName' => 'partner_products.name',
            'type'       => 'string',
            'label'      => trans('admin::app.partner_products.index.datagrid.name'),
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'clinic_name',
            'columnName' => 'clinics.name',
            'type'       => 'string',
            'label'      => 'Clinic',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => function ($row) {
                return $row->clinic_name ?? '-';
            },
        ]);

        $this->addColumn([
            'index'      => 'resource_type_name',
            'columnName' => 'resource_types.name',
            'type'       => 'string',
            'label'      => trans('admin::app.partner_products.index.create.resource_type'),
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => function ($row) {
                return $row->resource_type_name ?? '-';
            },
        ]);

        $this->addColumn([
            'index'      => 'resources_count',
            'columnName' => 'resources_count',
            'type'       => 'string',
            'label'      => 'Resources',
            'searchable' => false,
            'filterable' => false,
            'sortable'   => true,
            'closure'    => function ($row) {
                return (string) ($row->resources_count ?? 0);
            },
        ]);

        $this->addColumn([
            'index'      => 'purchase_price',
            'columnName' => 'partner_products.purchase_price',
            'type'       => 'string',
            'label'      => trans('admin::app.partner_products.index.datagrid.purchase_price'),
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => function ($row) {
                return Currency::formatMoney($row->currency, (float) $row->sales_price);
            },
        ]);

        $this->addColumn([
            'index'      => 'sales_price',
            'columnName' => 'partner_products.sales_price',
            'type'       => 'string',
            'label'      => trans('admin::app.partner_products.index.datagrid.sales_price'),
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => function ($row) {
                return Currency::formatMoney($row->currency, (float) $row->sales_price);
            },
        ]);

        $this->addColumn([
            'index'      => 'related_sales_price',
            'columnName' => 'partner_products.related_sales_price',
            'type'       => 'string',
            'label'      => trans('admin::app.partner_products.index.datagrid.related_sales_price'),
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => function ($row) {
                return $row->related_sales_price > 0 ? Currency::formatMoney($row->currency, (float) $row->related_sales_price) : '-';
            },
        ]);

        $this->addColumn([
            'index'      => 'reporting',
            'columnName' => 'partner_products.reporting',
            'type'       => 'string',
            'label'      => trans('admin::app.partner_products.index.datagrid.reporting'),
            'searchable' => false,
            'filterable' => false,
            'sortable'   => false,
            'closure'    => function ($row) {
                $normalized = PartnerProduct::normalizeReporting($row->reporting);

                if (empty($normalized)) {
                    return '-';
                }

                $labels = [];
                foreach ($normalized as $report) {
                    $enum = ProductReports::tryFrom($report);
                    if ($enum) {
                        $labels[] = $enum->getLabel();
                    }
                }

                return empty($labels) ? '-' : implode(', ', $labels);
            },
        ]);

        $this->addColumn([
            'index'      => 'active',
            'columnName' => 'partner_products.active',
            'type'       => 'boolean',
            'label'      => trans('admin::app.partner_products.index.datagrid.active'),
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => function ($row) {
                $active = $row->active ?? false;

                return $active
                    ? "<span class='icon-tick text-green-600 text-lg' title='".e(trans('admin::app.settings.clinics.index.datagrid.is_active'))."'></span>"
                    : "<span class='icon-cross-large text-red-600 text-lg' title='".e(trans('admin::app.settings.clinics.index.datagrid.is_active'))."'></span>";
            },
        ]);
    }

    public function prepareActions(): void
    {
        $this->addAction([
            'index'  => 'view',
            'icon'   => 'icon-eye',
            'title'  => trans('admin::app.partner_products.index.datagrid.view'),
            'method' => 'GET',
            'url'    => fn ($row) => route('admin.partner_products.view', $row->id),
        ]);

        if (bouncer()->hasPermission('partner_products.edit')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => trans('admin::app.partner_products.index.datagrid.edit'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.partner_products.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('partner_products.delete')) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.partner_products.index.datagrid.delete'),
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.partner_products.delete', $row->id),
            ]);
        }
    }

    /**
     * Default sorting: active resources first, then by name ASC.
     * Only applies when no explicit sort is requested by the client.
     */
    protected function processRequestedSorting($requestedSort)
    {
        if (empty($requestedSort) || empty($requestedSort['column'])) {
            // Reset any existing order and apply our default
            $this->queryBuilder->reorder()
                ->orderBy('partner_products.active', 'desc')
                ->orderBy('partner_products.name', 'asc');

            return $this->queryBuilder;
        }

        return parent::processRequestedSorting($requestedSort);
    }

    // Money formatting centralized in App\Enums\Currency::formatMoney
}


