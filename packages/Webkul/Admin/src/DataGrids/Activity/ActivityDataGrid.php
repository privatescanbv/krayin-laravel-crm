<?php

namespace Webkul\Admin\DataGrids\Activity;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;
use Webkul\Activity\Services\ViewService;
use Webkul\Admin\Traits\ProvideDropdownOptions;
use Webkul\DataGrid\DataGrid;
use Webkul\Lead\Models\Lead;
use Webkul\Contact\Models\Person;
use Webkul\User\Repositories\UserRepository;

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
                'leads.lead_pipeline_id',
                'users.id as assigned_user_id',
                'users.name as created_by',
                'groups.name as group_name',
                // Also select related entity names where possible
                'persons.name as person_name',
                'products.name as product_name',
                'warehouses.name as warehouse_name',
                // Cross-DB days until deadline: use different SQL per driver
                DB::raw((function () {
                    $driver = DB::connection()->getDriverName();
                    if ($driver === 'sqlite') {
                        // julianday returns day fractions; round to integer difference
                        return "CAST((julianday(activities.schedule_to) - julianday('now')) AS INTEGER) as days_until_deadline";
                    }
                    if ($driver === 'pgsql') {
                        return "(DATE(activities.schedule_to) - CURRENT_DATE) as days_until_deadline";
                    }
                    // default MySQL/MariaDB
                    return 'DATEDIFF(activities.schedule_to, CURDATE()) as days_until_deadline';
                })()),
                // Add entity information
                DB::raw('CASE
                    WHEN activities.lead_id IS NOT NULL THEN "lead"
                    WHEN EXISTS (SELECT 1 FROM person_activities WHERE activity_id = activities.id LIMIT 1) THEN "person"
                    WHEN EXISTS (SELECT 1 FROM product_activities WHERE activity_id = activities.id LIMIT 1) THEN "product"
                    WHEN EXISTS (SELECT 1 FROM warehouse_activities WHERE activity_id = activities.id LIMIT 1) THEN "warehouse"
                    ELSE NULL
                END as entity_type'),
                DB::raw('CASE
                    WHEN activities.lead_id IS NOT NULL THEN activities.lead_id
                    WHEN EXISTS (SELECT 1 FROM person_activities WHERE activity_id = activities.id LIMIT 1) THEN (SELECT person_id FROM person_activities WHERE activity_id = activities.id LIMIT 1)
                    WHEN EXISTS (SELECT 1 FROM product_activities WHERE activity_id = activities.id LIMIT 1) THEN (SELECT product_id FROM product_activities WHERE activity_id = activities.id LIMIT 1)
                    WHEN EXISTS (SELECT 1 FROM warehouse_activities WHERE activity_id = activities.id LIMIT 1) THEN (SELECT warehouse_id FROM warehouse_activities WHERE activity_id = activities.id LIMIT 1)
                    ELSE NULL
                END as entity_id')
            )

            ->leftJoin('leads', 'activities.lead_id', '=', 'leads.id')
            ->leftJoin('users', 'activities.user_id', '=', 'users.id')
            ->leftJoin('groups', 'activities.group_id', '=', 'groups.id')
            // Joins to fetch display names for related entities
            ->leftJoin('person_activities', 'activities.id', '=', 'person_activities.activity_id')
            ->leftJoin('persons', 'person_activities.person_id', '=', 'persons.id')
            ->leftJoin('product_activities', 'activities.id', '=', 'product_activities.activity_id')
            ->leftJoin('products', 'product_activities.product_id', '=', 'products.id')
            ->leftJoin('warehouse_activities', 'activities.id', '=', 'warehouse_activities.activity_id')
            ->leftJoin('warehouses', 'warehouse_activities.warehouse_id', '=', 'warehouses.id')
            ->whereIn('type', ['call', 'meeting','task'])
            ->when(!auth()->guard('user')->user()?->isGlobalAdmin(), function ($query) {
                $query->where(function ($query) {
                    if ($userIds = bouncer()->getAuthorizedUserIds()) {
                        $query->whereIn('activities.user_id', $userIds)
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
                });
            })->groupBy('activities.id', 'leads.id', 'users.id', 'groups.id');

        // Apply view filters - use default view if none specified
        $viewService = app(ViewService::class);
        $view = request()->get('view');
        if (!$view) {
            $defaultView = $viewService->getDefaultView();
            $view = $defaultView['key'];
        }

        // Get view configuration to add filters to the interface
        $viewConfig = $viewService->getView($view);
        if ($viewConfig) {
            // Always apply view filters on the query (supports custom filters and OR logic)
            $queryBuilder = $viewService->applyViewFilters($queryBuilder, $view);

            // Additionally, mirror simple filters into the datagrid request 'filters' so both paths behave consistently
            $requestedFilters = request()->input('filters', []);
            foreach ($viewConfig['filters'] as $filter) {
                // Only mirror non-custom filters; custom ones are handled server-side only
                if (($filter['operator'] ?? 'eq') !== 'custom') {
                    $columnKey = $filter['column'];
                    $value = $filter['value'];
                    if (!isset($requestedFilters[$columnKey]) || !is_array($requestedFilters[$columnKey])) {
                        $requestedFilters[$columnKey] = [];
                    }
                    // Avoid duplicate entries
                    if (!in_array($value, $requestedFilters[$columnKey], true)) {
                        $requestedFilters[$columnKey][] = $value;
                    }
                }
            }
            request()->merge(['filters' => $requestedFilters]);
        }

        // Default sorting: urgent tasks first, then newest
        // Only skip when an actual sort column is provided (front-end may send empty sort object)
        $requestedSort = request()->input('sort');
        if (!is_array($requestedSort) || empty($requestedSort['column'])) {
            $queryBuilder->orderByRaw('
                CASE WHEN days_until_deadline IS NULL THEN 1 ELSE 0 END,
                days_until_deadline ASC
            ');
        }

        $this->addFilter('id', 'activities.id');
        $this->addFilter('title', 'activities.title');
        $this->addFilter('type', 'activities.type');
        $this->addFilter('status', 'activities.status');
        $this->addFilter('is_done', 'activities.is_done');
        $this->addFilter('created_by', 'users.name');
        $this->addFilter('assigned_user_id', 'users.name');
        $this->addFilter('created_at', 'activities.created_at');
        $this->addFilter('days_until_deadline', 'days_until_deadline');
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
            'visibility' => false, // Hide ID column from view
        ]);

        $this->addColumn([
            'index'      => 'title',
            'label'      => trans('admin::app.activities.index.datagrid.title'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        // Place "Gerelateerd aan" (entity_type) before "type"
        $this->addColumn([
            'index'              => 'entity_type',
            'label'              => 'Gerelateerd aan',
            'type'               => 'string',
            'searchable'         => false,
            'sortable'           => true,
            'filterable'         => true,
            'filterable_type'    => 'dropdown',
            'filterable_options' => [
                ['label' => 'Alles', 'value' => ''],
                ['label' => 'Lead', 'value' => 'lead'],
                ['label' => 'Person', 'value' => 'person'],
                ['label' => 'Product', 'value' => 'product'],
                ['label' => 'Warehouse', 'value' => 'warehouse'],
            ],
            'closure'    => function ($row) {
                if (!$row->entity_type || !$row->entity_id) {
                    return "<span class='text-gray-800 dark:text-gray-300'>N/A</span>";
                }

                $route = '';
                $label = '';

                switch ($row->entity_type) {
                    case 'lead':
                        $route = route('admin.leads.view', $row->entity_id);
                        // Try to resolve lead name via model accessor; fallback to #ID
                        try {
                            $lead = Lead::find($row->entity_id);
                            $display = $lead ? ($lead->name ?? ('#'.$row->entity_id)) : ('#'.$row->entity_id);
                        } catch (Throwable $e) {
                            logger()->warning('Unable to locate lead entity id '.$row->entity_id . ', '. $e->getMessage());
                            $display = '#'.$row->entity_id;
                        }
                        $label = e($display);
                        break;
                    case 'person':
                        $route = route('admin.contacts.persons.view', $row->entity_id);
                        // Try to resolve person name via model accessor; fallback to #ID
                        try {
                            $person = Person::find($row->entity_id);
                            $display = $person ? $person->name : ('#'.$row->entity_id);
                        } catch (Throwable $e) {
                            logger()->warning('Unable to locate person entity id '.$row->entity_id . ', '. $e->getMessage());
                            $display = '#'.$row->entity_id;
                        }
                        $label = e($display);
                        break;
                    case 'product':
                        $route = route('admin.products.view', $row->entity_id);
                        $display = $row->product_name ?: ('#'.$row->entity_id);
                        $label = e($display);
                        break;
                    case 'warehouse':
                        $route = route('admin.warehouses.view', $row->entity_id);
                        $display = $row->warehouse_name ?: ('#'.$row->entity_id);
                        $label = e($display) . '"';
                        break;
                    default:
                        return "<span class='text-gray-800 dark:text-gray-300'>Onbekend</span>";
                }

                return "<a class='text-brandColor hover:underline' target='_blank' href='".$route."'>".$label.'</a>';
            },
        ]);

        $this->addColumn([
            'index'              => 'type',
            'label'              => trans('admin::app.activities.index.datagrid.type'),
            'type'               => 'string',
            'searchable'         => false,
            'filterable'         => true,
            'filterable_type'    => 'dropdown',
            'filterable_options' => $this->getActivityTypeDropdownOptions(),
            'sortable'           => true,
            'closure'            => fn ($row) => trans('admin::app.activities.edit.'.$row->type),
            'width'              => '20px',
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

        // Removed comment column as requested


        $this->addColumn([
            'index'              => 'status',
            'label'              => 'Status',
            'type'               => 'string',
            'searchable'         => false,
            'filterable'         => true,
            'filterable_type'    => 'dropdown',
            'filterable_options' => $this->getActivityStatusDropdownOptions(),
            'sortable'           => true,
            'closure'            => function ($row) {
                $statusLabels = [
                    'in_progress' => '<span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full dark:bg-blue-900 dark:text-blue-300">In behandeling</span>',
                    'active' => '<span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full dark:bg-green-900 dark:text-green-300">Actief</span>',
                    'on_hold' => '<span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full dark:bg-yellow-900 dark:text-yellow-300">On hold</span>',
                    'expired' => '<span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full dark:bg-red-900 dark:text-red-300">Verlopen</span>',
                ];
                $status = $row->status === 'new' ? 'active' : ($row->status ?? 'active');
                return $statusLabels[$status] ?? $status;
            },
        ]);

        $this->addColumn([
            'index'      => 'schedule_from',
            'label'      => 'Oppakken vanaf',
            'type'       => 'datetime',
            'sortable'   => true,
            'searchable' => true,
            'filterable' => false,
            'closure'    => function ($row) {
                if (empty($row->schedule_from)) {
                    return 'N/A';
                }

                $timestamp = strtotime($row->schedule_from);
                if ($timestamp === false) {
                    return 'N/A';
                }

                return date('d-m-Y H:i', $timestamp);
            },
        ]);

        $this->addColumn([
            'index'      => 'schedule_to',
            'label'      => 'Deadline',
            'type'       => 'datetime',
            'sortable'   => true,
            'searchable' => true,
            'filterable' => false,
            'closure'    => function ($row) {
                if (empty($row->schedule_to)) {
                    return 'N/A';
                }

                $timestamp = strtotime($row->schedule_to);
                if ($timestamp === false) {
                    return 'N/A';
                }

                return date('d-m-Y H:i', $timestamp);
            },
        ]);

        // Hidden column to support filtering by group via URL params like filters[group][0]=...
        $this->addColumn([
            'index'      => 'group',
            'label'      => 'Groep',
            'type'       => 'string',
            'searchable' => false,
            'filterable' => true,
            'sortable'   => false,
            'visibility' => false,
        ]);

        // Hidden column to support filtering by is_done via mirrored view filters
        $this->addColumn([
            'index'      => 'is_done',
            'label'      => 'Afgerond',
            'type'       => 'boolean',
            'searchable' => false,
            'filterable' => true,
            'sortable'   => false,
            'visibility' => false,
        ]);
    }

    /**
     * Prepare actions.
     */
    public function prepareActions(): void
    {
        // View action (eye icon)
        $this->addAction([
            'index'  => 'view',
            'icon'   => 'icon-eye',
            'title'  => trans('admin::app.activities.index.datagrid.view'),
            'method' => 'GET',
            'url'    => fn ($row) => route('admin.activities.view', $row->id),
        ]);

        if (bouncer()->hasPermission('activities.edit')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => trans('admin::app.activities.index.datagrid.edit'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.activities.edit', $row->id),
            ]);
        }

        // Remove delete action from datagrid as requested

        // Add unassign action for activities assigned to current user
        $currentUserId = auth()->guard('user')->id();
        $this->addAction([
            'index'  => 'unassign',
            'icon'   => 'icon-undo',
            'title'  => 'Ontkoppelen',
            'method' => 'POST',
            'url'    => fn ($row) => route('admin.activities.unassign', $row->id),
            'condition' => fn ($row) => $row->user_id == $currentUserId,
        ]);
    }

    /**
     * Prepare mass actions.
     */
    public function prepareMassActions(): void
    {
        // Intentionally left blank: no bulk/mass actions; also hides bulk selection UI
    }

}
