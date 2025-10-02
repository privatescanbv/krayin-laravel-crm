<?php

namespace Webkul\Admin\DataGrids\Product;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;
use Webkul\Tag\Repositories\TagRepository;

class ProductDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        $tablePrefix = DB::getTablePrefix();

        $queryBuilder = DB::table('products')
            ->leftJoin('product_inventories', 'products.id', '=', 'product_inventories.product_id')
            ->leftJoin('product_tags', 'products.id', '=', 'product_tags.product_id')
            ->leftJoin('tags', 'tags.id', '=', 'product_tags.tag_id')
            ->leftJoin('product_groups', 'products.product_group_id', '=', 'product_groups.id')
            ->select(
                'products.id',
                'products.name',
                'products.currency',
                'products.price',
                'products.active',
                'tags.name as tag_name',
                'product_groups.name as group_name'
            )
            ->addSelect(DB::raw('SUM(product_inventories.in_stock) as total_in_stock'))
            ->addSelect(DB::raw('SUM(product_inventories.allocated) as total_allocated'))
            ->addSelect(DB::raw('SUM(product_inventories.in_stock - product_inventories.allocated) as total_on_hand'))
            ->groupBy('products.id');

        if (request()->route('id')) {
            $queryBuilder->where('product_inventories.warehouse_id', request()->route('id'));
        }

        $this->addFilter('id', 'products.id');
        $this->addFilter('total_in_stock', DB::raw('SUM('.$tablePrefix.'product_inventories.in_stock'));
        $this->addFilter('total_allocated', DB::raw('SUM('.$tablePrefix.'product_inventories.allocated'));
        $this->addFilter('total_on_hand', DB::raw('SUM('.$tablePrefix.'product_inventories.in_stock - '.$tablePrefix.'product_inventories.allocated'));
        $this->addFilter('tag_name', 'tags.name');
        $this->addFilter('group_name', 'product_groups.name');

        return $queryBuilder;
    }

    /**
     * Add columns.
     */
    public function prepareColumns(): void
    {
        // SKU removed per requirements

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
                return $this->formatMoney($row->currency, (float) $row->price);
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

    private function formatMoney(?string $currency, float $amount): string
    {
        $symbolMap = [
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            'CHF' => 'CHF',
            'DKK' => 'kr',
            'NOK' => 'kr',
            'SEK' => 'kr',
        ];

        $symbol = $symbolMap[$currency ?? 'EUR'] ?? $currency ?? '';

        $formatted = number_format($amount, 2, ',', '.');

        // Place symbol before amount for common currencies, otherwise append code
        if (in_array($currency, ['EUR', 'USD', 'GBP'], true)) {
            return $symbol . ' ' . $formatted;
        }

        if (isset($symbolMap[$currency ?? ''])) {
            return $formatted . ' ' . $symbol;
        }

        return ($currency ? $currency . ' ' : '') . $formatted;
    }
}
