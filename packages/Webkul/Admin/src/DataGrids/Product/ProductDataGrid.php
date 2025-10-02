<?php

namespace Webkul\Admin\DataGrids\Product;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use App\Enums\Currency;
use Webkul\DataGrid\DataGrid;
use Webkul\Tag\Repositories\TagRepository;

class ProductDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('products')
            ->leftJoin('product_tags', 'products.id', '=', 'product_tags.product_id')
            ->leftJoin('tags', 'tags.id', '=', 'product_tags.tag_id')
            ->leftJoin('product_groups', 'products.product_group_id', '=', 'product_groups.id')
            ->select(
                DB::raw('products.id as id'),
                'products.name',
                'products.currency',
                'products.price',
                'products.active',
                DB::raw('MIN(tags.name) as tag_name'),
                'product_groups.name as group_name'
            )
            ->groupBy('products.id');

        $this->addFilter('id', 'products.id');
        $this->addFilter('tag_name', 'tags.name');
        $this->addFilter('group_name', 'product_groups.name');

        // 🚨 reset alle automatisch toegevoegde order by’s en zet jouw eigen fallback
        return $queryBuilder
            ->reorder()                       // wist ALLE order by’s, ook die van datagrid
            ->orderBy('products.id', 'desc'); // voeg expliciet jouw order toe
    }


    /**
     * Add columns.
     */
    public function prepareColumns(): void
    {
        // SKU removed per requirements

        $this->addColumn([
            'index'      => 'id',
            'label'      => trans('admin::app.settings.partner_products.index.datagrid.id'),
            'type'       => 'string',
            'sortable'   => true,
            'searchable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index'      => 'name',
            'label'      => trans('admin::app.products.index.datagrid.name'),
            'type'       => 'string',
            'sortable'   => true,
            'searchable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index'      => 'price',
            'label'      => trans('admin::app.products.index.datagrid.price'),
            'type'       => 'string',
            'sortable'   => true,
            'searchable' => true,
            'filterable' => true,
            'closure'    => function ($row) {
                return Currency::formatMoney($row->currency, (float) $row->price);
            },
        ]);
        $this->addColumn([
            'index'              => 'tag_name',
            'label'              => trans('admin::app.products.index.datagrid.tag-name'),
            'type'               => 'string',
            'searchable'         => false,
            'sortable'           => true,
            'filterable'         => true,
            'filterable_type'    => 'searchable_dropdown',
            'closure'            => fn ($row) => $row->tag_name ?? '--',
            'filterable_options' => [
                'repository' => TagRepository::class,
                'column'     => [
                    'label' => 'name',
                    'value' => 'name',
                ],
            ],
        ]);

        $this->addColumn([
            'index'      => 'group_name',
            'label'      => trans('admin::app.productgroups.title'),
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index'      => 'active',
            'label'      => trans('admin::app.settings.partner_products.index.datagrid.active'),
            'type'       => 'boolean',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true,
        ]);
    }

    /**
     * Prepare actions.
     */
    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('products.view')) {
            $this->addAction([
                'index'  => 'view',
                'icon'   => 'icon-eye',
                'title'  => trans('admin::app.products.index.datagrid.view'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.products.view', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('products.edit')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => trans('admin::app.products.index.datagrid.edit'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.products.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('products.delete')) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.products.index.datagrid.delete'),
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.products.delete', $row->id),
            ]);
        }
    }

    /**
     * Prepare mass actions.
     */
    public function prepareMassActions(): void
    {
        $this->addMassAction([
            'icon'   => 'icon-delete',
            'title'  => trans('admin::app.products.index.datagrid.delete'),
            'method' => 'POST',
            'url'    => route('admin.products.mass_delete'),
        ]);
    }

    // Money formatting centralized in App\Enums\Currency::formatMoney
}
