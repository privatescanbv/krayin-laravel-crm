<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\ClinicDataGrid;
use App\Http\Controllers\Concerns\NormalizesContactFields;
use App\Http\Requests\Admin\Settings\StoreClinicRequest;
use App\Http\Requests\Admin\Settings\UpdateClinicRequest;
use App\Models\Address;
use App\Repositories\ClinicRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;
use Webkul\Activity\Repositories\ActivityRepository;

class ClinicController extends SimpleEntityController
{
    use NormalizesContactFields;

    public function __construct(
        protected ClinicRepository $clinicRepository,
        private readonly ActivityRepository $activityRepository,
    ) {
        parent::__construct($clinicRepository);

        $this->entityName = 'clinic';
        $this->datagridClass = ClinicDataGrid::class;
        $this->indexView = 'adminc.clinics.index';
        $this->createView = 'adminc.clinics.create';
        $this->editView = 'adminc.clinics.edit';
        $this->indexRoute = 'admin.clinics.index';
        $this->permissionPrefix = 'settings.clinics';
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        // Normalize contact fields before validation
        $this->normalizeContactFields($request);

        $isPostalSameAsVisit = $request->boolean('is_postal_address_same_as_visit_address', false);

        $visitAddress = $this->handleAddress($request, 'visit_address');
        $postalAddress = $isPostalSameAsVisit
            ? $visitAddress
            : $this->handleAddress($request, 'postal_address');

        $request->merge([
            'visit_address_id'                        => $visitAddress?->id,
            'postal_address_id'                       => $postalAddress?->id,
            'is_postal_address_same_as_visit_address' => $isPostalSameAsVisit,
        ]);

        return parent::store($request);
    }

    public function update(Request $request, int $id): RedirectResponse|JsonResponse
    {
        // Normalize contact fields before validation
        $this->normalizeContactFields($request);

        $isPostalSameAsVisit = $request->boolean('is_postal_address_same_as_visit_address', false);

        $visitAddress = $this->handleAddress($request, 'visit_address');
        $postalAddress = $isPostalSameAsVisit
            ? $visitAddress
            : $this->handleAddress($request, 'postal_address');

        $request->merge([
            'is_active'                               => $request->boolean('is_active', false),
            'visit_address_id'                        => $visitAddress?->id,
            'postal_address_id'                       => $postalAddress?->id,
            'is_postal_address_same_as_visit_address' => $isPostalSameAsVisit,
        ]);

        return parent::update($request, $id);
    }

    public function view(int $id)
    {
        $clinic = $this->clinicRepository->with([
            'visitAddress', 'postalAddress', 'resources.resourceType', 'creator', 'updater',
        ])->findOrFail($id);
        $activitiesCount = $this->activityRepository->countOpen($clinic)->getData()->data;

        return view('adminc.clinics.view', [
            'clinic'          => $clinic,
            'activitiesCount' => $activitiesCount,
        ]);
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
        $request->validate(StoreClinicRequest::rulesForCreate());
    }

    protected function validateUpdate(Request $request, int $id): void
    {
        $request->validate(UpdateClinicRequest::rulesForUpdate($id));
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

    private function handleAddress(Request $request, string $payloadKey = 'address'): ?Address
    {
        $addressData = $request->get($payloadKey, []);

        if (! empty($addressData) && is_array($addressData)) {
            return Address::updateOrCreate(
                [
                    'postal_code'  => $addressData['postal_code'] ?? null,
                    'house_number' => $addressData['house_number'] ?? null,
                ],
                $addressData
            );
        }

        return null;
    }
}
