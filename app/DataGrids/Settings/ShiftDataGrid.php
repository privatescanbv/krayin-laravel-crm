<?php

namespace App\DataGrids\Settings;

use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class ShiftDataGrid extends DataGrid
{
    protected $sortColumn = 'shifts.period_start';

    public function prepareQueryBuilder(): Builder
    {
        $resourceId = (int) (
            $this->extraFilters['resourceId']
            ?? request()->route('resourceId')
            ?? request()->get('resource_id')
            ?? 0
        );
        if ($resourceId === 0) {
            throw new Exception('Resource ID is required to build the Shift data grid query.');
        }

        $queryBuilder = DB::table('shifts')
            ->where('shifts.resource_id', $resourceId)
            ->addSelect('shifts.id', 'shifts.period_start', 'shifts.period_end', 'shifts.notes', 'shifts.weekday_time_blocks', 'shifts.available');

        $this->addFilter('id', 'shifts.id');

        logger()->info('ShiftDataGrid QueryBuilder: '.$queryBuilder->toSql(), $queryBuilder->getBindings());

        return $queryBuilder;
    }

    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'available',
            'label'      => trans('admin::app.settings.shifts.fields.available'),
            'type'       => 'boolean',
            'searchable' => false,
            'filterable' => true,
            'sortable'   => false,
            'closure'    => function ($row) {
                $isAvailable = (bool) ($row->available ?? false);

                return $isAvailable
                    ? "<span class='icon-tick text-green-600 text-lg' title='".e(trans('admin::app.settings.shifts.fields.available'))."'></span>"
                    : "<span class='icon-cross-large text-red-600 text-lg' title='".e(trans('admin::app.settings.shifts.fields.available'))."'></span>";
            },
            'escape'     => false,
        ]);

        $this->addColumn([
            'index'      => 'id',
            'label'      => trans('admin::app.settings.shifts.datagrid.id'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'period_start',
            'type'       => 'date',
            'label'      => trans('admin::app.settings.shifts.fields.period_start'),
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'period_end',
            'type'       => 'date',
            'label'      => trans('admin::app.settings.shifts.fields.period_end'),
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => function ($row) {
                // NULL means infinite duration (oneindig geldig)
                if ($row->period_end === null || $row->period_end === '') {
                    return ''; // Lege string voor oneindig
                }

                return $row->period_end;
            },
        ]);

        $this->addColumn([
            'index'      => 'notes',
            'type'       => 'string',
            'label'      => trans('admin::app.settings.shifts.datagrid.notes'),
            'searchable' => true,
            'filterable' => false,
            'sortable'   => false,
        ]);

        $this->addColumn([
            'index'      => 'weekday_time_blocks',
            'type'       => 'string',
            'label'      => trans('admin::app.settings.shifts.fields.time_blocks'),
            'searchable' => false,
            'filterable' => false,
            'sortable'   => false,
            'closure'    => function ($row) {
                $labels = [
                    1 => trans('admin::app.monday'),
                    2 => trans('admin::app.tuesday'),
                    3 => trans('admin::app.wednesday'),
                    4 => trans('admin::app.thursday'),
                    5 => trans('admin::app.friday'),
                    6 => trans('admin::app.saturday'),
                    7 => trans('admin::app.sunday'),
                ];

                $data = $row->weekday_time_blocks;
                if (is_string($data)) {
                    $data = json_decode($data, true) ?: [];
                }

                $parts = [];
                for ($d = 1; $d <= 7; $d++) {
                    $blocks = $data[$d] ?? [];
                    if (! empty($blocks)) {
                        $ranges = [];
                        foreach ($blocks as $b) {
                            $from = $b['from'] ?? '';
                            $to = $b['to'] ?? '';
                            if ($from !== '' || $to !== '') {
                                $ranges[] = trim($from.'-'.$to, '-');
                            }
                        }
                        $parts[] = $labels[$d].': '.(empty($ranges) ? '—' : implode(', ', $ranges));
                    } else {
                        $parts[] = $labels[$d].': —';
                    }
                }

                return implode(' | ', $parts);
            },
        ]);
    }

    public function prepareActions(): void
    {
        $this->addAction([
            'index'  => 'edit',
            'icon'   => 'icon-edit',
            'title'  => trans('admin::app.settings.shifts.datagrid.edit'),
            'method' => 'GET',
            'url'    => function ($row) {
                return route('admin.settings.resources.shifts.edit', [request()->route('resourceId'), $row->id]);
            },
        ]);

        $this->addAction([
            'index'  => 'delete',
            'icon'   => 'icon-delete',
            'title'  => trans('admin::app.settings.shifts.datagrid.delete'),
            'method' => 'DELETE',
            'url'    => function ($row) {
                return route('admin.settings.resources.shifts.delete', [request()->route('resourceId'), $row->id]);
            },
        ]);
    }
}
