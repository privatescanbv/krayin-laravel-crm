<?php

namespace App\DataGrids;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class SalesLeadDataGrid extends DataGrid
{
    protected $primaryColumn = 'salesleads.id';

    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('salesleads')
            ->leftJoin('lead_pipeline_stages', 'salesleads.pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->leftJoin('leads', 'salesleads.lead_id', '=', 'leads.id')
            ->leftJoin('users', 'salesleads.user_id', '=', 'users.id')
            ->addSelect(
                'salesleads.id',
                'salesleads.name',
                'salesleads.description',
                'salesleads.lead_id',
                'salesleads.created_at',
                'lead_pipeline_stages.name as stage_name',
                DB::raw(
                    DB::getDriverName() === 'sqlite'
                        ? "TRIM(COALESCE(leads.first_name, '') || ' ' || COALESCE(leads.lastname_prefix, '') || ' ' || COALESCE(leads.last_name, '')) as lead_title"
                        : "CONCAT_WS(' ', leads.first_name, leads.lastname_prefix, leads.last_name) as lead_title"
                ),
                DB::raw(
                    DB::getDriverName() === 'sqlite'
                        ? "TRIM(COALESCE(users.first_name, '') || ' ' || COALESCE(users.last_name, '')) as user_name"
                        : "CONCAT_WS(' ', users.first_name, users.last_name) as user_name"
                ),
            );

        // Filter by pipeline if pipeline_id is provided
        if (request('pipeline_id')) {
            $pipeline = app('Webkul\Lead\Repositories\PipelineRepository')->find(request('pipeline_id'));
            if ($pipeline) {
                $stageIds = $pipeline->stages()->pluck('id');
                $queryBuilder->whereIn('salesleads.pipeline_stage_id', $stageIds);
            }
        }

        // Filter by lead_id if provided
        if (request('lead_id')) {
            $queryBuilder->where('salesleads.lead_id', request('lead_id'));
        }

        // Filter by person_id if provided (via saleslead_persons pivot)
        if (request('person_id')) {
            $queryBuilder->whereIn('salesleads.id', function ($sub) {
                $sub->select('saleslead_id')
                    ->from('saleslead_persons')
                    ->where('person_id', request('person_id'));
            });
        }

        // Filter by status bucket (active vs closed)
        if (request('status_bucket')) {
            if (request('status_bucket') === 'active') {
                $queryBuilder->where('lead_pipeline_stages.is_won', 0)
                    ->where('lead_pipeline_stages.is_lost', 0);
            } elseif (request('status_bucket') === 'closed') {
                $queryBuilder->where(function ($q) {
                    $q->where('lead_pipeline_stages.is_won', 1)
                        ->orWhere('lead_pipeline_stages.is_lost', 1);
                });
            }
        }

        $this->addFilter('id', 'salesleads.id');
        $this->addFilter('name', 'salesleads.name');
        $this->addFilter('description', 'salesleads.description');
        $this->addFilter('stage_name', 'lead_pipeline_stages.name');
        $this->addFilter('lead_title', 'leads.title');
        $this->addFilter('user_name', 'users.name');
        $this->addFilter('created_at', 'salesleads.created_at');

        return $queryBuilder;
    }

    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'id',
            'label'      => 'ID',
            'type'       => 'integer',
            'searchable' => true,
            'sortable'   => true,
            'width'      => '50px',
        ]);

        $this->addColumn([
            'index'      => 'name',
            'label'      => 'Name',
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'width'      => '200px',
        ]);

        $this->addColumn([
            'index'      => 'description',
            'label'      => 'Description',
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'width'      => '300px',
        ]);

        $this->addColumn([
            'index'      => 'stage_name',
            'label'      => 'Pipeline Stage',
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'width'      => '150px',
        ]);

        $this->addColumn([
            'index'      => 'lead_title',
            'label'      => 'Lead',
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'width'      => '200px',
        ]);

        $this->addColumn([
            'index'      => 'user_name',
            'label'      => 'User',
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'width'      => '150px',
        ]);

        $this->addColumn([
            'index'      => 'created_at',
            'label'      => 'Created At',
            'type'       => 'date',
            'searchable' => true,
            'sortable'   => true,
            'width'      => '120px',
        ]);
    }

    public function prepareActions(): void
    {
        $this->addAction([
            'title'  => 'View',
            'method' => 'GET',
            'url'    => fn ($row) => route('admin.sales-leads.view', $row->id),
            'icon'   => 'icon-eye',
        ]);

        $this->addAction([
            'title'  => 'Edit',
            'method' => 'GET',
            'url'    => fn ($row) => route('admin.sales-leads.edit', $row->id),
            'icon'   => 'icon-edit',
        ]);

        $this->addAction([
            'title'  => 'Delete',
            'method' => 'DELETE',
            'url'    => fn ($row) => route('admin.sales-leads.delete', $row->id),
            'icon'   => 'icon-delete',
        ]);
    }
}
