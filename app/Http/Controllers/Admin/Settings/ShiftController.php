<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\ShiftDataGrid;
use App\Models\Resource;
use App\Repositories\ResourceRepository;
use App\Repositories\ShiftRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;

class ShiftController extends Controller
{
    public function __construct(protected ShiftRepository $shiftRepository, protected ResourceRepository $resourceRepository) {}

    public function index(Request $request, int $resourceId): View|JsonResponse
    {
        $resource = Resource::findOrFail($resourceId);

        if ($request->ajax() || $request->wantsJson()) {
            return datagrid(ShiftDataGrid::class, ['resourceId' => $resource->id])->process();
        }

        return view('admin::settings.shifts.index', ['resource' => $resource]);
    }

    public function create(Request $request, int $resourceId): View
    {
        $resource = Resource::findOrFail($resourceId);

        $resources = $this->resourceRepository->all();

        return view('admin::settings.shifts.create', ['resource' => $resource, 'resources' => $resources]);
    }

    public function store(Request $request, int $resourceId): RedirectResponse|JsonResponse
    {
        $resource = Resource::findOrFail($resourceId);

        $validated = $this->validatePayload($request);
        $validated['resource_id'] = $resource->id;

        $shift = $this->shiftRepository->create($validated);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['data' => $shift], 200);
        }

        return redirect()->route('admin.settings.resources.shifts.index', $resource->id)
            ->with('success', trans('admin::app.settings.shifts.create-success'));
    }

    public function edit(Request $request, int $resourceId, int $id): View
    {
        $resource = Resource::findOrFail($resourceId);
        $shift = $this->shiftRepository->findOrFail($id);
        $resources = $this->resourceRepository->all();

        return view('admin::settings.shifts.edit', [
            'resource'  => $resource,
            'shift'     => $shift,
            'resources' => $resources,
        ]);
    }

    public function update(Request $request, int $resourceId, int $id): RedirectResponse|JsonResponse
    {
        $resource = Resource::findOrFail($resourceId);
        $validated = $this->validatePayload($request);

        $shift = $this->shiftRepository->update($validated, $id);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['data' => $shift], 200);
        }

        return redirect()->route('admin.settings.resources.shifts.index', $resource->id)
            ->with('success', trans('admin::app.settings.shifts.update-success'));
    }

    public function destroy(Request $request, int $resourceId, ?int $id = null): RedirectResponse|JsonResponse
    {
        $resource = Resource::findOrFail($resourceId);

        $shiftId = $id ?? (int) ($request->input('id') ?? 0);
        abort_if($shiftId <= 0, 404);

        $deleted = $this->shiftRepository->delete($shiftId);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['message' => $deleted ? 'success' : 'failed'], $deleted ? 200 : 400);
        }

        return redirect()->route('admin.settings.resources.shifts.index', $resource->id)
            ->with($deleted ? 'success' : 'error', trans($deleted ? 'admin::app.settings.shifts.delete-success' : 'admin::app.settings.shifts.delete-failed'));
    }

    protected function validatePayload(Request $request): array
    {
        // Remove empty time block rows (both from/to empty) to allow empty days
        $blocks = (array) $request->input('weekday_time_blocks', []);
        foreach ($blocks as $day => $dayBlocks) {
            $blocks[$day] = array_values(array_filter((array) $dayBlocks, function ($b) {
                $from = trim((string) data_get($b, 'from', ''));
                $to = trim((string) data_get($b, 'to', ''));

                return $from !== '' || $to !== '';
            }));
        }
        $request->merge(['weekday_time_blocks' => $blocks]);

        $rules = [
            // new period-based fields (whole days + per-weekday blocks)
            'resource_id'         => 'required|exists:resources,id',
            'period_start'        => 'required|date',
            'period_end'          => 'nullable|date|after:period_start',
            'weekday_time_blocks' => 'required|array',

            // keys 1..7 with arrays of blocks
            'weekday_time_blocks.*'        => 'array',
            'weekday_time_blocks.*.*.from' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'weekday_time_blocks.*.*.to'   => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'notes'                        => 'nullable|string',
            'available'                    => 'sometimes|boolean',
        ];

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($validator) use ($request) {
            $blocks = (array) $request->input('weekday_time_blocks', []);
            foreach ($blocks as $day => $dayBlocks) {
                foreach ((array) $dayBlocks as $index => $block) {
                    $from = data_get($block, 'from');
                    $to = data_get($block, 'to');

                    if ($from !== null && $from !== '' && $to !== null && $to !== '') {
                        [$fh, $fm] = array_map('intval', explode(':', $from));
                        [$th, $tm] = array_map('intval', explode(':', $to));
                        $fromMinutes = ($fh * 60) + $fm;
                        $toMinutes = ($th * 60) + $tm;

                        if ($toMinutes <= $fromMinutes) {
                            $validator->errors()->add(
                                "weekday_time_blocks.$day.$index.to",
                                trans('admin::app.settings.shifts.validation.timeblock_order')
                            );
                        }
                    }
                }
            }
        });

        $validated = $validator->validate();

        // Normalize boolean checkbox
        $validated['available'] = $request->boolean('available', true);

        // Convert empty period_end to null (oneindig geldig)
        if (empty($validated['period_end'])) {
            $validated['period_end'] = null;
        }

        return $validated;
    }
}
