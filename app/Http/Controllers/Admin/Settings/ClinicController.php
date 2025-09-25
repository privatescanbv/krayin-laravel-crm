<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\ClinicDataGrid;
use App\Repositories\ClinicRepository;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;

class ClinicController extends Controller
{
    public function __construct(protected ClinicRepository $clinicRepository) {}

    public function index(): View|JsonResponse
    {
        if (request()->ajax() || request()->wantsJson()) {
            return datagrid(ClinicDataGrid::class)->process();
        }

        return view('admin::settings.clinics.index');
    }

    public function create(): View
    {
        return view('admin::settings.clinics.create');
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'name'   => 'required|unique:clinics,name|max:100',
            'emails' => 'nullable|array',
            'phones' => 'nullable|array',
        ]);

        Event::dispatch('settings.clinic.create.before');

        $clinic = $this->clinicRepository->create($request->all());

        Event::dispatch('settings.clinic.create.after', $clinic);

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'data'    => $clinic,
                'message' => trans('admin::app.settings.clinics.index.create-success'),
            ], 200);
        }

        return redirect()
            ->route('admin.settings.clinics.index')
            ->with('success', trans('admin::app.settings.clinics.index.create-success'));
    }

    public function edit(int $id): View
    {
        $clinic = $this->clinicRepository->findOrFail($id);

        return view('admin::settings.clinics.edit', compact('clinic'));
    }

    public function update(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $request->validate([
            'name'   => 'required|max:100|unique:clinics,name,'.$id,
            'emails' => 'nullable|array',
            'phones' => 'nullable|array',
        ]);

        Event::dispatch('settings.clinic.update.before', $id);

        $clinic = $this->clinicRepository->update($request->all(), $id);

        Event::dispatch('settings.clinic.update.after', $clinic);

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'data'    => $clinic,
                'message' => trans('admin::app.settings.clinics.index.update-success'),
            ], 200);
        }

        return redirect()
            ->route('admin.settings.clinics.index')
            ->with('success', trans('admin::app.settings.clinics.index.update-success'));
    }

    public function destroy(?int $id = null): JsonResponse|RedirectResponse
    {
        // Allow id from request for routes that do not pass parameter
        $id = $id ?? (int) request('id');
        if (! $id) {
            $indices = request('indices');
            if (is_array($indices) && count($indices) > 0) {
                $id = (int) $indices[0];
            }
        }

        if (! $id) {
            return redirect()
                ->route('admin.settings.clinics.index')
                ->with('error', 'Geen geldig ID opgegeven.');
        }

        $clinic = $this->clinicRepository->findOrFail($id);

        try {
            Event::dispatch('settings.clinic.delete.before', $id);

            $clinic->delete();

            Event::dispatch('settings.clinic.delete.after', $id);

            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'message' => trans('admin::app.settings.clinics.index.destroy-success'),
                ], 200);
            }

            return redirect()
                ->route('admin.settings.clinics.index')
                ->with('success', trans('admin::app.settings.clinics.index.destroy-success'));
        } catch (Exception $exception) {
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'message' => trans('admin::app.settings.clinics.index.delete-failed'),
                ], 400);
            }

            return redirect()
                ->route('admin.settings.clinics.index')
                ->with('error', trans('admin::app.settings.clinics.index.delete-failed'));
        }
    }
}
