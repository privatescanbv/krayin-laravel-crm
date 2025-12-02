<?php

namespace App\Observers;

use App\Services\PersonDuplicateCacheService;
use App\Services\PersonKeycloakService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Contact\Models\Person;

class PersonObserver
{
    /**
     * Create a new observer instance.
     */
    public function __construct(
        protected ActivityRepository $activityRepository,
        protected PersonDuplicateCacheService $duplicateCacheService,
        protected PersonKeycloakService $personKeycloakService,
    ) {}

    /**
     * Handle the Person "created" event.
     */
    public function created(Person $person): void
    {
        // Set created_by if not already set
        if (is_null($person->created_by) && auth()->check()) {
            DB::table('persons')->where('id', $person->id)->update(['created_by' => auth()->id()]);
        }

        // Invalidate duplicate cache for this person
        $this->duplicateCacheService->invalidatePersonCache($person->id);

        // Do not support for create person
        //        $this->ensurePortalAccountOnCreate($person);

        Log::info('CREATE person', [
            'person_id' => $person->id,
            'name'      => $person->name,
        ]);
    }

    /**
     * Handle the Person "updated" event.
     */
    public function updated(Person $person): void
    {
        // Set updated_by if authenticated user exists
        if (auth()->check()) {
            DB::table('persons')->where('id', $person->id)->update(['updated_by' => auth()->id()]);
        }

        // Invalidate duplicate cache for this person if relevant fields changed
        $duplicateRelevantFields = ['first_name', 'last_name', 'married_name', 'emails', 'phones'];
        if ($person->wasChanged($duplicateRelevantFields)) {
            $this->duplicateCacheService->invalidatePersonCache($person->id);
        }

        // Log activities for fixed fields
        $this->logFixedFieldsActivity($person);

        $this->handlePortalSyncOnUpdate($person);
    }

    /**
     * Handle the Person "deleted" event.
     */
    public function deleted(Person $person): void
    {
        // Invalidate duplicate cache for this person
        $this->duplicateCacheService->invalidatePersonCache($person->id);

        $this->deletePortalAccount($person, 'deleted');
    }

    protected function ensurePortalAccountOnCreate(Person $person): void
    {
        if (! $this->shouldManagePortal($person)) {
            return;
        }

        if ($person->is_active && empty($person->keycloak_user_id)) {
            $this->createPortalAccount($person, 'created');
        }
    }

    protected function handlePortalSyncOnUpdate(Person $person): void
    {
        if (! $this->shouldManagePortal($person)) {
            return;
        }

        $changedFields = array_keys($person->getChanges());

        if (empty($changedFields)) {
            return;
        }

        if ($person->wasChanged('is_active')) {
            if ($person->is_active) {
                if ($person->keycloak_user_id) {
                    $this->updatePortalAccount($person, ['is_active']);
                } else {
                    $this->createPortalAccount($person, 'reactivated');
                }
            } else {
                $this->deletePortalAccount($person, 'deactivated');
            }

            return;
        }

        if ($person->is_active && $person->keycloak_user_id) {
            $relevantFields = [
                'emails',
                'first_name',
                'last_name',
                'lastname_prefix',
                'married_name',
                'married_name_prefix',
                'password',
            ];

            $portalFields = array_values(array_intersect($relevantFields, $changedFields));

            if (! empty($portalFields)) {
                $this->updatePortalAccount($person, $portalFields);
            }
        }
    }

    protected function createPortalAccount(Person $person, string $reason): void
    {
        if (! $this->isKeycloakConfigured()) {
            return;
        }

        $result = $this->personKeycloakService->create($person);

        if (! $result['success']) {
            Log::warning('Failed to create portal account for person', [
                'person_id' => $person->id,
                'reason'    => $reason,
                'message'   => $result['message'] ?? null,
            ]);

            return;
        }

        if (! empty($result['keycloak_user_id'])) {
            Person::withoutEvents(function () use ($person, $result) {
                $person->forceFill([
                    'keycloak_user_id' => $result['keycloak_user_id'],
                    'is_active'        => true,
                ])->save();
            });
        }

        Log::info('Person portal account synced (create)', [
            'person_id'        => $person->id,
            'reason'           => $reason,
            'keycloak_user_id' => $result['keycloak_user_id'] ?? null,
        ]);
    }

    protected function updatePortalAccount(Person $person, array $changedFields): void
    {
        if (! $this->isKeycloakConfigured()) {
            return;
        }

        $result = $this->personKeycloakService->update($person, $changedFields);

        if (! $result['success']) {
            Log::warning('Failed to update portal account for person', [
                'person_id' => $person->id,
                'fields'    => $changedFields,
                'message'   => $result['message'] ?? null,
            ]);
        } else {
            Log::info('Person portal account updated', [
                'person_id' => $person->id,
                'fields'    => $changedFields,
            ]);
        }
    }

    protected function deletePortalAccount(Person $person, string $reason): void
    {
        if (! $this->isKeycloakConfigured() || empty($person->keycloak_user_id)) {
            return;
        }

        $result = $this->personKeycloakService->delete($person);

        if (! $result['success']) {
            Log::warning('Failed to delete portal account for person', [
                'person_id' => $person->id,
                'reason'    => $reason,
                'message'   => $result['message'] ?? null,
            ]);

            return;
        }

        Person::withoutEvents(function () use ($person) {
            $person->forceFill([
                'keycloak_user_id' => null,
                'is_active'        => false,
            ])->save();
        });

        Log::info('Person portal account deleted', [
            'person_id' => $person->id,
            'reason'    => $reason,
        ]);
    }

    protected function shouldManagePortal(Person $person): bool
    {
        if (! $this->isKeycloakConfigured()) {
            return false;
        }

        return ! empty($person->findDefaultEmail());
    }

    protected function isKeycloakConfigured(): bool
    {
        return ! empty(Config::get('services.keycloak.client_id'));
    }

    /**
     * Log activities for fixed fields (first_name, last_name, maiden_name, etc.)
     */
    private function logFixedFieldsActivity(Person $person): void
    {
        $fixedFields = [
            'first_name',
            'last_name',
            'lastname_prefix',
            'maiden_name',
            'maiden_name_prefix',
            'initials',
            'salutation',
            'job_title',
            'date_of_birth',
        ];

        $fieldLabels = [
            'first_name'         => 'Voornaam',
            'last_name'          => 'Achternaam',
            'lastname_prefix'    => 'Tussenvoegsel',
            'maiden_name'        => 'Aangetrouwde naam',
            'maiden_name_prefix' => 'Aangetrouwde naam tussenvoegsel',
            'initials'           => 'Initialen',
            'salutation'         => 'Aanhef',
            'job_title'          => 'Functie',
            'date_of_birth'      => 'Geboortedatum',
        ];

        foreach ($fixedFields as $field) {
            if ($person->wasChanged($field)) {
                $oldValue = $person->getOriginal($field);
                $newValue = $person->$field;

                // Skip if both values are empty/null
                if (empty($oldValue) && empty($newValue)) {
                    continue;
                }

                $fieldLabel = $fieldLabels[$field];

                // Format date values for display
                if ($field === 'date_of_birth') {
                    $oldValue = $oldValue ? $oldValue->format('d-m-Y') : '-';
                    $newValue = $newValue ? $newValue->format('d-m-Y') : '-';
                } else {
                    $oldValue = $oldValue ?: '-';
                    $newValue = $newValue ?: '-';
                }

                $activity = $this->activityRepository->create([
                    'type'       => 'system',
                    'title'      => "$fieldLabel gewijzigd",
                    'is_done'    => 1,
                    'additional' => json_encode([
                        'attribute' => $fieldLabel,
                        'new'       => [
                            'value' => $newValue,
                            'label' => $newValue,
                        ],
                        'old' => [
                            'value' => $oldValue,
                            'label' => $oldValue,
                        ],
                    ]),
                    'user_id' => auth()->id() ?? 1,
                ]);

                $person->activities()->attach($activity->id);
            }
        }
    }
}
