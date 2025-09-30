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

    public function view(int $id)
    {
        $clinic = $this->clinicRepository->with(['address', 'partnerProducts', 'resources.resourceType', 'creator', 'updater'])->findOrFail($id);

        return view('admin::settings.clinics.view', compact('clinic'));
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

    protected function transformPayload(array $payload, ?int $id = null): array
    {
        // Filter en normaliseer emails
        if (isset($payload['emails']) && is_array($payload['emails'])) {
            $payload['emails'] = array_values(array_filter($payload['emails'], function($email) {
                // Filter out empty values
                return isset($email['value']) && !empty(trim($email['value']));
            }));
            
            // Normalize is_default to boolean
            $payload['emails'] = array_map(function($email) {
                if (isset($email['is_default'])) {
                    $email['is_default'] = $email['is_default'] === true || $email['is_default'] === 'on' || $email['is_default'] === '1';
                } else {
                    $email['is_default'] = false;
                }
                return $email;
            }, $payload['emails']);
        }

        // Filter en normaliseer phones
        if (isset($payload['phones']) && is_array($payload['phones'])) {
            $payload['phones'] = array_values(array_filter($payload['phones'], function($phone) {
                // Filter out empty values
                return isset($phone['value']) && !empty(trim($phone['value']));
            }));
            
            // Normalize is_default to boolean
            $payload['phones'] = array_map(function($phone) {
                if (isset($phone['is_default'])) {
                    $phone['is_default'] = $phone['is_default'] === true || $phone['is_default'] === 'on' || $phone['is_default'] === '1';
                } else {
                    $phone['is_default'] = false;
                }
                return $phone;
            }, $payload['phones']);
        }

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
