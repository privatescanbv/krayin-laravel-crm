<?php

namespace App\DataGrids;

use App\Models\SalesLead;
use Illuminate\Support\Facades\Log;
use Webkul\DataGrid\DataGrid;

class SalesLeadDataGrid extends DataGrid
{
    public function prepareQueryBuilder()
    {
        $queryBuilder = SalesLead::query()
            ->with(['stage', 'lead', 'user']);

        // Filter by pipeline if pipeline_id is provided
        if (request('pipeline_id')) {
            $pipeline = app('Webkul\Lead\Repositories\PipelineRepository')->find(request('pipeline_id'));
            if ($pipeline) {
                $stageIds = $pipeline->stages()->pluck('id');
                $queryBuilder->whereIn('pipeline_stage_id', $stageIds);
            }
        }

        // Debug: Log de query
        Log::info('SalesLeadDataGrid Query: '.$queryBuilder->toSql());
        Log::info('SalesLeadDataGrid Bindings: '.json_encode($queryBuilder->getBindings()));

        $this->addFilter('id', 'id');
        $this->addFilter('name', 'name');
        $this->addFilter('description', 'description');
        $this->addFilter('pipeline_stage_id', 'pipeline_stage_id');
        $this->addFilter('lead_id', 'lead_id');
        $this->addFilter('user_id', 'user_id');
        $this->addFilter('workflow_type', 'workflow_type');

        return $queryBuilder;
    }

    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'id',
            'label'      => 'ID',
            'type'       => 'number',
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
            'index'      => 'pipeline_stage.name',
            'label'      => 'Pipeline Stage',
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'width'      => '150px',
        ]);

        $this->addColumn([
            'index'      => 'lead.title',
            'label'      => 'Lead',
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'width'      => '200px',
        ]);

        $this->addColumn([
            'index'      => 'user.name',
            'label'      => 'User',
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'width'      => '150px',
        ]);

        $this->addColumn([
            'index'      => 'created_at',
            'label'      => 'Created At',
            'type'       => 'date_range',
            'searchable' => true,
            'sortable'   => true,
            'width'      => '120px',
        ]);
    }

    public function prepareActions(): void
    {
        $this->addAction([
            'title'  => 'Edit',
            'method' => 'GET',
            'route'  => 'admin.sales-leads.edit',
            'icon'   => 'icon-edit',
        ]);

        $this->addAction([
            'title'  => 'Delete',
            'method' => 'DELETE',
            'route'  => 'admin.sales-leads.delete',
            'icon'   => 'icon-delete',
        ]);
    }
}
