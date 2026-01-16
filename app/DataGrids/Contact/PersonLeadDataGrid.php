<?php

namespace App\DataGrids\Contact;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class PersonLeadDataGrid extends DataGrid
{
    public function prepareQueryBuilder(): Builder
    {
        $personId = (int) (request('person_id') ?: request()->route('id'));

        $queryBuilder = DB::table('leads')
            ->addSelect(
                'leads.id',
                'leads.created_at',
                DB::raw("TRIM(CONCAT_WS(' ', leads.first_name, leads.lastname_prefix, leads.last_name)) as lead_name"),
                'salesleads.id as sales_lead_id',
                'salesleads.name as sales_lead_name',
                DB::raw('COALESCE(sales_stages.name, lead_stages.name) as stage'),
                DB::raw('COALESCE(sales_stages.is_won, lead_stages.is_won, 0) as stage_is_won'),
                DB::raw('COALESCE(sales_stages.is_lost, lead_stages.is_lost, 0) as stage_is_lost')
            )
            ->join('lead_persons', 'leads.id', '=', 'lead_persons.lead_id')
            ->leftJoin('lead_pipeline_stages as lead_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_stages.id')
            ->leftJoin('salesleads', 'salesleads.lead_id', '=', 'leads.id')
            ->leftJoin('lead_pipeline_stages as sales_stages', 'salesleads.pipeline_stage_id', '=', 'sales_stages.id')
            ->where('lead_persons.person_id', $personId)
            ->groupBy('leads.id', 'salesleads.id', 'lead_stages.id', 'sales_stages.id');

        /**
         * Optional bucket filter:
         * - open: stage not won and not lost
         * - completed: stage won or lost
         */
        $statusBucket = request('status_bucket');
        if ($statusBucket === 'open') {
            $queryBuilder
                ->whereRaw('COALESCE(sales_stages.is_won, lead_stages.is_won, 0) = 0')
                ->whereRaw('COALESCE(sales_stages.is_lost, lead_stages.is_lost, 0) = 0');
        } elseif ($statusBucket === 'completed') {
            $queryBuilder->whereRaw(
                '(COALESCE(sales_stages.is_won, lead_stages.is_won, 0) = 1 OR COALESCE(sales_stages.is_lost, lead_stages.is_lost, 0) = 1)'
            );
        }

        $queryBuilder->orderByDesc('leads.created_at');

        $this->addFilter('id', 'leads.id');
        $this->addFilter('lead_name', DB::raw("TRIM(CONCAT_WS(' ', leads.first_name, leads.lastname_prefix, leads.last_name))"));
        $this->addFilter('sales_lead_name', 'salesleads.name');
        $this->addFilter('stage', DB::raw('COALESCE(sales_stages.name, lead_stages.name)'));
        $this->addFilter('created_at', 'leads.created_at');

        if (config('app.debug') && request()->boolean('debug_sql')) {
            logger()->info('PersonLeadDataGrid SQL: '.$queryBuilder->toSql(), [
                'bindings' => $queryBuilder->getBindings(),
            ]);
        }

        return $queryBuilder;
    }

    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'lead_name',
            'label'      => 'Lead (link)',
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true,
            'closure'    => function ($row) {
                $label = trim((string) ($row->lead_name ?? ''));
                $label = $label !== '' ? e($label) : '#'.(int) $row->id;

                return '<a class="text-brandColor hover:underline" href="'.
                    e(route('admin.leads.view', (int) $row->id)).
                    '">'.$label.'</a>';
            },
        ]);

        $this->addColumn([
            'index'      => 'sales_lead_name',
            'label'      => 'SalesLead (link)',
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true,
            'closure'    => function ($row) {
                if (empty($row->sales_lead_id)) {
                    return '--';
                }

                $label = trim((string) ($row->sales_lead_name ?? ''));
                $label = $label !== '' ? e($label) : '#'.(int) $row->sales_lead_id;

                return '<a class="text-brandColor hover:underline" href="'.
                    e(route('admin.sales-leads.view', (int) $row->sales_lead_id)).
                    '">'.$label.'</a>';
            },
        ]);

        $this->addColumn([
            'index'      => 'stage',
            'label'      => 'Stage',
            'type'       => 'string',
            'searchable' => false,
            'sortable'   => true,
            'filterable' => true,
            'closure'    => fn ($row) => $row->stage ?: '--',
        ]);

        $this->addColumn([
            'index'           => 'created_at',
            'label'           => 'Aangemaakt',
            'type'            => 'date',
            'searchable'      => false,
            'sortable'        => true,
            'filterable'      => true,
            'filterable_type' => 'date_range',
        ]);
    }

    public function prepareActions(): void {}
}
