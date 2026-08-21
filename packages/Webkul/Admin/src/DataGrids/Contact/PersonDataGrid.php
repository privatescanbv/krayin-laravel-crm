<?php

namespace Webkul\Admin\DataGrids\Contact;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\DataGrid\DataGrid;

class PersonDataGrid extends DataGrid
{
    /**
     * Create a new class instance.
     *
     * @return void
     */
    public function __construct(
        protected OrganizationRepository $organizationRepository
    ) {}

    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('persons')
            ->addSelect(
                'persons.id',
                DB::raw("CONCAT_WS(' ',
                    NULLIF(persons.first_name, ''),
                    NULLIF(persons.lastname_prefix, ''),
                    NULLIF(persons.last_name, ''),
                    '; ',
                    NULLIF(persons.married_name_prefix, ''),
                    NULLIF(persons.married_name, '')
                ) as person_name"),
                'persons.emails',
                'persons.phones',
                'persons.date_of_birth',
                'persons.is_active',
                'persons.has_duplicates',
                'persons.keycloak_user_id',
                'organizations.name as organization',
                'organizations.id as organization_id'
            )
            ->leftJoin('organizations', 'persons.organization_id', '=', 'organizations.id');

        if ($userIds = bouncer()->getAuthorizedUserIds()) {
            $queryBuilder->whereIn('persons.user_id', $userIds);
        }

        /**
         * Soft deletes: hide deleted persons by default.
         *
         * We handle the filter manually (and remove it from request filters) because
         * the generic filter engine only supports simple comparisons, not NULL checks.
         */
        $requestedFilters = request()->input('filters', []);

        $trashedValues = $requestedFilters['trashed'] ?? null;
        $trashedValues = is_array($trashedValues) ? $trashedValues : [$trashedValues];
        $trashedValues = array_values(array_filter($trashedValues, static fn ($v) => $v !== null && $v !== ''));

        // Default: only non-deleted, unless user explicitly selects otherwise.
        if (! array_key_exists('trashed', $requestedFilters) || empty($trashedValues) || in_array('without', $trashedValues, true)) {
            $queryBuilder->whereNull('persons.deleted_at');
        } elseif (in_array('only', $trashedValues, true)) {
            $queryBuilder->whereNotNull('persons.deleted_at');
        } elseif (in_array('with', $trashedValues, true)) {
            // include both deleted + non-deleted (no constraint)
        } else {
            // Unknown value: keep safe default behavior.
            $queryBuilder->whereNull('persons.deleted_at');
        }

        // Remove this custom filter so the datagrid core won't try to apply it as an equality filter.
        unset($requestedFilters['trashed']);

        // Auto-fill missing from/to for single-value date_of_birth filter.
        // Dates arrive as [[from, to]] (numeric indices, not string keys).
        $dobFilter = $requestedFilters['date_of_birth'] ?? null;
        if (is_array($dobFilter) && isset($dobFilter[0]) && is_array($dobFilter[0])) {
            $from = ($dobFilter[0][0] ?? null) ?: null;
            $to   = ($dobFilter[0][1] ?? null) ?: null;
            if ($from && ! $to) {
                $dobFilter[0][1] = $from;
                $requestedFilters['date_of_birth'] = $dobFilter;
            } elseif ($to && ! $from) {
                $dobFilter[0][0] = $to;
                $requestedFilters['date_of_birth'] = $dobFilter;
            }
        }

        // Update request with cleaned filters (avoid validation errors when filters becomes empty).
        $originalFilters = request()->input('filters');
        if (! empty($requestedFilters)) {
            request()->merge(['filters' => $requestedFilters]);
        } elseif ($originalFilters !== null) {
            request()->request->remove('filters');
            request()->query->remove('filters');
        }

        $this->addFilter('id', 'persons.id');
        $this->addFilter('person_name', DB::raw("CONCAT_WS(' ',
            NULLIF(persons.first_name, ''),
            NULLIF(persons.lastname_prefix, ''),
            NULLIF(persons.last_name, '')
        )"));
        $this->addFilter('organization', 'organizations.name');
        $this->addFilter('is_active', 'persons.is_active');
        $this->addFilter('has_duplicates', 'persons.has_duplicates');
        $this->addFilter('date_of_birth', 'persons.date_of_birth');

        return $queryBuilder;
    }

    /**
     * Add columns.
     */
    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'id',
            'label'      => trans('admin::app.contacts.persons.index.datagrid.id'),
            'type'       => 'integer',
            'filterable' => true,
            'sortable'   => true,
            'searchable' => true,
        ]);

        $this->addColumn([
            'index'      => 'person_name',
            'label'      => trans('admin::app.contacts.persons.index.datagrid.name'),
            'type'       => 'string',
            'sortable'   => true,
            'filterable' => true,
            'searchable' => true,
        ]);

        $this->addColumn([
            'index'              => 'trashed',
            'label'              => 'Verwijderd',
            'type'               => 'string',
            'searchable'         => false,
            'sortable'           => false,
            'filterable'         => true,
            'visibility'         => false, // filter-only
            'filterable_type'    => 'dropdown',
            'filterable_options' => [
                ['label' => 'Niet verwijderd', 'value' => 'without'],
                ['label' => 'Inclusief verwijderd', 'value' => 'with'],
                ['label' => 'Alleen verwijderd', 'value' => 'only'],
            ],
        ]);

        $this->addColumn([
            'index'      => 'emails',
            'label'      => trans('admin::app.contacts.persons.index.datagrid.emails'),
            'type'       => 'string',
            'sortable'   => true,
            'filterable' => true,
            'searchable' => true,
            'closure'    => fn ($row) => collect(json_decode($row->emails, true) ?? [])->pluck('value')->join(', '),
        ]);

        $this->addColumn([
            'index'      => 'phones',
            'label'      => trans('admin::app.contacts.persons.index.datagrid.contact-numbers'),
            'type'       => 'string',
            'sortable'   => true,
            'filterable' => true,
            'searchable' => true,
            'closure'    => fn ($row) => collect(json_decode($row->phones, true) ?? [])->pluck('value')->join(', '),
        ]);

        $this->addColumn([
            'index'              => 'organization',
            'label'              => trans('admin::app.contacts.persons.index.datagrid.organization-name'),
            'type'               => 'string',
            'searchable'         => true,
            'filterable'         => true,
            'sortable'           => true,
            'filterable_type'    => 'searchable_dropdown',
            'filterable_options' => [
                'repository' => OrganizationRepository::class,
                'column'     => [
                    'label' => 'name',
                    'value' => 'name',
                ],
            ],
        ]);

        $this->addColumn([
            'index'                    => 'date_of_birth',
            'label'                    => 'Geboortedatum',
            'type'                     => 'date',
            'sortable'                 => true,
            'filterable'               => true,
            'filterable_type'          => 'date_range',
            'date_range_quick_filters' => false,
            'searchable'               => false,
            'closure'         => function ($row) {
                if (! $row->date_of_birth) {
                    return '-';
                }

                $birthDate = Carbon::parse($row->date_of_birth);
                $age = $birthDate->age;

                return $birthDate->format('d-m-Y') . ' (' . $age . ' jaar)';
            },
        ]);

        $this->addColumn([
            'index'      => 'is_active',
            'label'      => 'Actief',
            'type'       => 'string',
            'sortable'   => true,
            'filterable' => true,
            'searchable' => false,
            'filterable_type'    => 'dropdown',
            'filterable_options' => [
                ['label' => 'Actief', 'value' => '1'],
                ['label' => 'Inactief', 'value' => '0'],
            ],
            'closure'    => fn ($row) => $row->is_active
                ? '<span class="icon-tick text-lg text-green-600" title="Actief"></span>'
                : '<span class="icon-cross text-lg text-red-600" title="Inactief"></span>',
        ]);

        $this->addColumn([
            'index'              => 'has_duplicates',
            'label'              => 'Duplicaten',
            'type'               => 'string',
            'sortable'           => true,
            'filterable'         => true,
            'searchable'         => false,
            'filterable_type'    => 'dropdown',
            'filterable_options' => [
                ['label' => 'Heeft duplicaten', 'value' => '1'],
                ['label' => 'Geen duplicaten', 'value' => '0'],
            ],
            // Reads the indexed flag instead of detecting per row: detection here cost a query per
            // row and wrote the flag as a side effect, so browsing the list changed the counts.
            // duplicates:refresh-cache --index is what maintains the column.
            'closure'            => function ($row) {
                if (! $row->has_duplicates) {
                    return '';
                }

                return '<a href="' . route('admin.contacts.persons.duplicates.index', $row->id) . '" class="text-orange-600 hover:text-activity-note-text" title="Mogelijk duplicaat">'
                     . '<span class="icon-warning text-lg"></span>'
                     . '</a>';
            },
        ]);
    }

    /**
     * Prepare actions.
     */
    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('contacts.persons.view')) {
            $this->addAction([
                'icon'   => 'icon-eye',
                'title'  => trans('admin::app.contacts.persons.index.datagrid.view'),
                'method' => 'GET',
                'url'    => function ($row) {
                    return route('admin.contacts.persons.view', $row->id);
                },
            ]);
        }

        if (bouncer()->hasPermission('contacts.persons.edit')) {
            $this->addAction([
                'icon'   => 'icon-edit',
                'title'  => trans('admin::app.contacts.persons.index.datagrid.edit'),
                'method' => 'GET',
                'url'    => function ($row) {
                    return route('admin.contacts.persons.edit', $row->id);
                },
            ]);
        }

        if (bouncer()->hasPermission('contacts.persons.delete')) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.contacts.persons.index.datagrid.delete'),
                'method' => 'DELETE',
                'url'    => function ($row) {
                    return route('admin.contacts.persons.delete', $row->id);
                },
            ]);
        }
    }

    /**
     * Hide delete for persons that still have a patient portal account.
     */
    protected function formatRecords($records): mixed
    {
        $records = parent::formatRecords($records);

        foreach ($records as $record) {
            if (empty($record->keycloak_user_id)) {
                continue;
            }

            $record->actions = array_values(array_filter(
                $record->actions,
                fn (array $action): bool => ($action['index'] ?? '') !== 'delete'
            ));
        }

        return $records;
    }

    /**
     * Prepare mass actions.
     */
    public function prepareMassActions(): void
    {
        if (bouncer()->hasPermission('contacts.persons.delete')) {
            $this->addMassAction([
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.contacts.persons.index.datagrid.delete'),
                'method' => 'POST',
                'url'    => route('admin.contacts.persons.mass_delete'),
            ]);
        }
    }
}
