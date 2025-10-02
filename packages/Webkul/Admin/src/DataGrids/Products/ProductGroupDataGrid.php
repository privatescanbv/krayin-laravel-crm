<?php

namespace Webkul\Admin\DataGrids\Products;

use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class ProductGroupDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function prepareQueryBuilder()
    {
        $queryBuilder = DB::table('product_groups')
            ->leftJoin('product_groups as parent', 'product_groups.parent_id', '=', 'parent.id')
            ->addSelect(
                'product_groups.id',
                'product_groups.name',
                'product_groups.description',
                'product_groups.created_at',
                DB::raw('parent.name as parent_path')
            );

        $this->addFilter('id', 'product_groups.id');
        $this->addFilter('name', 'product_groups.name');
        $this->addFilter('created_at', 'product_groups.created_at');
        $this->addFilter('parent_path', 'parent.name');

        return $queryBuilder;
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
            'index'      => 'parent_path',
            'label'      => trans('admin::app.productgroups.index.datagrid.parent_path'),
            'type'       => 'string',
            'searchable' => false,
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
        $this->addAction([
            'title'  => trans('admin::app.productgroups.index.datagrid.edit'),
            'method' => 'GET',
            'route'  => 'admin.productgroups.edit',
            'url'    => function ($row) {
                return route('admin.productgroups.edit', $row->id);
            },
            'icon'   => 'icon-edit',
        ]);

        $this->addAction([
            'title'  => trans('admin::app.productgroups.index.datagrid.delete'),
            'method' => 'DELETE',
            'route'  => 'admin.productgroups.delete',
            'url'    => function ($row) {
                return route('admin.productgroups.delete', $row->id);
            },
            'icon'   => 'icon-delete',
        ]);
    }
}
