<?php

namespace Webkul\Admin\DataGrids\Settings;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Webkul\DataGrid\DataGrid;

class UserDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('users')
            ->distinct()
            ->addSelect(
                'id',
                DB::raw("TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) as name"),
                'email',
                'image',
                'status',
                'created_at'
            )
            ->leftJoin('user_groups', 'id', '=', 'user_groups.user_id');

        if ($userIds = bouncer()->getAuthorizedUserIds()) {
            $queryBuilder->whereIn('id', $userIds);
        }

        return $queryBuilder;
    }

    /**
     * Add columns.
     */
    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'    => 'id',
            'label'    => trans('admin::app.settings.users.index.datagrid.id'),
            'type'     => 'string',
            'sortable' => true,
        ]);

        $this->addColumn([
            'index'      => 'name',
            'label'      => trans('admin::app.settings.users.index.datagrid.name'),
            'type'       => 'string',
            'sortable'   => true,
            'searchable' => true,
            'filterable' => true,
            'closure'    => function ($row) {
                return [
                    'image' => $row->image ? Storage::url($row->image) : null,
                    'name'  => $row->name,
                ];
            },
        ]);

        $this->addColumn([
            'index'      => 'email',
            'label'      => trans('admin::app.settings.users.index.datagrid.email'),
            'type'       => 'string',
            'sortable'   => true,
            'searchable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index'      => 'status',
            'label'      => trans('admin::app.settings.users.index.datagrid.status'),
            'type'       => 'boolean',
            'filterable' => true,
            'sortable'   => true,
            'searchable' => true,
        ]);

        $this->addColumn([
            'index'           => 'created_at',
            'label'           => trans('admin::app.settings.users.index.datagrid.created-at'),
            'type'            => 'date',
            'sortable'        => true,
            'searchable'      => true,
            'filterable_type' => 'date_range',
            'filterable'      => true,
        ]);
    }

    /**
     * Prepare actions.
     */
    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('settings.user.users.edit')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => trans('admin::app.settings.users.index.datagrid.edit'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.settings.users.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('settings.user.users.delete')) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.settings.users.index.datagrid.delete'),
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.settings.users.delete', $row->id),
            ]);
        }
    }

    /**
     * Prepare mass actions.
     */
    public function prepareMassActions(): void
    {
        $this->addMassAction([
            'icon'   => 'icon-delete',
            'title'  => trans('admin::app.settings.users.index.datagrid.delete'),
            'method' => 'POST',
            'url'    => route('admin.settings.users.mass_delete'),
        ]);

        $this->addMassAction([
            'title'   => trans('admin::app.settings.users.index.datagrid.update-status'),
            'method'  => 'POST',
            'url'     => route('admin.settings.users.mass_update'),
            'options' => [
                [
                    'label' => trans('admin::app.settings.users.index.datagrid.active'),
                    'value' => 1,
                ],
                [
                    'label' => trans('admin::app.settings.users.index.datagrid.inactive'),
                    'value' => 0,
                ],
            ],
        ]);
    }

    /**
     * Override the processRequestedFilters method to handle name search properly.
     */
    protected function processRequestedFilters(array $requestedFilters)
    {
        foreach ($requestedFilters as $requestedColumn => $requestedValues) {
            if ($requestedColumn === 'all') {
                $this->queryBuilder->where(function ($scopeQueryBuilder) use ($requestedValues) {
                    foreach ($requestedValues as $value) {
                        collect($this->columns)
                            ->filter(fn ($column) => $column->getSearchable() && ! in_array($column->getType(), [
                                \Webkul\DataGrid\Enums\ColumnTypeEnum::BOOLEAN->value,
                                \Webkul\DataGrid\Enums\ColumnTypeEnum::AGGREGATE->value,
                            ]))
                            ->each(function ($column) use ($scopeQueryBuilder, $value) {
                                if ($column->getIndex() === 'name') {
                                    // Handle name search with first_name and last_name
                                    $scopeQueryBuilder->orWhere(function ($nameQuery) use ($value) {
                                        $nameQuery->where('users.first_name', 'LIKE', '%'.$value.'%')
                                                 ->orWhere('users.last_name', 'LIKE', '%'.$value.'%')
                                                 ->orWhereRaw("CONCAT(users.first_name, ' ', users.last_name) LIKE ?", ['%'.$value.'%']);
                                    });
                                } else {
                                    $scopeQueryBuilder->orWhere($column->getColumnName(), 'LIKE', '%'.$value.'%');
                                }
                            });
                    }
                });
            } else {
                $column = collect($this->columns)
                    ->first(fn ($column) => $column->getIndex() === $requestedColumn);

                // Gracefully skip unknown filter keys instead of crashing
                if ($column) {
                    if ($column->getIndex() === 'name') {
                        // Handle name search with first_name and last_name
                        $this->queryBuilder->where(function ($nameQuery) use ($requestedValues) {
                            foreach ($requestedValues as $value) {
                                $nameQuery->where(function ($subQuery) use ($value) {
                                    $subQuery->where('users.first_name', 'LIKE', '%'.$value.'%')
                                             ->orWhere('users.last_name', 'LIKE', '%'.$value.'%')
                                             ->orWhereRaw("CONCAT(users.first_name, ' ', users.last_name) LIKE ?", ['%'.$value.'%']);
                                });
                            }
                        });
                    } else {
                        $column->processFilter($this->queryBuilder, $requestedValues);
                    }
                }
            }
        }

        return $this->queryBuilder;
    }
}
