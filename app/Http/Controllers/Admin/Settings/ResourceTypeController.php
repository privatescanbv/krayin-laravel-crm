<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\ResourceTypeDataGrid;
use App\Repositories\ResourceTypeRepository;
use Illuminate\Http\Request;

class ResourceTypeController extends SimpleEntityController
{
    public function __construct(protected ResourceTypeRepository $resourceTypeRepository)
    {
        parent::__construct($resourceTypeRepository);

        $this->entityName       = 'resource_type';
        $this->datagridClass    = ResourceTypeDataGrid::class;
        $this->indexView        = 'admin::settings.resource_types.index';
        $this->createView       = 'admin::settings.resource_types.create';
        $this->editView         = 'admin::settings.resource_types.edit';
        $this->indexRoute       = 'admin.settings.resource_types.index';
        $this->permissionPrefix = 'settings.resource_types';
    }

    protected function validateStore(Request $request): void
    {
        $request->validate([
            'name'        => 'required|unique:resource_types,name|max:100',
            'description' => 'nullable|string',
        ]);
    }

    protected function validateUpdate(Request $request, int $id): void
    {
        $request->validate([
            'name'        => 'required|max:100|unique:resource_types,name,'.$id,
            'description' => 'nullable|string',
        ]);
    }

    protected function getCreateSuccessMessage(): string
    {
        return trans('admin::app.settings.resource_types.index.create-success');
    }

    protected function getUpdateSuccessMessage(): string
    {
        return trans('admin::app.settings.resource_types.index.update-success');
    }

    protected function getDestroySuccessMessage(): string
    {
        return trans('admin::app.settings.resource_types.index.destroy-success');
    }

    protected function getDeleteFailedMessage(): string
    {
        return trans('admin::app.settings.resource_types.index.delete-failed');
    }
}
