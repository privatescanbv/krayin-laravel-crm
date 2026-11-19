<?php

namespace App\DataGrids\Settings;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class DocumentTypeDataGrid extends DataGrid
{
    protected $sortColumn = 'document_types.sort_order';

    protected $sortOrder = 'asc';

    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('document_types')
            ->addSelect(
                'document_types.id',
                'document_types.key',
                'document_types.name',
                'document_types.show_in_clinic_portal',
                'document_types.show_in_patient_portal',
                'document_types.sort_order',
                DB::raw('(select count(*) from activities where activities.document_type_id = document_types.id) as documents_count'),
            );

        $this->addFilter('id', 'document_types.id');
        $this->addFilter('name', 'document_types.name');

        return $queryBuilder;
    }

    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'name',
            'label'      => trans('admin::app.settings.document_types.index.datagrid.name'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'show_in_clinic_portal',
            'label'      => trans('admin::app.settings.document_types.index.datagrid.show_in_clinic_portal'),
            'type'       => 'boolean',
            'searchable' => false,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'show_in_patient_portal',
            'label'      => trans('admin::app.settings.document_types.index.datagrid.show_in_patient_portal'),
            'type'       => 'boolean',
            'searchable' => false,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'sort_order',
            'label'      => trans('admin::app.settings.document_types.index.datagrid.sort_order'),
            'type'       => 'integer',
            'searchable' => false,
            'filterable' => false,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'documents_count',
            'label'      => trans('admin::app.settings.document_types.index.datagrid.documents_count'),
            'type'       => 'integer',
            'searchable' => false,
            'filterable' => false,
            'sortable'   => false,
        ]);
    }

    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('settings.document_types.edit')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => trans('admin::app.settings.document_types.index.datagrid.edit'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.settings.document_types.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('settings.document_types.delete')) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.settings.document_types.index.datagrid.delete'),
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.settings.document_types.delete', $row->id),
            ]);
        }
    }
}
