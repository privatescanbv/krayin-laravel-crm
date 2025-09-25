<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\ClinicDataGrid;
use App\Repositories\ClinicRepository;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;

class ClinicController extends Controller
{
    public function __construct(protected ClinicRepository $clinicRepository) {}

    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(ClinicDataGrid::class)->process();
        }

        return view('admin::settings.clinics.index');
    }

    public function create(): View
    {
        return view('admin::settings.clinics.create');
    }

    public function store(): JsonResponse
    {
        $this->validate(request(), [
            'name'   => 'required|unique:clinics,name|max:100',
            'emails' => 'nullable|array',
            'phones' => 'nullable|array',
        ]);

        Event::dispatch('settings.clinic.create.before');

        $clinic = $this->clinicRepository->create([
            'name'   => request('name'),
            'emails' => request('emails'),
            'phones' => request('phones'),
        ]);

        Event::dispatch('settings.clinic.create.after', $clinic);

        return new JsonResponse([
            'data'    => $clinic,
            'message' => trans('admin::app.settings.clinics.index.create-success'),
        ]);
    }

    public function edit(int $id): JsonResource
    {
        $clinic = $this->clinicRepository->findOrFail($id);

        return new JsonResource([
            'data' => $clinic,
        ]);
    }

    public function update(int $id): JsonResponse
    {
        $this->validate(request(), [
            'name'   => 'required|max:100|unique:clinics,name,'.$id,
            'emails' => 'nullable|array',
            'phones' => 'nullable|array',
        ]);

        Event::dispatch('settings.clinic.update.before', $id);

        $clinic = $this->clinicRepository->update([
            'name'   => request('name'),
            'emails' => request('emails'),
            'phones' => request('phones'),
        ], $id);

        Event::dispatch('settings.clinic.update.after', $clinic);

        return new JsonResponse([
            'data'    => $clinic,
            'message' => trans('admin::app.settings.clinics.index.update-success'),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $clinic = $this->clinicRepository->findOrFail($id);

        try {
            Event::dispatch('settings.clinic.delete.before', $id);

            $clinic->delete($id);

            Event::dispatch('settings.clinic.delete.after', $id);

            return new JsonResponse([
                'message' => trans('admin::app.settings.clinics.index.destroy-success'),
            ], 200);
        } catch (Exception $exception) {
            return new JsonResponse([
                'message' => trans('admin::app.settings.clinics.index.delete-failed'),
            ], 400);
        }
    }
}
