<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\ClinicDepartmentDataGrid;
use App\Http\Controllers\Concerns\HandlesReturnUrl;
use App\Http\Requests\Admin\Settings\StoreClinicDepartmentRequest;
use App\Http\Requests\Admin\Settings\UpdateClinicDepartmentRequest;
use App\Repositories\ClinicDepartmentRepository;
use App\Repositories\ClinicRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

class ClinicDepartmentController extends SimpleEntityController
{
    use HandlesReturnUrl;

    public function __construct(
        protected ClinicDepartmentRepository $departmentRepository,
        private readonly ClinicRepository $clinicRepository,
    ) {
        parent::__construct($departmentRepository);

        $this->entityName = 'clinic_department';
        $this->datagridClass = ClinicDepartmentDataGrid::class;
        $this->indexView = 'adminc.clinic-departments.index';
        $this->createView = 'adminc.clinic-departments.create';
        $this->editView = 'adminc.clinic-departments.edit';
        $this->indexRoute = 'admin.clinic_departments.index';
        $this->permissionPrefix = 'settings.clinics';
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->validateStore($request);

        Event::dispatch('settings.clinic_department.create.before');

        $entity = $this->repository->create($this->transformPayload($request->all()));

        Event::dispatch('settings.clinic_department.create.after', $entity);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'data'    => $entity,
                'message' => $this->getCreateSuccessMessage(),
            ], 200);
        }

        return $this->redirectWithReturnUrl($this->indexRoute, [], 'success', $this->getCreateSuccessMessage());
    }

    public function update(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $this->validateUpdate($request, $id);

        Event::dispatch('settings.clinic_department.update.before', $id);

        $entity = $this->repository->update($this->transformPayload($request->all(), $id), $id);

        Event::dispatch('settings.clinic_department.update.after', $entity);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'data'    => $entity,
                'message' => $this->getUpdateSuccessMessage(),
            ]);
        }

        return $this->redirectWithReturnUrl($this->indexRoute, [], 'success', $this->getUpdateSuccessMessage());
    }

    protected function getCreateViewData(Request $request): array
    {
        return [
            'clinics'   => $this->clinicRepository->allActive(),
            'clinic_id' => $request->input('clinic_id'),
        ];
    }

    protected function getEditViewData(Request $request, Model $entity): array
    {
        return [
            'clinic_department' => $entity,
            'clinics'           => $this->clinicRepository->allActive(),
        ];
    }

    protected function validateStore(Request $request): void
    {
        $request->validate(StoreClinicDepartmentRequest::rules());
    }

    protected function validateUpdate(Request $request, int $id): void
    {
        $request->validate(UpdateClinicDepartmentRequest::rules());
    }

    protected function getCreateSuccessMessage(): string
    {
        return 'Afdeling succesvol aangemaakt.';
    }

    protected function getUpdateSuccessMessage(): string
    {
        return 'Afdeling succesvol bijgewerkt.';
    }

    protected function getDestroySuccessMessage(): string
    {
        return 'Afdeling succesvol verwijderd.';
    }

    protected function getDeleteFailedMessage(): string
    {
        return 'Afdeling kon niet worden verwijderd.';
    }
}
