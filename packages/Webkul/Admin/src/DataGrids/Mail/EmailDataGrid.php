<?php

namespace Webkul\Admin\DataGrids\Mail;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\DataGrid\DataGrid;
use Webkul\Email\Repositories\EmailRepository;
use Webkul\Lead\Models\Lead;
use Webkul\Tag\Repositories\TagRepository;

class EmailDataGrid extends DataGrid
{
    /**
     * Default sort column of datagrid.
     *
     * @var ?string
     */
    protected $sortColumn = null;

    /**
     * Default sort order of datagrid.
     *
     * @var string
     */
    protected $sortOrder = 'desc';

    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('emails')
            ->select(
                'emails.id',
                'emails.name',
                'emails.from',
                'emails.subject',
                'emails.reply',
                'emails.is_read',
                'emails.created_at',
                'emails.parent_id',
                'emails.person_id',
                'emails.lead_id',
                'emails.sales_lead_id',
                'emails.activity_id',
                // Preload related entity names to avoid N+1 queries
                // For leads: select individual name fields to construct name in closure
                'leads.first_name as lead_first_name',
                'leads.lastname_prefix as lead_lastname_prefix',
                'leads.last_name as lead_last_name',
                'leads.married_name_prefix as lead_married_name_prefix',
                'leads.married_name as lead_married_name',
                // For persons: select individual name fields to construct name in closure
                'persons.first_name as person_first_name',
                'persons.lastname_prefix as person_lastname_prefix',
                'persons.last_name as person_last_name',
                'persons.married_name_prefix as person_married_name_prefix',
                'persons.married_name as person_married_name',
                DB::raw('salesleads.name as sales_name'),
                'activities.title as activity_title',
                // Aggregate tags and attachments
                DB::raw('GROUP_CONCAT(DISTINCT tags.name) as tags'),
                DB::raw('COUNT(DISTINCT '.DB::getTablePrefix().'email_attachments.id) as attachments'),
                // Add entity information
                DB::raw('CASE
                    WHEN emails.person_id IS NOT NULL THEN "person"
                    WHEN emails.activity_id IS NOT NULL THEN "activity"
                    WHEN emails.lead_id IS NOT NULL THEN "lead"
                    WHEN emails.sales_lead_id IS NOT NULL THEN "sales"
                    ELSE "N/A"
                END as entity_type'),
            )
            ->leftJoin('email_attachments', 'emails.id', '=', 'email_attachments.email_id')
            ->leftJoin('email_tags', 'emails.id', '=', 'email_tags.email_id')
            ->leftJoin('tags', 'tags.id', '=', 'email_tags.tag_id')
            // Preload related entities to avoid N+1 queries
            ->leftJoin('leads', 'emails.lead_id', '=', 'leads.id')
            ->leftJoin('salesleads', 'emails.sales_lead_id', '=', 'salesleads.id')
            ->leftJoin('persons', 'emails.person_id', '=', 'persons.id')
            ->leftJoin('activities', 'emails.activity_id', '=', 'activities.id')
            ->leftJoin('folders', 'emails.folder_id', '=', 'folders.id')
            ->groupBy(
                'emails.id',
                'leads.first_name', 'leads.lastname_prefix', 'leads.last_name', 'leads.married_name_prefix', 'leads.married_name',
                'persons.first_name', 'persons.lastname_prefix', 'persons.last_name', 'persons.married_name_prefix', 'persons.married_name',
                'activities.title',
                'emails.is_read',
                'emails.created_at'
            )
            // Filter by folder name - handle both new folder_id and old folders JSON
            ->where(function($query) {
                $query->where('folders.name', request('route'));
            });

        $this->addFilter('id', 'emails.id');
        $this->addFilter('name', 'emails.name');
        $this->addFilter('tags', 'tags.name');
        $this->addFilter('created_at', 'emails.created_at');

        return $queryBuilder;
    }

    /**
     * Override the default sorting to maintain our custom unread-first sorting.
     */
    protected function processRequestedSorting($requestedSort)
    {
        // Only apply custom sorting if no specific sort is requested
        if (empty($requestedSort) || empty($requestedSort['column'])) {
            // Apply our custom sorting: unread emails first, then by created_at desc
            $this->queryBuilder->reorder()
                ->orderByRaw('CASE WHEN emails.is_read = 0 OR emails.is_read IS NULL THEN 0 ELSE 1 END, emails.created_at DESC');
        } else {
            // If user requests specific sorting, apply it normally
            return parent::processRequestedSorting($requestedSort);
        }

        return $this->queryBuilder;
    }

    /**
     * Prepare Columns.
     */
    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'attachments',
            'label'      => trans('admin::app.mail.index.datagrid.attachments'),
            'type'       => 'string',
            'searchable' => false,
            'filterable' => false,
            'sortable'   => false,
            'closure'    => fn ($row) => $row->attachments ? '<i class="icon-attachment text-2xl"></i>' : '',
        ]);

        $this->addColumn([
            'index'      => 'name',
            'label'      => trans('admin::app.mail.index.datagrid.from'),
            'type'       => 'string',
            'sortable'   => true,
            'searchable' => true,
            'filterable' => true,
            'closure'    => function ($row) {
                return $row->name
                    ? trim($row->name, '"')
                    : trim($row->from, '"');
            },
        ]);

        $this->addColumn([
            'index'      => 'subject',
            'label'      => trans('admin::app.mail.index.datagrid.subject'),
            'type'       => 'string',
            'sortable'   => true,
            'searchable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index'      => 'reply',
            'label'      => trans('admin::app.mail.index.datagrid.content'),
            'type'       => 'string',
            'sortable'   => true,
            'searchable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index'              => 'tags',
            'label'              => trans('admin::app.mail.index.datagrid.tags'),
            'type'               => 'string',
            'searchable'         => false,
            'sortable'           => true,
            'filterable'         => true,
            'filterable_type'    => 'searchable_dropdown',
            'closure'            => function ($row) {
                return $row->tags ?: '--';
            },
            'filterable_options' => [
                'repository' => TagRepository::class,
                'column'     => [
                    'label' => 'name',
                    'value' => 'name',
                ],
            ],
        ]);

        // Place "Gerelateerd aan" (entity_type) before "type"
        $this->addColumn([
            'index'              => 'entity_type',
            'label'              => 'Gerelateerd aan',
            'type'               => 'string',
            'searchable'         => false,
            'sortable'           => true,
            'filterable'         => false,
            'filterable_type'    => 'dropdown',
            'filterable_options' => [
                ['label' => 'Alles', 'value' => ''],
                ['label' => 'Lead', 'value' => 'lead'],
                ['label' => 'Persoon', 'value' => 'person'],
                ['label' => 'Sales', 'value' => 'sales'],
                ['label' => 'Activiteit', 'value' => 'activity'],
                ['label' => 'N/A', 'value' => 'N/A'],
            ],
            'closure'    => function ($row) {
                if ($row->entity_type === 'N/A') {
                    return "<span class='text-gray-800 dark:text-gray-300'>N/A</span>";
                }

                switch ($row->entity_type) {
                    case 'lead':
                        $route = route('admin.leads.view', $row->lead_id);
                        // Construct name similar to Lead model's name accessor
                        $parts = [];
                        if (!empty($row->lead_first_name)) {
                            $parts[] = trim($row->lead_first_name);
                        }
                        if (!empty($row->lead_lastname_prefix)) {
                            $parts[] = trim($row->lead_lastname_prefix);
                        }
                        if (!empty($row->lead_last_name)) {
                            $parts[] = trim($row->lead_last_name);
                        }
                        if (!empty($row->lead_married_name)) {
                            $marriedParts = [];
                            if (!empty($row->lead_married_name_prefix)) {
                                $marriedParts[] = trim($row->lead_married_name_prefix);
                            }
                            $marriedParts[] = trim($row->lead_married_name);
                            $parts[] = '/ '.implode(' ', array_filter($marriedParts));
                        }
                        $display = !empty($parts) ? implode(' ', array_filter($parts)) : ('#'.$row->lead_id);
                        $label = e($display);
                        break;
                    case 'person':
                        $route = route('admin.contacts.persons.view', $row->person_id);
                        // Construct name similar to Person model's name accessor
                        $parts = [];
                        if (!empty($row->person_first_name)) {
                            $parts[] = trim($row->person_first_name);
                        }
                        if (!empty($row->person_lastname_prefix)) {
                            $parts[] = trim($row->person_lastname_prefix);
                        }
                        if (!empty($row->person_last_name)) {
                            $parts[] = trim($row->person_last_name);
                        }
                        if (!empty($row->person_married_name)) {
                            $marriedParts = [];
                            if (!empty($row->person_married_name_prefix)) {
                                $marriedParts[] = trim($row->person_married_name_prefix);
                            }
                            $marriedParts[] = trim($row->person_married_name);
                            $parts[] = '/ '.implode(' ', array_filter($marriedParts));
                        }
                        $display = !empty($parts) ? implode(' ', array_filter($parts)) : ('#'.$row->person_id);
                        $label = e($display);
                        break;
                    case 'activity':
                        $route = route('admin.activities.view', $row->activity_id);
                        $display = $row->activity_title ?: ('#'.$row->activity_id);
                        $label = e($display);
                        break;
                    case 'sales':
                        $route = route('admin.sales-leads.view', $row->sales_lead_id);
                        $display = $row->sales_name ?: ('#'.$row->sales_lead_id);
                        $label = e($display);
                        break;
                    default:
                        return "<span class='text-gray-800 dark:text-gray-300'>Onbekend</span>";
                }

                return "<a class='text-brandColor hover:underline' target='_blank' href='".$route."'>".$label.'</a>';
            },
        ]);

        $this->addColumn([
            'index'           => 'created_at',
            'label'           => trans('admin::app.mail.index.datagrid.date'),
            'type'            => 'date',
            'searchable'      => true,
            'filterable'      => true,
            'filterable_type' => 'date_range',
            'sortable'        => true,
            'closure'         => function ($row) {
                return Carbon::parse($row->created_at)->isToday()
                    ? Carbon::parse($row->created_at)->format('h:i A')
                    : Carbon::parse($row->created_at)->format('M d');
            },
        ]);
    }

    /**
     * Prepare actions.
     */
    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('mail.view')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => request('route') == 'draft'
                    ? 'icon-edit'
                    : 'icon-eye',
                'title'  => request('route') == 'draft'
                    ? trans('admin::app.mail.index.datagrid.edit')
                    : trans('admin::app.mail.index.datagrid.view'),
                'method' => 'GET',
                'params' => [
                    'type' => request('route') == 'trash'
                        ? 'delete'
                        : 'trash',
                ],
                'url'    => fn ($row) => route('admin.mail.view', [request('route'), $row->id]),
            ]);
        }

        if (bouncer()->hasPermission('mail.delete')) {
            $this->addAction([
                'index'        => 'delete',
                'icon'         => 'icon-delete',
                'title'        => trans('admin::app.mail.index.datagrid.delete'),
                'method'       => 'DELETE',
                'params'       => [
                    'type' => request('route') == 'trash'
                        ? 'delete'
                        : 'trash',
                ],
                'url'    => fn ($row) => route('admin.mail.delete', $row->id),
            ]);
        }
    }

    /**
     * Prepare mass actions.
     */
    public function prepareMassActions(): void
    {
        if (request('route') == 'trash') {
            $this->addMassAction([
                'title'   => trans('admin::app.mail.index.datagrid.move-to-inbox'),
                'method'  => 'POST',
                'url'     => route('admin.mail.mass_update', ['folders' => ['inbox']]),
                'options' => [
                    [
                        'value' => 'trash',
                        'label' => trans('admin::app.mail.index.datagrid.move-to-inbox'),
                    ],
                ],
            ]);
        }

        $this->addMassAction([
            'icon'   => 'icon-delete',
            'title'  => request('route') == 'trash'
                    ? trans('admin::app.mail.index.datagrid.delete')
                    : trans('admin::app.mail.index.datagrid.move-to-trash'),
            'method' => 'POST',
            'url'    => route('admin.mail.mass_delete', [
                'type' => request('route') == 'trash'
                    ? 'delete'
                    : 'trash',
            ]),
        ]);
    }
}
