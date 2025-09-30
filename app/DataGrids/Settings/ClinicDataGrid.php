<?php

namespace App\DataGrids\Settings;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class ClinicDataGrid extends DataGrid
{
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('clinics')
            ->addSelect(
                'clinics.id',
                'clinics.name'
            );

        $this->addFilter('id', 'clinics.id');

        return $queryBuilder;
    }

    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'id',
            'label'      => trans('admin::app.settings.clinics.index.datagrid.id'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'name',
            'type'       => 'string',
            'label'      => trans('admin::app.settings.clinics.index.datagrid.name'),
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);
    }

    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('settings.clinics.view')) {
            $this->addAction([
                'index'  => 'view',
                'icon'   => 'icon-eye',
                'title'  => trans('admin::app.settings.clinics.index.datagrid.view'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.settings.clinics.view', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('settings.clinics.edit')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => trans('admin::app.settings.clinics.index.datagrid.edit'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.settings.clinics.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('settings.clinics.delete')) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.settings.clinics.index.datagrid.delete'),
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.settings.clinics.delete', $row->id),
            ]);
        }
    }
}
