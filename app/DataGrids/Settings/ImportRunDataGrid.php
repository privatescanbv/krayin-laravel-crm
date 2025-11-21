<?php

namespace App\DataGrids\Settings;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class ImportRunDataGrid extends DataGrid
{
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('import_runs')
            ->addSelect(
                'import_runs.id',
                'import_runs.import_type',
                'import_runs.status',
                'import_runs.started_at',
                'import_runs.completed_at',
                'import_runs.records_processed',
                'import_runs.records_imported',
                'import_runs.records_skipped',
                'import_runs.records_errored'
            )
            ->orderBy('import_runs.id', 'desc');

        $this->addFilter('id', 'import_runs.id');

        return $queryBuilder;
    }

    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'id',
            'label'      => '#',
            'type'       => 'integer',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'import_type',
            'type'       => 'string',
            'label'      => 'Type',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'status',
            'type'       => 'string',
            'label'      => 'Status',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => function ($row) {
                $statusColors = [
                    'completed' => 'success',
                    'running'   => 'warning',
                    'failed'    => 'danger',
                ];
                $color = $statusColors[$row->status] ?? 'secondary';

                return "<span class='label label-{$color}'>".ucfirst($row->status).'</span>';
            },
        ]);

        $this->addColumn([
            'index'      => 'started_at',
            'type'       => 'datetime',
            'label'      => 'Gestart',
            'searchable' => false,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'records_processed',
            'type'       => 'integer',
            'label'      => 'Verwerkt',
            'searchable' => false,
            'filterable' => false,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'records_imported',
            'type'       => 'integer',
            'label'      => 'Geïmporteerd',
            'searchable' => false,
            'filterable' => false,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'records_errored',
            'type'       => 'integer',
            'label'      => 'Fouten',
            'searchable' => false,
            'filterable' => false,
            'sortable'   => true,
            'closure'    => function ($row) {
                if ($row->records_errored > 0) {
                    return "<span class='text-status-expired-text font-semibold'>{$row->records_errored}</span>";
                }

                return "<span class='text-status-active-text'>0</span>";
            },
        ]);
    }

    public function prepareActions(): void
    {
        $this->addAction([
            'index'  => 'view',
            'icon'   => 'icon-eye',
            'title'  => 'View',
            'method' => 'GET',
            'url'    => fn ($row) => route('admin.settings.import-runs.view', $row->id),
        ]);

        $this->addAction([
            'index'  => 'delete',
            'icon'   => 'icon-delete',
            'title'  => 'Delete',
            'method' => 'DELETE',
            'url'    => fn ($row) => route('admin.settings.import-runs.delete', $row->id),
        ]);
    }
}
