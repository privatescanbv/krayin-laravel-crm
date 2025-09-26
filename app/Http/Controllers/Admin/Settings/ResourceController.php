<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\ResourceDataGrid;
use App\Repositories\ResourceRepository;
use App\Repositories\ResourceTypeRepository;
use Illuminate\Http\Request;

class ResourceController extends SimpleEntityController
{
    public function __construct(
        protected ResourceRepository $resourceRepository,
        protected ResourceTypeRepository $resourceTypeRepository
    ) {
        parent::__construct($resourceRepository);

        $this->entityName       = 'resource';
        $this->datagridClass    = ResourceDataGrid::class;
        $this->indexView        = 'admin::settings.resources.index';
        $this->createView       = 'admin::settings.resources.create';
        $this->editView         = 'admin::settings.resources.edit';
        $this->indexRoute       = 'admin.settings.resources.index';
        $this->permissionPrefix = 'settings.resources';
    }

    protected function getCreateViewData(Request $request): array
    {
        return [
            'resourceTypes' => $this->resourceTypeRepository->all(),
        ];
    }

    protected function getEditViewData(Request $request, \Illuminate\Database\Eloquent\Model $entity): array
    {
        return [
            'resource'      => $entity,
            'resourceTypes' => $this->resourceTypeRepository->all(),
        ];
    }

    protected function validateStore(Request $request): void
    {
        $request->validate([
            'type'              => 'required|string|max:100',
            'name'              => 'required|unique:resources,name|max:100',
            'resource_type_id'  => 'required|exists:resource_types,id',
        ]);
    }

    protected function validateUpdate(Request $request, int $id): void
    {
        $request->validate([
            'type'              => 'required|string|max:100',
            'name'              => 'required|max:100|unique:resources,name,'.$id,
            'resource_type_id'  => 'required|exists:resource_types,id',
        ]);
    }

    protected function getCreateSuccessMessage(): string
    {
        return trans('admin::app.settings.resources.index.create-success');
    }

    protected function getUpdateSuccessMessage(): string
    {
        return trans('admin::app.settings.resources.index.update-success');
    }

    protected function getDestroySuccessMessage(): string
    {
        return trans('admin::app.settings.resources.index.destroy-success');
    }

    protected function getDeleteFailedMessage(): string
    {
        return trans('admin::app.settings.resources.index.delete-failed');
    }
}
