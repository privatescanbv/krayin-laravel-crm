<?php

namespace App\DataGrids\Settings;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class ClinicDataGrid extends DataGrid
{
    /**
     * @param  string  $sortColumn
     * @param  string  $sortOrder
     */
    public function __construct()
    {
        $this->sortColumn = 'clinics.name';
        $this->sortOrder = 'asc';
    }

    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('clinics')
            ->addSelect(
                'clinics.id',
                'clinics.is_active',
                'clinics.name',
                'clinics.registration_form_clinic_name'
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
            'index'      => 'is_active',
            'type'       => 'boolean',
            'label'      => trans('admin::app.settings.clinics.index.datagrid.is_active'),
            'searchable' => false,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => function ($row) {
                $active = (bool) ($row->is_active ?? false);

                return $active
                    ? "<span class='icon-tick text-green-600 text-lg' title='".e(trans('admin::app.settings.clinics.index.datagrid.is_active'))."'></span>"
                    : "<span class='icon-cross-large text-red-600 text-lg' title='".e(trans('admin::app.settings.clinics.index.datagrid.is_active'))."'></span>";
            },
            'escape'     => false,
            'width'      => '20px',
        ]);

        $this->addColumn([
            'index'      => 'name',
            'type'       => 'string',
            'label'      => trans('admin::app.settings.clinics.index.datagrid.name'),
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'registration_form_clinic_name',
            'type'       => 'string',
            'label'      => 'AFB naam kliniek',
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
                'url'    => fn ($row) => route('admin.clinics.view', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('settings.clinics.edit')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => trans('admin::app.settings.clinics.index.datagrid.edit'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.clinics.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('settings.clinics.delete')) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.settings.clinics.index.datagrid.delete'),
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.clinics.delete', $row->id),
            ]);
        }
    }
}
