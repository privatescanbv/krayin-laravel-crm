<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\ClinicDataGrid;
use App\Http\Controllers\Concerns\NormalizesContactFields;
use App\Repositories\ClinicRepository;
use App\Services\ClinicValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class ClinicController extends SimpleEntityController
{
    use NormalizesContactFields;

    public function __construct(protected ClinicRepository $clinicRepository)
    {
        parent::__construct($clinicRepository);

        $this->entityName = 'clinic';
        $this->datagridClass = ClinicDataGrid::class;
        $this->indexView = 'admin.clinics.index';
        $this->createView = 'admin.clinics.create';
        $this->editView = 'admin.clinics.edit';
        $this->indexRoute = 'admin.settings.clinics.index';
        $this->permissionPrefix = 'settings.clinics';
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        // Normalize contact fields before validation
        $this->normalizeContactFields($request);

        return parent::store($request);
    }

    public function update(Request $request, int $id): RedirectResponse|JsonResponse
    {
        // Normalize contact fields before validation
        $this->normalizeContactFields($request);

        return parent::update($request, $id);
    }

    public function view(int $id)
    {
        $clinic = $this->clinicRepository->with(['address', 'resources.resourceType', 'creator', 'updater'])->findOrFail($id);

        return view('admin.clinics.view', ['clinic' => $clinic]);
    }

    public function destroy(Request $request, ?int $id = null): RedirectResponse|JsonResponse
    {
        if (! $id) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'message' => $this->getDeleteFailedMessage(),
                ], 400);
            }

            return redirect()->route($this->indexRoute)->with('error', $this->getDeleteFailedMessage());
        }

        try {
            $this->clinicRepository->deleteWithResourceDetach($id);
        } catch (Throwable $ex) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'message' => $this->getDeleteFailedMessage(),
                ], 400);
            }

            return redirect()->route($this->indexRoute)->with('error', $this->getDeleteFailedMessage());
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'message' => $this->getDestroySuccessMessage(),
            ], 200);
        }

        return redirect()->route($this->indexRoute)->with('success', $this->getDestroySuccessMessage());
    }

    protected function validateStore(Request $request): void
    {
        $request->validate(ClinicValidationService::getCreateValidationRules());
    }

    protected function validateUpdate(Request $request, int $id): void
    {
        $request->validate(ClinicValidationService::getUpdateValidationRules($id));
    }

    protected function transformPayload(array $payload, ?int $id = null): array
    {
        // Contact fields are already normalized by normalizeContactFields() in store/update
        // This method can be used for additional transformations if needed in the future
        return $payload;
    }

    protected function getCreateSuccessMessage(): string
    {
        return trans('admin::app.settings.clinics.index.create-success');
    }

    protected function getUpdateSuccessMessage(): string
    {
        return trans('admin::app.settings.clinics.index.update-success');
    }

    protected function getDestroySuccessMessage(): string
    {
        return trans('admin::app.settings.clinics.index.destroy-success');
    }

    protected function getDeleteFailedMessage(): string
    {
        return trans('admin::app.settings.clinics.index.delete-failed');
    }
}
