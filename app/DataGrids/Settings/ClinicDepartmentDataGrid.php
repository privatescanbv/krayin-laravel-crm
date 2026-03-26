<?php

namespace App\DataGrids\Settings;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class ClinicDepartmentDataGrid extends DataGrid
{
    protected $sortColumn = 'clinic_departments.name';

    protected $sortOrder = 'asc';

    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('clinic_departments')
            ->join('clinics', 'clinics.id', '=', 'clinic_departments.clinic_id')
            ->addSelect(
                'clinic_departments.id',
                'clinic_departments.name',
                'clinic_departments.email',
                'clinic_departments.description',
                'clinic_departments.clinic_id',
                DB::raw('clinics.name as clinic_name'),
            );

        if ($clinicId = request()->input('clinic_id')) {
            $queryBuilder->where('clinic_departments.clinic_id', $clinicId);
        }

        $this->addFilter('id', 'clinic_departments.id');
        $this->addFilter('name', 'clinic_departments.name');
        $this->addFilter('email', 'clinic_departments.email');
        $this->addFilter('clinic_name', 'clinics.name');

        return $queryBuilder;
    }

    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'id',
            'label'      => 'ID',
            'type'       => 'string',
            'searchable' => false,
            'filterable' => true,
            'sortable'   => false,
            'visibility' => false,
        ]);

        $this->addColumn([
            'index'      => 'clinic_name',
            'label'      => 'Kliniek',
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'name',
            'label'      => 'Naam',
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'email',
            'label'      => 'E-mail',
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'description',
            'label'      => 'Omschrijving',
            'type'       => 'string',
            'searchable' => false,
            'filterable' => false,
            'sortable'   => false,
        ]);
    }

    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('settings.clinics.edit')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => 'Bewerken',
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.clinic_departments.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('settings.clinics.delete')) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => 'Verwijderen',
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.clinic_departments.delete', $row->id),
            ]);
        }
    }
}
