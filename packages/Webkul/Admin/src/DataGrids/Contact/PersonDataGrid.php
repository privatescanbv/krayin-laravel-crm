<?php

namespace Webkul\Admin\DataGrids\Contact;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\DataGrid\DataGrid;
use App\Services\PersonDuplicateCacheService;

class PersonDataGrid extends DataGrid
{
    /**
     * Create a new class instance.
     *
     * @return void
     */
    public function __construct(
        protected OrganizationRepository $organizationRepository,
        protected PersonDuplicateCacheService $duplicateCacheService
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
                'organizations.name as organization',
                'organizations.id as organization_id'
            )
            ->leftJoin('organizations', 'persons.organization_id', '=', 'organizations.id');

        if ($userIds = bouncer()->getAuthorizedUserIds()) {
            $queryBuilder->whereIn('persons.user_id', $userIds);
        }

        $this->addFilter('id', 'persons.id');
        $this->addFilter('person_name', DB::raw("CONCAT_WS(' ',
            NULLIF(persons.first_name, ''),
            NULLIF(persons.lastname_prefix, ''),
            NULLIF(persons.last_name, '')
        )"));
        $this->addFilter('organization', 'organizations.name');

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
            'index'      => 'date_of_birth',
            'label'      => 'Leeftijd',
            'type'       => 'string',
            'sortable'   => true,
            'filterable' => false,
            'searchable' => false,
            'closure'    => function ($row) {
                if (!$row->date_of_birth) {
                    return '-';
                }

                $birthDate = Carbon::parse($row->date_of_birth);
                $age = $birthDate->age;

                return $age . ' jaar';
            },
        ]);

        $this->addColumn([
            'index'      => 'has_duplicates',
            'label'      => 'Duplicaten',
            'type'       => 'string',
            'sortable'   => false,
            'filterable' => false,
            'searchable' => false,
            'closure'    => function ($row) {
                $duplicateIds = $this->duplicateCacheService->getCachedDuplicates($row->id);
                $duplicateCount = $duplicateIds->count();
                if ($duplicateCount > 0) {
                    return '<a href="' . route('admin.contacts.persons.duplicates.index', $row->id) . '" class="text-orange-600 hover:text-activity-note-text" title="' . $duplicateCount . ' duplicaten gevonden">'
                         . '<span class="icon-warning text-lg"></span>'
                         . '</a>';
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
