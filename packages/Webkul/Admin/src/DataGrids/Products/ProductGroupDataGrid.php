<?php

namespace Webkul\Admin\DataGrids\Products;

use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;
use Webkul\Product\Repositories\ProductGroupRepository;

class ProductGroupDataGrid extends DataGrid
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected ProductGroupRepository $productGroupRepository) {}
    /**
     * Prepare query builder.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function prepareQueryBuilder()
    {
        $queryBuilder = DB::table('product_groups')
            ->addSelect(
                'id',
                'name',
                'description',
                'parent_id',
                'created_at'
            );

        $this->addFilter('id', 'id');
        $this->addFilter('name', 'name');
        $this->addFilter('created_at', 'created_at');

        return $queryBuilder;
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
                ->orderBy('product_groups.name', 'asc');

            return $this->queryBuilder;
        }

        return parent::processRequestedSorting($requestedSort);
    }

    /**
     * Prepare columns.
     */
    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'id',
            'label'      => trans('admin::app.productgroups.index.datagrid.id'),
            'type'       => 'integer',
            'searchable' => false,
            'sortable'   => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index'      => 'name',
            'label'      => trans('admin::app.productgroups.index.datagrid.name'),
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true,
            'closure'    => function ($row) {
                return $this->productGroupRepository->getGroupPathByRow($row);
            },
        ]);

        $this->addColumn([
            'index'      => 'description',
            'label'      => trans('admin::app.productgroups.index.datagrid.description'),
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index'      => 'created_at',
            'label'      => trans('admin::app.productgroups.index.datagrid.created_at'),
            'type'       => 'datetime',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true,
        ]);
    }

    /**
     * Prepare actions.
     *
     * @return void
     */
    public function prepareActions()
    {
        if (bouncer()->hasPermission('productgroups.edit')) {
            $this->addAction([
                'title' => trans('admin::app.productgroups.index.datagrid.edit'),
                'method' => 'GET',
                'route' => 'admin.productgroups.edit',
                'url' => function ($row) {
                    return route('admin.productgroups.edit', $row->id);
                },
                'icon' => 'icon-edit',
            ]);
        }

        if (bouncer()->hasPermission('productgroups.delete')) {
            $this->addAction([
                'title' => trans('admin::app.productgroups.index.datagrid.delete'),
                'method' => 'DELETE',
                'route' => 'admin.productgroups.delete',
                'url' => function ($row) {
                    return route('admin.productgroups.delete', $row->id);
                },
                'icon' => 'icon-delete',
            ]);
        }
    }

}
