<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\ResourceTypeDataGrid;
use App\Repositories\ResourceTypeRepository;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
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

    public function store(Request $request): RedirectResponse
    {
        $request->validate( [
            'name'        => 'required|unique:resource_types,name|max:100',
            'description' => 'nullable|string',
        ]);

        Event::dispatch('settings.resource_type.create.before');

        $entity = $this->resourceTypeRepository->create($request->all());

        Event::dispatch('settings.resource_type.create.after', $entity);

        return redirect()
            ->route('admin.settings.resource_types.index')
            ->with('success', trans('admin::app.settings.resource_types.index.create-success'));
    }

    public function edit(int $id): View
    {
        $resourceType = $this->resourceTypeRepository->findOrFail($id);

        return view('admin::settings.resource_types.edit', compact('resourceType'));
    }

    public function update(Request $request,int $id): RedirectResponse
    {
        $request->validate( [
            'name'        => 'required|max:100|unique:resource_types,name,'.$id,
            'description' => 'nullable|string',
        ]);

        Event::dispatch('settings.resource_type.update.before', $id);

        $entity = $this->resourceTypeRepository->update($request->all(), $id);

        Event::dispatch('settings.resource_type.update.after', $entity);

        return redirect()
            ->route('admin.settings.resource_types.index')
            ->with('success', trans('admin::app.settings.resource_types.index.update-success'));
    }

    public function destroy(Request $request, ?int $id = null): JsonResponse|RedirectResponse
    {
        if (! $id) {
            $indices = $request['indices'];
            if (is_array($indices) && count($indices) > 0) {
                $id = (int) $indices[0];
            }
        }

        if (! $id) {
            return redirect()
                ->route('admin.settings.resource_types.index')
                ->with('error', 'Geen geldig ID opgegeven.');
        }

        $entity = $this->resourceTypeRepository->findOrFail($id);

        try {
            Event::dispatch('settings.resource_type.delete.before', $id);

            $entity->delete();

            Event::dispatch('settings.resource_type.delete.after', $id);

            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'message' => trans('admin::app.settings.resource_types.index.destroy-success'),
                ], 200);
            }

            return redirect()
                ->route('admin.settings.resource_types.index')
                ->with('success', trans('admin::app.settings.resource_types.index.destroy-success'));
        } catch (Exception $exception) {
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'message' => trans('admin::app.settings.resource_types.index.delete-failed'),
                ], 400);
            }

            return redirect()
                ->route('admin.settings.resource_types.index')
                ->with('error', trans('admin::app.settings.resource_types.index.delete-failed'));
        }
    }
}
