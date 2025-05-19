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
            ->addSelect(
                'id',
                'name',
                'description',
                'created_at'
            );

        $this->addFilter('id', 'id');
        $this->addFilter('name', 'name');
        $this->addFilter('created_at', 'created_at');

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
