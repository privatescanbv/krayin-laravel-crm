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
    protected $sortColumn = 'created_at';

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
                'tags.name as tags',
                'emails.person_id',
                'emails.lead_id',
                'emails.activity_id',
                DB::raw('COUNT(DISTINCT '.DB::getTablePrefix().'email_attachments.id) as attachments'),
                // Add entity information
                DB::raw('CASE
                    WHEN emails.person_id IS NOT NULL THEN "person"
                    WHEN emails.activity_id IS NOT NULL THEN "activity"
                    WHEN emails.lead_id IS NOT NULL THEN "lead"
                    ELSE "N/A"
                END as entity_type'),
            )
            ->leftJoin('email_attachments', 'emails.id', '=', 'email_attachments.email_id')
            ->leftJoin('email_tags', 'emails.id', '=', 'email_tags.email_id')
            ->leftJoin('tags', 'tags.id', '=', 'email_tags.tag_id')
            ->groupBy('emails.id')
            ->where('folders', 'like', '%"'.request('route').'"%');

        $this->addFilter('id', 'emails.id');
        $this->addFilter('name', 'emails.name');
        $this->addFilter('tags', 'tags.name');
        $this->addFilter('created_at', 'emails.created_at');
        // Allow filtering by computed entity_type alias
        $this->addFilter('entity_type', 'entity_type');

        return $queryBuilder;
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
                if ($email = app(EmailRepository::class)->find($row->id)) {
                    return $email->tags;
                }

                return '--';
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
            'filterable'         => true,
            'filterable_type'    => 'dropdown',
            'filterable_options' => [
                ['label' => 'Alles', 'value' => ''],
                ['label' => 'Lead', 'value' => 'lead'],
                ['label' => 'Persoon', 'value' => 'person'],
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
                        // Try to resolve lead name via model accessor; fallback to #ID
                        try {
                            $lead = Lead::find($row->lead_id);
                            $display = $lead ? ($lead->name ?? ('#'.$row->lead_id)) : ('#'.$row->lead_id);
                        } catch (Throwable $e) {
                            logger()->warning('Unable to locate lead entity id '.$row->lead_id . ', '. $e->getMessage());
                            $display = '#'.$row->lead_id;
                        }
                        $label = e($display);
                        break;
                    case 'person':
                        $route = route('admin.contacts.persons.view', $row->person_id);
                        // Try to resolve person name via model accessor; fallback to #ID
                        try {
                            $person = Person::find($row->person_id);
                            $display = $person ? $person->name : ('#'.$row->person_id);
                        } catch (Throwable $e) {
                            logger()->warning('Unable to locate person entity id '.$row->person_id . ', '. $e->getMessage());
                            $display = '#'.$row->person_id;
                        }
                        $label = e($display);
                        break;
                    case 'activity':
                        $route = route('admin.activities.view', $row->activity_id);
                        $activity = Activity::find($row->activity_id);
                        $display = $activity ? $activity->title : ('#'.$row->activity_id);
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
