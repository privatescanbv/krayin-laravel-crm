<?php

namespace Webkul\Admin\DataGrids\Product;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use App\Enums\Currency;
use Webkul\DataGrid\DataGrid;
use Webkul\Product\Repositories\ProductGroupRepository;

class ProductDataGrid extends DataGrid
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected ProductGroupRepository $productGroupRepository) {}
    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('products')
            ->leftJoin('partner_products', function ($join) {
                $join->on('products.id', '=', 'partner_products.product_id')
                    ->whereNull('partner_products.deleted_at');
            })
            ->leftJoin('product_tags', 'products.id', '=', 'product_tags.product_id')
            ->leftJoin('tags', 'tags.id', '=', 'product_tags.tag_id')
            ->leftJoin('product_groups', 'products.product_group_id', '=', 'product_groups.id')
            ->leftJoin('product_types', 'products.product_type_id', '=', 'product_types.id')
            ->leftJoin('resource_types', 'products.resource_type_id', '=', 'resource_types.id')
            ->select(
                DB::raw('products.id as id'),
                'products.name',
                'products.currency',
                'products.price',
                'products.active',
                DB::raw('MIN(tags.name) as tag_name'),
                'product_groups.name as group_name',
                'product_groups.parent_id as group_parent_id',
                'product_types.name as product_type_name',
                'resource_types.name as resource_type_name',
                DB::raw('COUNT(DISTINCT partner_products.id) as partner_products_count')
            )
            ->groupBy('products.id');

        $this->addFilter('id', 'products.id');
//        $this->addFilter('tag_name', 'tags.name');
        $this->addFilter('group_name', 'product_groups.name');
        $this->addFilter('active', 'products.active');

        // Default filter: only active resources unless user provides a filter
        $requestedFilters = request()->input('filters', []);
        if (! array_key_exists('active', $requestedFilters)) {
            $queryBuilder->where('products.active', 1);
        }

        // 🚨 reset alle automatisch toegevoegde order by’s en zet jouw eigen fallback
        return $queryBuilder
            ->reorder()                       // wist ALLE order by’s, ook die van datagrid
            ->orderBy('products.id', 'desc'); // voeg expliciet jouw order toe
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
                ->orderBy('products.active', 'desc')
                ->orderBy('products.name', 'asc');

            return $this->queryBuilder;
        }

        return parent::processRequestedSorting($requestedSort);
    }

    /**
     * Add columns.
     */
    public function prepareColumns(): void
    {
        // SKU removed per requirements

        $this->addColumn([
            'index'      => 'name',
            'columnName' => 'products.name',
            'label'      => trans('admin::app.products.index.datagrid.name'),
            'type'       => 'string',
            'sortable'   => true,
            'searchable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index'      => 'product_type_name',
            'columnName' => 'product_types.name',
            'label'      => trans('admin::app.products.create.product_type'),
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true,
            'closure'    => function ($row) {
                return $row->product_type_name ?? '--';
            },
        ]);

        $this->addColumn([
            'index'      => 'resource_type_name',
            'columnName' => 'resource_types.name',
            'label'      => trans('admin::app.products.create.resource_type'),
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true,
            'closure'    => function ($row) {
                return $row->resource_type_name ?? '--';
            },
        ]);

        $this->addColumn([
            'index'      => 'price',
            'columnName' => 'products.price',
            'label'      => trans('admin::app.products.index.datagrid.price'),
            'type'       => 'string',
            'sortable'   => true,
            'searchable' => true,
            'filterable' => true,
            'closure'    => function ($row) {
                return Currency::formatMoney($row->currency, (float) $row->price);
            },
        ]);

        $this->addColumn([
            'index'      => 'group_name',
            'columnName' => 'products.group_name',
            'label'      => trans('admin::app.productgroups.title'),
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true,
            'closure'    => function ($row) {
                if (!$row->group_name) {
                    return '--';
                }

                // Create a mock object with the row data
                $mockRow = (object) [
                    'name' => $row->group_name,
                    'parent_id' => $row->group_parent_id
                ];

                return $this->productGroupRepository->getGroupPathByRow($mockRow);
            },
        ]);

        $this->addColumn([
            'index'      => 'partner_products_count',
            'columnName' => 'partner_products_count',
            'label'      => 'Partner products',
            'type'       => 'string',
            'searchable' => false,
            'sortable'   => true,
            'filterable' => false,
            'closure'    => function ($row) {
                return (string) ($row->partner_products_count ?? 0);
            },
        ]);

        $this->addColumn([
            'index'      => 'active',
            'columnName' => 'products.active',
            'label'      => trans('admin::app.partner_products.index.datagrid.active'),
            'type'       => 'boolean',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true,
            'closure'    => function ($row) {
                $active = $row->active ?? false;

                return $active
                    ? "<span class='icon-tick text-green-600 text-lg' title='".e(trans('admin::app.settings.clinics.index.datagrid.is_active'))."'></span>"
                    : "<span class='icon-cross-large text-red-600 text-lg' title='".e(trans('admin::app.settings.clinics.index.datagrid.is_active'))."'></span>";
            },
        ]);
    }

    /**
     * Prepare actions.
     */
    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('products.view')) {
            $this->addAction([
                'index'  => 'view',
                'icon'   => 'icon-eye',
                'title'  => trans('admin::app.products.index.datagrid.view'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.products.view', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('products.edit')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => trans('admin::app.products.index.datagrid.edit'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.products.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('products.add')) {
            $this->addAction([
                'index'  => 'add',
                'icon'   => 'icon-add',
                'title'  => 'Partner product toevoegen op basis van dit product',
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.partner_products.create', ['product_id'=>$row->id]),
            ]);
        }

        if (bouncer()->hasPermission('products.delete')) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.products.index.datagrid.delete'),
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.products.delete', $row->id),
            ]);
        }
    }

    // Money formatting centralized in App\Enums\Currency::formatMoney
}
