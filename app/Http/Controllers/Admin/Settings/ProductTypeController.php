<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\ProductTypeDataGrid;
use App\Repositories\ProductTypeRepository;
use Illuminate\Http\Request;

class ProductTypeController extends SimpleEntityController
{
    public function __construct(protected ProductTypeRepository $productTypeRepository)
    {
        parent::__construct($productTypeRepository);

        $this->entityName = 'product_type';
        $this->datagridClass = ProductTypeDataGrid::class;
        $this->indexView = 'admin::settings.product_types.index';
        $this->createView = 'admin::settings.product_types.create';
        $this->editView = 'admin::settings.product_types.edit';
        $this->indexRoute = 'admin.settings.product_types.index';
        $this->permissionPrefix = 'settings.product_types';
    }

    protected function validateStore(Request $request): void
    {
        $request->validate([
            'name'        => 'required|unique:product_types,name|max:100',
            'description' => 'nullable|string',
        ]);
    }

    protected function validateUpdate(Request $request, int $id): void
    {
        $request->validate([
            'name'        => 'required|max:100|unique:product_types,name,'.$id,
            'description' => 'nullable|string',
        ]);
    }

    protected function getCreateSuccessMessage(): string
    {
        return trans('admin::app.settings.product_types.index.create-success');
    }

    protected function getUpdateSuccessMessage(): string
    {
        return trans('admin::app.settings.product_types.index.update-success');
    }

    protected function getDestroySuccessMessage(): string
    {
        return trans('admin::app.settings.product_types.index.destroy-success');
    }

    protected function getDeleteFailedMessage(): string
    {
        return trans('admin::app.settings.product_types.index.delete-failed');
    }
}
