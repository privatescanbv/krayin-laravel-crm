<?php

namespace Webkul\Admin\DataGrids\Activity;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\Activity\Services\ViewService;
use Webkul\Admin\Traits\ProvideDropdownOptions;
use Webkul\DataGrid\DataGrid;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\User\Repositories\UserRepository;
use Webkul\User\Repositories\GroupRepository;

class ActivityDataGrid extends DataGrid
{
    use ProvideDropdownOptions;

    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('activities')
            ->distinct()
            ->select(
                'activities.*',
                'leads.id as lead_id',
                'leads.title as lead_title',
                'leads.lead_pipeline_id',
                'users.id as assigned_user_id',
                'users.name as created_by',
                'groups.name as group_name',
                DB::raw('DATEDIFF(activities.schedule_to, CURDATE()) as days_until_deadline')
            )
            ->leftJoin('activity_participants', 'activities.id', '=', 'activity_participants.activity_id')
            ->leftJoin('lead_activities', 'activities.id', '=', 'lead_activities.activity_id')
            ->leftJoin('leads', 'lead_activities.lead_id', '=', 'leads.id')
            ->leftJoin('users', 'activities.user_id', '=', 'users.id')
            ->leftJoin('groups', 'activities.group_id', '=', 'groups.id')
            ->whereIn('type', ['call', 'meeting','task'])
            ->where(function ($query) {
                if ($userIds = bouncer()->getAuthorizedUserIds()) {
                    $query->whereIn('activities.user_id', $userIds)
                        ->orWhereIn('activity_participants.user_id', $userIds)
                        ->orWhere(function ($query) use ($userIds) {
                            $query->whereNotNull('activities.group_id')
                                ->whereExists(function ($query) use ($userIds) {
                                    $query->select(DB::raw(1))
                                        ->from('user_groups')
                                        ->whereColumn('user_groups.group_id', 'activities.group_id')
                                        ->whereIn('user_groups.user_id', $userIds);
                                });
                        });
                }
            })->groupBy('activities.id', 'leads.id', 'users.id', 'groups.id');

        // Apply view filters - use default view if none specified
        $viewService = app(ViewService::class);
        $view = request()->get('view');
        if (!$view) {
            $defaultView = $viewService->getDefaultView();
            $view = $defaultView['key'];
        }
        $queryBuilder = $viewService->applyViewFilters($queryBuilder, $view);

        // Default sorting: urgent tasks first, then newest
        if (!request()->has('sort')) {
            $queryBuilder->orderByRaw('
                CASE WHEN days_until_deadline IS NULL THEN 1 ELSE 0 END,
                days_until_deadline ASC, 
                activities.created_at DESC
            ');
        }

        $this->addFilter('id', 'activities.id');
        $this->addFilter('title', 'activities.title');
        $this->addFilter('is_done', 'activities.is_done');
        $this->addFilter('created_by', 'users.name');
        $this->addFilter('assigned_user_id', 'users.name');
        $this->addFilter('created_at', 'activities.created_at');
        $this->addFilter('days_until_deadline', 'days_until_deadline');
        $this->addFilter('lead_title', 'leads.title');
        $this->addFilter('group', 'groups.name');

        return $queryBuilder;
    }

    /**
     * Prepare columns.
     */
    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'id',
            'label'      => trans('admin::app.activities.index.datagrid.id'),
            'type'       => 'integer',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index' => 'is_done',
            'label' => trans('admin::app.activities.index.datagrid.is_done'),
            'type' => 'string',
            'dropdown_options' => $this->getBooleanDropdownOptions('yes_no'),
            'searchable' => false,
            'filterable' => true,
            'filterable_type'  => 'dropdown', // <-- gewone dropdown, niet searchable
            'filterable_options' => [
                [
                    'label' => 'Alles',
                    'value' => '',
                ],
                [
                    'label' => 'Afgerond',
                    'value' => '1',
                ],
                [
                    'label' => 'Open',
                    'value' => '0',
                ],
            ],
            'closure' => fn($row) => view('admin::activities.datagrid.is-done', compact('row'))->render(),
        ]);

        $this->addColumn([
            'index'      => 'title',
            'label'      => trans('admin::app.activities.index.datagrid.title'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'              => 'assigned_user_id',
            'label'              => trans('admin::app.activities.index.datagrid.assigned_to'),
            'type'               => 'string',
            'searchable'         => false,
            'sortable'           => true,
            'filterable'         => true,
            'filterable_type'    => 'searchable_dropdown',
            'filterable_options' => [
                'repository' => UserRepository::class,
                'column'     => [
                    'label' => 'name',
                    'value' => 'name',
                ],
            ],
            'closure'    => function ($row) {
                $route = urldecode(route('admin.settings.users.index', ['id[eq]' => $row->assigned_user_id]));

                return "<a class='text-brandColor hover:underline' href='".$route."'>".$row->created_by.'</a>';
            },
        ]);

        $this->addColumn([
            'index'              => 'group',
            'label'              => trans('admin::app.activities.index.datagrid.group'),
            'type'               => 'string',
            'searchable'         => false,
            'sortable'           => true,
            'filterable'         => true,
            'filterable_type'    => 'searchable_dropdown',
            'filterable_options' => [
                'repository' => GroupRepository::class,
                'column'     => [
                    'label' => 'name',
                    'value' => 'name',
                ],
            ],
            'closure' => function ($row) {
                return $row->group_name ?? 'N/A';
            },
        ]);

        $this->addColumn([
            'index'   => 'comment',
            'label'   => trans('admin::app.activities.index.datagrid.comment'),
            'type'    => 'string',
        ]);

        $this->addColumn([
            'index'              => 'lead_title',
            'label'              => trans('admin::app.activities.index.datagrid.lead'),
            'type'               => 'string',
            'searchable'         => true,
            'sortable'           => true,
            'filterable'         => true,
            'filterable_type'    => 'searchable_dropdown',
            'filterable_options' => [
                'repository' => LeadRepository::class,
                'column'     => [
                    'label' => 'title',
                    'value' => 'title',
                ],
            ],
            'closure'    => function ($row) {
                if ($row->lead_title == null) {
                    return "<span class='text-gray-800 dark:text-gray-300'>N/A</span>";
                }

                $route = urldecode(route('admin.leads.view', $row->lead_id));

                return "<a class='text-brandColor hover:underline' target='_blank' href='".$route."'>".$row->lead_title.'</a>';
            },
        ]);

        $this->addColumn([
            'index'      => 'type',
            'label'      => trans('admin::app.activities.index.datagrid.type'),
            'type'       => 'string',
            'searchable' => false,
            'filterable' => false,
            'sortable'   => true,
            'closure'    => fn ($row) => trans('admin::app.activities.index.datagrid.'.$row->type),
        ]);

        $this->addColumn([
            'index'      => 'created_at',
            'label'      => trans('admin::app.activities.index.datagrid.created_at'),
            'type'       => 'date',
            'sortable'   => true,
            'searchable' => true,
            'filterable' => true,
            'closure'    => fn ($row) => core()->formatDate($row->created_at, 'd M Y'),
        ]);

        $this->addColumn([
            'index'      => 'days_until_deadline',
            'label'      => 'Dagen tot deadline',
            'type'       => 'integer',
            'sortable'   => true,
            'searchable' => false,
            'filterable' => true,
            'closure'    => function ($row) {
                $days = $row->days_until_deadline;
                if ($days === null) {
                    return 'N/A';
                } elseif ($days < 0) {
                    return '<span class="text-red-600 font-semibold">' . abs($days) . ' dagen over tijd</span>';
                } elseif ($days == 0) {
                    return '<span class="text-orange-600 font-semibold">Vandaag</span>';
                } elseif ($days <= 3) {
                    return '<span class="text-yellow-600 font-semibold">' . $days . ' dagen</span>';
                } else {
                    return '<span class="text-green-600">' . $days . ' dagen</span>';
                }
            },
        ]);
    }

    /**
     * Prepare actions.
     */
    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('activities.edit')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => trans('admin::app.activities.index.datagrid.edit'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.activities.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('activities.delete')) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.activities.index.datagrid.update'),
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.activities.delete', $row->id),
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
            'title'  => trans('admin::app.activities.index.datagrid.mass-delete'),
            'method' => 'POST',
            'url'    => route('admin.activities.mass_delete'),
        ]);

        $this->addMassAction([
            'title'   => trans('admin::app.activities.index.datagrid.mass-update'),
            'url'     => route('admin.activities.mass_update'),
            'method'  => 'POST',
            'options' => [
                [
                    'label' => trans('admin::app.activities.index.datagrid.done'),
                    'value' => 1,
                ], [
                    'label' => trans('admin::app.activities.index.datagrid.not-done'),
                    'value' => 0,
                ],
            ],
        ]);
    }
}
