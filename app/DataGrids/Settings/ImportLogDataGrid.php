<?php

namespace App\DataGrids\Settings;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class ImportLogDataGrid extends DataGrid
{
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('import_logs')
            ->leftJoin('import_runs', 'import_logs.import_run_id', '=', 'import_runs.id')
            ->addSelect(
                'import_logs.id',
                'import_logs.import_run_id',
                'import_runs.import_type',
                'import_logs.level',
                'import_logs.message',
                'import_logs.record_id',
                'import_logs.created_at'
            );

        $this->addFilter('id', 'import_logs.id');

        return $queryBuilder;
    }

    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'id',
            'label'      => 'ID',
            'type'       => 'integer',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'import_run_id',
            'type'       => 'integer',
            'label'      => 'Import Run',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'import_type',
            'type'       => 'string',
            'label'      => 'Import Type',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'level',
            'type'       => 'string',
            'label'      => 'Level',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'message',
            'type'       => 'string',
            'label'      => 'Message',
            'searchable' => true,
            'filterable' => false,
            'sortable'   => false,
        ]);

        $this->addColumn([
            'index'      => 'record_id',
            'type'       => 'string',
            'label'      => 'Record ID',
            'searchable' => true,
            'filterable' => false,
            'sortable'   => false,
        ]);

        $this->addColumn([
            'index'      => 'created_at',
            'type'       => 'datetime',
            'label'      => 'Created At',
            'searchable' => false,
            'filterable' => true,
            'sortable'   => true,
        ]);
    }

    public function prepareActions(): void
    {
        $this->addAction([
            'index'  => 'view',
            'icon'   => 'icon-eye',
            'title'  => 'View',
            'method' => 'GET',
            'url'    => fn ($row) => route('admin.settings.import-logs.view', $row->id),
        ]);

        $this->addAction([
            'index'  => 'delete',
            'icon'   => 'icon-delete',
            'title'  => 'Delete',
            'method' => 'DELETE',
            'url'    => fn ($row) => route('admin.settings.import-logs.delete', $row->id),
        ]);
    }
}