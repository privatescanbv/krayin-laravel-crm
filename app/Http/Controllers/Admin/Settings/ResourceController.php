<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\ResourceDataGrid;
use App\Repositories\ResourceRepository;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;

class ResourceController extends Controller
{
    public function __construct(protected ResourceRepository $resourceRepository) {}

    public function index(): View|JsonResponse
    {
        if (request()->ajax() || request()->wantsJson()) {
            return datagrid(ResourceDataGrid::class)->process();
        }

        return view('admin::settings.resources.index');
    }

    public function create(): View
    {
        return view('admin::settings.resources.create');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'type' => 'required|string|max:100',
            'name' => 'required|unique:resources,name|max:100',
        ]);

        Event::dispatch('settings.resource.create.before');

        $resource = $this->resourceRepository->create($request->all());

        Event::dispatch('settings.resource.create.after', $resource);

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json(['data' => $resource, 'message' => trans('admin::app.settings.resources.index.create-success')]);
        }

        return redirect()
            ->route('admin.settings.resources.index')
            ->with('success', trans('admin::app.settings.resources.index.create-success'));
    }

    public function edit(Request $request, int $id): View|JsonResponse
    {
        $resource = $this->resourceRepository->findOrFail($id);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['data' => $resource]);
        }

        return view('admin::settings.resources.edit', ['resource' => $resource]);
    }

    public function update(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $request->validate([
            'type' => 'required|string|max:100',
            'name' => 'required|max:100|unique:resources,name,'.$id,
        ]);

        Event::dispatch('settings.resource.update.before', $id);

        $resource = $this->resourceRepository->update($request->all(), $id);

        Event::dispatch('settings.resource.update.after', $resource);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['data' => $resource, 'message' => trans('admin::app.settings.resources.index.update-success')]);
        }

        return redirect()
            ->route('admin.settings.resources.index')
            ->with('success', trans('admin::app.settings.resources.index.update-success'));
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
                ->route('admin.settings.resources.index')
                ->with('error', 'Geen geldig ID opgegeven.');
        }

        $resource = $this->resourceRepository->findOrFail($id);

        try {
            Event::dispatch('settings.resource.delete.before', $id);

            $resource->delete();

            Event::dispatch('settings.resource.delete.after', $id);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'message' => trans('admin::app.settings.resources.index.destroy-success'),
                ], 200);
            }

            return redirect()
                ->route('admin.settings.resources.index')
                ->with('success', trans('admin::app.settings.resources.index.destroy-success'));
        } catch (Exception $exception) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'message' => trans('admin::app.settings.resources.index.delete-failed'),
                ], 400);
            }

            return redirect()
                ->route('admin.settings.resources.index')
                ->with('error', trans('admin::app.settings.resources.index.delete-failed'));
        }
    }
}
