<?php

namespace Webkul\Admin\DataGrids\Mail;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;
use Webkul\Email\Models\Email;
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
                'emails.reply_to',
                'emails.is_read',
                'emails.created_at',
                'emails.parent_id',
                'emails.person_id',
                'emails.lead_id',
                'emails.sales_lead_id',
                'leads.first_name as lead_first_name',
                'leads.lastname_prefix as lead_lastname_prefix',
                'leads.last_name as lead_last_name',
                'leads.married_name_prefix as lead_married_name_prefix',
                'leads.married_name as lead_married_name',
                'persons.first_name as person_first_name',
                'persons.lastname_prefix as person_lastname_prefix',
                'persons.last_name as person_last_name',
                'persons.married_name_prefix as person_married_name_prefix',
                'persons.married_name as person_married_name',
                DB::raw('salesleads.name as sales_name'),
                'emails.order_id',
                DB::raw('orders.title as order_title'),
                DB::raw('GROUP_CONCAT(DISTINCT tags.name) as tags'),
                DB::raw('COUNT(DISTINCT '.DB::getTablePrefix().'email_attachments.id) as attachments'),
                DB::raw(Email::entityTypeCaseSql('emails').' as entity_type'),
            )
            ->leftJoin('email_attachments', 'emails.id', '=', 'email_attachments.email_id')
            ->leftJoin('email_tags', 'emails.id', '=', 'email_tags.email_id')
            ->leftJoin('tags', 'tags.id', '=', 'email_tags.tag_id')
            ->leftJoin('leads', 'emails.lead_id', '=', 'leads.id')
            ->leftJoin('salesleads', 'emails.sales_lead_id', '=', 'salesleads.id')
            ->leftJoin('orders', 'emails.order_id', '=', 'orders.id')
            ->leftJoin('persons', 'emails.person_id', '=', 'persons.id')
            ->leftJoin('folders', 'emails.folder_id', '=', 'folders.id')
            ->groupBy(
                'emails.id',
                'leads.first_name', 'leads.lastname_prefix', 'leads.last_name', 'leads.married_name_prefix', 'leads.married_name',
                'persons.first_name', 'persons.lastname_prefix', 'persons.last_name', 'persons.married_name_prefix', 'persons.married_name',
                'orders.title',
                'emails.is_read',
                'emails.created_at'
            )
            ->where('folders.name', request('route'));

        // Custom composite filter: show only unread and/or unlinked emails.
        // Applied manually because the datagrid engine can't handle this OR-condition.
        // Removed from request afterwards so the datagrid core skips it.
        $this->applyUnreadUnlinkedFilter($queryBuilder);

        $this->addFilter('id', 'emails.id');
        $this->addFilter('name', 'emails.name');
        $this->addFilter('tags', 'tags.name');
        $this->addFilter('created_at', 'emails.created_at');

        return $queryBuilder;
    }

    /**
     * Apply the "ongelezen / ongekoppeld" composite filter and remove it from request.
     */
    protected function applyUnreadUnlinkedFilter(Builder $queryBuilder): void
    {
        $filters = request()->input('filters', []);

        if (empty($filters['ongelezen_ongekoppeld'])) {
            return;
        }

        $values = array_filter((array) $filters['ongelezen_ongekoppeld']);

        if (in_array('1', $values)) {
            $queryBuilder->where(function ($query) {
                $query->where('emails.is_read', 0)
                    ->orWhere(function ($q) {
                        Email::applyUnlinkedFromAllEntitiesConstraints($q, 'emails');
                    });
            });
        }

        // Remove so datagrid core won't try to apply it as an equality filter
        unset($filters['ongelezen_ongekoppeld']);

        if (! empty($filters)) {
            request()->merge(['filters' => $filters]);
        } else {
            request()->request->remove('filters');
            request()->query->remove('filters');
        }
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
        // Filter-only column: filters to unread + unlinked emails (not shown as table column).
        $this->addColumn([
            'index'              => 'ongelezen_ongekoppeld',
            'label'              => 'Ongelezen / ongekoppeld',
            'type'               => 'string',
            'searchable'         => false,
            'sortable'           => false,
            'filterable'         => true,
            'visibility'         => false,
            'filterable_type'    => 'dropdown',
            'filterable_options' => [
                ['label' => 'Actief', 'value' => '1'],
            ],
        ]);

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
            'index'      => 'from',
            'label'      => trans('admin::app.mail.index.datagrid.from'),
            'type'       => 'string',
            'sortable'   => true,
            'searchable' => true,
            'filterable' => true,
            'closure'    => function ($row) {
                $from = $row->from;

                // Als het een JSON-string is → decode
                if (is_string($from)) {
                    $from = json_decode($from, true);
                }

                // Verwachte standaardstructuur
                if (is_array($from)) {
                    if (! empty($from['name'])) {
                        return $from['name'].' - '.($from['email'] ?? '');
                    }

                    return $from['email'] ?? '';
                }

                return '';
            },
        ]);

        //        $this->addColumn([
        //            'index'      => 'reply_to',
        //            'label'      => trans('admin::app.mail.index.datagrid.to'),
        //            'type'       => 'string',
        //            'sortable'   => true,
        //            'searchable' => true,
        //            'filterable' => true,
        //            'closure'    => function ($row) {
        //                return (is_array($row->reply_to)) ? implode(', ', $row->reply_to) : $row->reply_to;
        //            },
        //        ]);

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
                ['label' => 'Order', 'value' => 'order'],
                ['label' => 'Sales', 'value' => 'sales'],
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
                        if (! empty($row->lead_first_name)) {
                            $parts[] = trim($row->lead_first_name);
                        }
                        if (! empty($row->lead_lastname_prefix)) {
                            $parts[] = trim($row->lead_lastname_prefix);
                        }
                        if (! empty($row->lead_last_name)) {
                            $parts[] = trim($row->lead_last_name);
                        }
                        if (! empty($row->lead_married_name)) {
                            $marriedParts = [];
                            if (! empty($row->lead_married_name_prefix)) {
                                $marriedParts[] = trim($row->lead_married_name_prefix);
                            }
                            $marriedParts[] = trim($row->lead_married_name);
                            $parts[] = '/ '.implode(' ', array_filter($marriedParts));
                        }
                        $display = ! empty($parts) ? implode(' ', array_filter($parts)) : ('#'.$row->lead_id);
                        $label = e($display);
                        break;
                    case 'person':
                        $route = route('admin.contacts.persons.view', $row->person_id);
                        // Construct name similar to Person model's name accessor
                        $parts = [];
                        if (! empty($row->person_first_name)) {
                            $parts[] = trim($row->person_first_name);
                        }
                        if (! empty($row->person_lastname_prefix)) {
                            $parts[] = trim($row->person_lastname_prefix);
                        }
                        if (! empty($row->person_last_name)) {
                            $parts[] = trim($row->person_last_name);
                        }
                        if (! empty($row->person_married_name)) {
                            $marriedParts = [];
                            if (! empty($row->person_married_name_prefix)) {
                                $marriedParts[] = trim($row->person_married_name_prefix);
                            }
                            $marriedParts[] = trim($row->person_married_name);
                            $parts[] = '/ '.implode(' ', array_filter($marriedParts));
                        }
                        $display = ! empty($parts) ? implode(' ', array_filter($parts)) : ('#'.$row->person_id);
                        $label = e($display);
                        break;
                    case 'order':
                        $route = route('admin.orders.view', $row->order_id);
                        $display = $row->order_title ?: ('#'.$row->order_id);
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
