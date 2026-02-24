<?php

namespace App\DataGrids\Settings;

use App\Repositories\ClinicRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class ResourceDataGrid extends DataGrid
{
    /**
     * Ensure default sorting uses a qualified column to avoid ambiguity when joins are present.
     */
    protected $sortColumn = 'resources.id';

    public function __construct(protected ClinicRepository $clinicRepository) {}

    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('resources')
            ->leftJoin('resource_types', 'resource_types.id', '=', 'resources.resource_type_id')
            ->leftJoin('clinics', 'clinics.id', '=', 'resources.clinic_id')
            ->addSelect(
                'resources.id',
                'resources.name',
                'resources.clinic_id',
                'resources.is_active',
                'resource_types.id as resource_type_id',
                'resource_types.name as resource_type_name',
                'clinics.name as clinic_name'
            );

        $this->addFilter('id', 'resources.id');
        $this->addFilter('is_active', 'resources.is_active');
        // Note: resource_type_name and clinic_id filters are handled specially below

        // Default filter: only active resources unless user provides a filter
        $requestedFilters = request()->input('filters', []);
        if (! array_key_exists('is_active', $requestedFilters)) {
            $queryBuilder->where('resources.is_active', 1);
        }

        // Apply entity selector filter for resource_type_name
        $this->applyEntitySelectorFilter($queryBuilder, $requestedFilters, 'resource_type_name', 'resource_types.id');

        // Apply entity selector filter for clinic_id
        $this->applyEntitySelectorFilter($queryBuilder, $requestedFilters, 'clinic_id', 'clinics.id');

        // Update request with cleaned filters
        $originalFilters = request()->input('filters');
        if (! empty($requestedFilters)) {
            request()->merge(['filters' => $requestedFilters]);
        } elseif ($originalFilters !== null) {
            // If filters was present but is now empty, remove it entirely to avoid validation issues
            request()->request->remove('filters');
            request()->query->remove('filters');
        }

        return $queryBuilder;
    }

    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'id',
            'label'      => trans('admin::app.settings.resources.index.datagrid.id'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
            'columnName' => 'resources.id',
        ]);

        // Removed deprecated 'type' column; it's replaced by relation to ResourceType

        $this->addColumn([
            'index'      => 'name',
            'type'       => 'string',
            'label'      => trans('admin::app.settings.resources.index.datagrid.name'),
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
            'columnName' => 'resources.name',
        ]);

        $this->addColumn([
            'index'              => 'resource_type_name',
            'type'               => 'string',
            'label'              => trans('admin::app.settings.resources.index.datagrid.resource_type'),
            'searchable'         => true,
            'filterable'         => true,
            'filterable_type'    => 'entity_selector',
            'filterable_options' => [
                'search_route' => route('admin.settings.resource_types.search'),
                'entity_type'  => 'resource_type',
            ],
            'sortable'   => true,
            'columnName' => 'resource_types.name',
        ]);

        $this->addColumn([
            'index'      => 'clinic_name',
            'type'       => 'string',
            'label'      => trans('admin::app.settings.resources.index.datagrid.clinic'),
            'searchable' => true,
            'filterable' => false,
            'sortable'   => true,
            'columnName' => 'clinics.name',
        ]);

        $this->addColumn([
            'index'      => 'is_active',
            'type'       => 'boolean',
            'label'      => 'Actief',
            'searchable' => false,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => function ($row) {
                $active = $row->is_active ?? false;

                return $active
                    ? "<span class='icon-tick text-status-active-text text-lg' title='".e(trans('admin::app.settings.clinics.index.datagrid.is_active'))."'></span>"
                    : "<span class='icon-cross-large text-status-expired-text text-lg' title='".e(trans('admin::app.settings.clinics.index.datagrid.is_active'))."'></span>";
            },
        ]);

        // Clinic filter column (using entity selector)
        $this->addColumn([
            'index'              => 'clinic_id',
            'type'               => 'string',
            'label'              => trans('admin::app.settings.resources.index.datagrid.clinic'),
            'searchable'         => false,
            'filterable'         => true,
            'sortable'           => false,
            'filterable_type'    => 'entity_selector',
            'filterable_options' => [
                'search_route' => route('admin.clinics.search'),
                'entity_type'  => 'clinic',
            ],
            'visibility'        => false, // Hidden from view, only used for filtering
        ]);
    }

    public function prepareActions(): void
    {
        $this->addAction([
            'index'  => 'view',
            'icon'   => 'icon-eye',
            'title'  => trans('admin::app.settings.resources.index.datagrid.view'),
            'method' => 'GET',
            'url'    => fn ($row) => route('admin.settings.resources.show', $row->id),
        ]);

        if (bouncer()->hasPermission('settings.resources.edit')) {
            $this->addAction([
                'index'  => 'manage-shifts',
                'icon'   => 'icon-calendar',
                'title'  => trans('admin::app.settings.resources.index.manage-shifts'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.settings.resources.shifts.index', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('settings.resources.edit')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => trans('admin::app.settings.resources.index.datagrid.edit'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.settings.resources.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('settings.resources.delete')) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.settings.resources.index.datagrid.delete'),
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.settings.resources.delete', $row->id),
            ]);
        }
    }

    /**
     * Default sorting: active resources first, then by name ASC.
     * Only applies when no explicit sort is requested by the client.
     */
    protected function processRequestedSorting($requestedSort)
    {
        if (empty($requestedSort) || empty($requestedSort['column'])) {
            // Reset any existing order and apply our default
            $this->queryBuilder->reorder()
                ->orderBy('resources.is_active', 'desc')
                ->orderBy('resources.name', 'asc');

            return $this->queryBuilder;
        }

        return parent::processRequestedSorting($requestedSort);
    }
}
