<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\ResourceTypeDataGrid;
use App\Repositories\ResourceTypeRepository;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\View\View;
use Illuminate\Support\Facades\Event;
use Webkul\Admin\Http\Controllers\Controller;

class ResourceTypeController extends Controller
{
    public function __construct(protected ResourceTypeRepository $resourceTypeRepository) {}

    public function index(): View|JsonResponse
    {
        if (request()->ajax() || request()->wantsJson()) {
            return datagrid(ResourceTypeDataGrid::class)->process();
        }

        return view('admin::settings.resource_types.index');
    }

    public function create(): View
    {
        return view('admin::settings.resource_types.create');
    }

    public function store(): JsonResponse
    {
        $this->validate(request(), [
            'name'        => 'required|unique:resource_types,name|max:100',
            'description' => 'nullable|string',
        ]);

        Event::dispatch('settings.resource_type.create.before');

        $entity = $this->resourceTypeRepository->create([
            'name'        => request('name'),
            'description' => request('description'),
        ]);

        Event::dispatch('settings.resource_type.create.after', $entity);

        return new JsonResponse([
            'data'    => $entity,
            'message' => trans('admin::app.settings.resource_types.index.create-success'),
        ]);
    }

    public function edit(int $id): JsonResource
    {
        $entity = $this->resourceTypeRepository->findOrFail($id);

        return new JsonResource([
            'data' => $entity,
        ]);
    }

    public function update(int $id): JsonResponse
    {
        $this->validate(request(), [
            'name'        => 'required|max:100|unique:resource_types,name,'.$id,
            'description' => 'nullable|string',
        ]);

        Event::dispatch('settings.resource_type.update.before', $id);

        $entity = $this->resourceTypeRepository->update([
            'name'        => request('name'),
            'description' => request('description'),
        ], $id);

        Event::dispatch('settings.resource_type.update.after', $entity);

        return new JsonResponse([
            'data'    => $entity,
            'message' => trans('admin::app.settings.resource_types.index.update-success'),
        ]);
    }

    public function destroy(?int $id = null): JsonResponse
    {
        $id = $id ?? (int) request('id');
        if (! $id) {
            $indices = request('indices');
            if (is_array($indices) && count($indices) > 0) {
                $id = (int) $indices[0];
            }
        }

        $entity = $this->resourceTypeRepository->findOrFail($id);

        try {
            Event::dispatch('settings.resource_type.delete.before', $id);

            $entity->delete();

            Event::dispatch('settings.resource_type.delete.after', $id);

            return new JsonResponse([
                'message' => trans('admin::app.settings.resource_types.index.destroy-success'),
            ], 200);
        } catch (Exception $exception) {
            return new JsonResponse([
                'message' => trans('admin::app.settings.resource_types.index.delete-failed'),
            ], 400);
        }
    }
}

