<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\ClinicDataGrid;
use App\Http\Controllers\Concerns\NormalizesContactFields;
use App\Http\Requests\Admin\Settings\StoreClinicRequest;
use App\Http\Requests\Admin\Settings\UpdateClinicRequest;
use App\Models\Address;
use App\Models\AfbDispatchOrder;
use App\Repositories\ClinicRepository;
use App\Services\ContactValidationRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        $clinic = $this->clinicRepository->find($id);

        $visitAddress = $this->handleAddress($request, 'visit_address');
        $postalAddress = $isPostalSameAsVisit
            ? $visitAddress
            : $this->handleAddress($request, 'postal_address');

        // Delete orphaned address records when an address is cleared.
        // When isPostalSameAsVisit is true and the visit address is cleared, the postal
        // address must also be removed — even if it was a separate record.
        if (is_null($visitAddress) && $clinic?->visit_address_id) {
            Address::where('id', $clinic->visit_address_id)->delete();

            // Also clean up a separate postal address when the two were kept in sync.
            if (
                $isPostalSameAsVisit
                && $clinic->postal_address_id
                && $clinic->postal_address_id !== $clinic->visit_address_id
            ) {
                Address::where('id', $clinic->postal_address_id)->delete();
            }
        }

        if (
            ! $isPostalSameAsVisit
            && is_null($postalAddress)
            && $clinic?->postal_address_id
            && $clinic->postal_address_id !== $clinic->visit_address_id
        ) {
            Address::where('id', $clinic->postal_address_id)->delete();
        }

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
            'visitAddress', 'postalAddress', 'resources.resourceType', 'resources.clinicDepartment', 'creator', 'updater',
            'afbDispatches.email',
            'afbDispatches.items.order.salesLead.persons',
            'afbDispatches.items.person',
            'departments',
        ])->findOrFail($id);
        $activitiesCount = $this->activityRepository->countOpen($clinic)->getData()->data;

        return view('adminc.clinics.view', [
            'clinic'          => $clinic,
            'activitiesCount' => $activitiesCount,
        ]);
    }

    public function downloadAfbDocument(int $id, int $dispatchOrderId)
    {
        $clinic = $this->clinicRepository->findOrFail($id);

        $dispatchOrder = AfbDispatchOrder::query()
            ->where('clinic_id', $clinic->id)
            ->findOrFail($dispatchOrderId);

        if (! Storage::disk('local')->exists($dispatchOrder->file_path)) {
            return redirect()->back()->with('error', 'AFB document niet gevonden op storage.');
        }

        return Storage::disk('local')->download(
            $dispatchOrder->file_path,
            $dispatchOrder->file_name
        );
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
        $isPostalSameAsVisit = $request->boolean('is_postal_address_same_as_visit_address', false);

        $request->validate(
            StoreClinicRequest::rulesForCreate($isPostalSameAsVisit),
            [],
            $this->clinicAddressAttributes($isPostalSameAsVisit),
        );
    }

    protected function validateUpdate(Request $request, int $id): void
    {
        $isPostalSameAsVisit = $request->boolean('is_postal_address_same_as_visit_address', false);

        $request->validate(
            UpdateClinicRequest::rulesForUpdate($id, $isPostalSameAsVisit),
            [],
            $this->clinicAddressAttributes($isPostalSameAsVisit),
        );
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

    private function clinicAddressAttributes(bool $isPostalSameAsVisit): array
    {
        $attrs = ContactValidationRules::strictAddressAttributes('visit_address', 'bezoekadres');

        if (! $isPostalSameAsVisit) {
            $attrs = array_merge(
                $attrs,
                ContactValidationRules::strictAddressAttributes('postal_address', 'postadres'),
            );
        }

        return $attrs;
    }

    private function handleAddress(Request $request, string $payloadKey = 'address'): ?Address
    {
        $addressData = $request->get($payloadKey, []);

        if (! is_array($addressData)) {
            return null;
        }

        // Explicit clear request from the UI ("Adres wissen" button).
        if (! empty($addressData['_clear'])) {
            return null;
        }

        unset($addressData['_clear']);

        $filled = array_filter($addressData, fn ($v) => $v !== null && $v !== '');

        if (empty($filled)) {
            return null;
        }

        return Address::updateOrCreate(
            [
                'postal_code'  => $addressData['postal_code'] ?? null,
                'house_number' => $addressData['house_number'] ?? null,
            ],
            $addressData
        );
    }
}
