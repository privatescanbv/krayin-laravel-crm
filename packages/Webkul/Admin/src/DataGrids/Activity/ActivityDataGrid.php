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
                DB::raw("CONCAT(users.first_name, ' ', users.last_name) as created_by"),
                'groups.name as group_name',
                // Also select related entity names where possible
                'persons.name as person_name',
                'products.name as product_name',
                'warehouses.name as warehouse_name',
                'folders.name as folder_name',
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
            ->leftJoin('emails', 'activities.id', '=', 'emails.activity_id')
            ->leftJoin('folders', 'emails.folder_id', '=', 'folders.id')
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
        $this->addFilter('created_by', DB::raw("CONCAT(users.first_name, ' ', users.last_name)"));
        $this->addFilter('assigned_user_id', 'users.id');
        $this->addFilter('created_at', 'activities.created_at');
        $this->addFilter('days_until_deadline', 'days_until_deadline');
        $this->addFilter('group', 'groups.name');

        /**
         * Special handling for "Gerelateerd aan" (entity_type) filter.
         * The column is a SELECT alias from a CASE expression; MySQL does not allow
         * referencing a SELECT alias in the WHERE clause. We therefore translate any
         * incoming filter for `entity_type` into a HAVING clause and remove it from
         * the generic datagrid filters so the core engine does not try to add a WHERE.
         */
        $filters = request()->input('filters', []);
        if (isset($filters['entity_type'])) {
            $values = $filters['entity_type'];
            if (!is_array($values)) {
                $values = [$values];
            }
            // Clean empty values
            $values = array_values(array_filter($values, static fn($v) => $v !== null && $v !== ''));

            if (!empty($values)) {
                $queryBuilder->having(function ($q) use ($values) {
                    foreach ($values as $i => $value) {
                        if ($i === 0) {
                            $q->having('entity_type', '=', $value);
                        } else {
                            $q->orHaving('entity_type', '=', $value);
                        }
                    }
                });
            }

            // Remove to avoid WHERE on alias by the generic filter application
            unset($filters['entity_type']);
        }

        /**
         * Special handling for "Toegewezen aan" (assigned_user_id) filter.
         * Uses entity selector filter method from base class.
         */
        $this->applyEntitySelectorFilter($queryBuilder, $filters, 'assigned_user_id', 'users.id');
        
        // Update request with cleaned filters
        $originalFilters = request()->input('filters');
        if (!empty($filters)) {
            request()->merge(['filters' => $filters]);
        } elseif ($originalFilters !== null) {
            // If filters was present but is now empty, remove it entirely to avoid validation issues
            request()->request->remove('filters');
            request()->query->remove('filters');
        }

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
            // Show as icon instead of text label
            'closure'            => function ($row) {
                $map = [
                    'call'    => 'icon-call',
                    'email'   => 'icon-mail',
                    'note'    => 'icon-note',
                    'meeting' => 'icon-activity',
                    'task'    => 'icon-activity',
                    'file'    => 'icon-file',
                    'system'  => 'icon-system-generate',
                ];
                $icon = $map[$row->type] ?? 'icon-activity';
                return "<span class='".$icon." text-lg'></span>";
            },
            'width'              => '20px',
        ]);

        $this->addColumn([
            'index'              => 'assigned_user_id',
            'label'              => trans('admin::app.activities.index.datagrid.assigned_to'),
            'type'               => 'string',
            'searchable'         => false,
            'sortable'           => true,
            'filterable'         => true,
            'filterable_type'    => 'entity_selector',
            'filterable_options' => [
                'search_route' => route('admin.settings.users.search'),
                'entity_type'  => 'user',
            ],
            'closure'    => function ($row) {
                $route = urldecode(route('admin.settings.users.index', ['id[eq]' => $row->assigned_user_id]));

                return "<a class='text-brandColor hover:underline' href='".$route."'>".$row->created_by.'</a>';
            },
        ]);

        // Removed comment column as requested


        // Status column removed from UI (kept in DB/entity)

        // Removed 'Oppakken vanaf' column as requested

        // Created date column
        $this->addColumn([
            'index'      => 'created_at',
            'label'      => 'Aangemaakt op',
            'type'       => 'datetime',
            'sortable'   => true,
            'searchable' => true,
            'filterable' => false,
            'closure'    => function ($row) {
                if (empty($row->created_at)) {
                    return 'N/A';
                }

                $timestamp = strtotime($row->created_at);
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
                    return "<span class='text-gray-500'>N/A</span>";
                }

                $timestamp = strtotime($row->schedule_to);
                if ($timestamp === false) {
                    return "<span class='text-gray-500'>N/A</span>";
                }

                $date = date('d-m-Y H:i', $timestamp);
                $days = (int)($row->days_until_deadline ?? 0);
                if ($days < 0) {
                    $classes = 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
                } elseif ($days === 0) {
                    $classes = 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
                } else {
                    $classes = 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
                }
                return "<span class='px-2 py-0.5 rounded-full text-xs ".$classes."'>".$date.'</span>';
            },
        ]);

        // Hidden technical column remains for filtering, plus visual "done" icon column at the end
        $this->addColumn([
            'index'      => 'is_done',
            'label'      => 'Afgerond',
            'type'       => 'boolean',
            'searchable' => false,
            'filterable' => true,
            'sortable'   => false,
            'visibility' => false,
        ]);

        // Visual done indicator (last column)
        $this->addColumn([
            'index'      => 'is_done_symbol',
            'label'      => 'Gereed',
            'type'       => 'string',
            'searchable' => false,
            'filterable' => false,
            'sortable'   => false,
            'closure'    => function ($row) {
                if ((int)($row->is_done ?? 0) === 1) {
                    return "<span class='icon-tick text-green-600 text-xl' title='Afgerond'></span>";
                }
                return '';
            },
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
