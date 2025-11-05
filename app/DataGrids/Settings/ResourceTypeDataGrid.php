<?php

namespace App\DataGrids\Settings;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class ResourceTypeDataGrid extends DataGrid
{
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('resource_types')
            ->addSelect(
                'resource_types.id',
                'resource_types.name',
                'resource_types.description'
            );

        $this->addFilter('id', 'resource_types.id');

        return $queryBuilder;
    }

    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'name',
            'type'       => 'string',
            'label'      => trans('admin::app.settings.resource_types.index.datagrid.name'),
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'description',
            'type'       => 'string',
            'label'      => trans('admin::app.settings.resource_types.index.datagrid.description'),
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);
    }

    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('settings.resource_types.edit')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => trans('admin::app.settings.resource_types.index.datagrid.edit'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.settings.resource_types.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('settings.resource_types.delete')) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.settings.resource_types.index.datagrid.delete'),
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.settings.resource_types.delete', $row->id),
            ]);
        }
    }
}
