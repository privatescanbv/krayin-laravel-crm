<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\ClinicDataGrid;
use App\Repositories\ClinicRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class ClinicController extends SimpleEntityController
{
    public function __construct(protected ClinicRepository $clinicRepository)
    {
        parent::__construct($clinicRepository);

        $this->entityName = 'clinic';
        $this->datagridClass = ClinicDataGrid::class;
        $this->indexView = 'admin::settings.clinics.index';
        $this->createView = 'admin::settings.clinics.create';
        $this->editView = 'admin::settings.clinics.edit';
        $this->indexRoute = 'admin.settings.clinics.index';
        $this->permissionPrefix = 'settings.clinics';
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
        $request->validate([
            'name'   => 'required|unique:clinics,name|max:100',
            'emails' => 'nullable|array',
            'phones' => 'nullable|array',
        ]);
    }

    protected function validateUpdate(Request $request, int $id): void
    {
        $request->validate([
            'name'   => 'required|max:100|unique:clinics,name,'.$id,
            'emails' => 'nullable|array',
            'phones' => 'nullable|array',
        ]);
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
