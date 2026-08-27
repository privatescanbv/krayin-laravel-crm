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
     * Fields that change who a person matches as a duplicate.
     */
    private const DUPLICATE_MATCH_FIELDS = ['first_name', 'last_name', 'married_name', 'emails', 'phones'];

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

        // Only invalidate (not eager-refresh) on create: a duplicate partner created in the
        // same request/import batch may not exist in the DB yet, and eagerly computing here
        // would cache a false "no duplicates" result for up to an hour.
        if (! config('import.skip_duplicate_cache', false)) {
            $this->duplicateCacheService->invalidatePersonCache($person->id);
        }

        // Do not support for create person
        //        $this->ensurePortalAccountOnCreate($person);

        Log::info('CREATE person', [
            'person_id' => $person->id,
            'name'      => $person->name,
        ]);
    }

    /**
     * Handle the Person "updating" event.
     *
     * We sync portal changes here because `updated` can have an empty changeset
     * depending on other listeners/traits touching the model state.
     */
    public function updating(Person $person): void
    {
        $this->handlePortalSyncOnUpdating($person);
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

        // Refresh duplicate cache + has_duplicates flag if relevant fields changed, right away
        // rather than just invalidating (skipped during bulk imports). Both sides of every affected
        // pair are recomputed: the person itself, the counterparts of its pre-edit identity (so a
        // now-broken match is cleared) and of its new identity (so a new match is flagged). Without
        // the pre-edit side a counterpart keeps a stale has_duplicates=true until the hourly index
        // rebuild - see PersonDuplicateFlagRatchetTest / PersonDuplicateCounterpartTest.
        if (! config('import.skip_duplicate_cache', false) && $person->wasChanged(self::DUPLICATE_MATCH_FIELDS)) {
            // getOriginal() still holds the pre-save values during the "updated" event.
            $preEditIdentity = (new Person)->setRawAttributes($person->getRawOriginal());

            $this->duplicateCacheService->refreshPersonCache($person->id);
            $this->duplicateCacheService->refreshMany(array_merge(
                $this->duplicateCacheService->counterpartIdsFor($preEditIdentity)->all(),
                $this->duplicateCacheService->counterpartIdsFor($person)->all(),
            ));
        }

        // Log activities for fixed fields
        $this->logFixedFieldsActivity($person);

        $this->handlePortalDeactivationOnUpdate($person);
    }

    /**
     * Handle the Person "deleted" event.
     */
    public function deleted(Person $person): void
    {
        // Invalidate this person's cache and recompute its counterparts: a person that only
        // matched the one just deleted must have its stale has_duplicates flag cleared now, not
        // at the next hourly index rebuild. Skipped during bulk imports.
        if (! config('import.skip_duplicate_cache', false)) {
            $this->duplicateCacheService->invalidatePersonCache($person->id);
            $this->duplicateCacheService->refreshMany(
                $this->duplicateCacheService->counterpartIdsFor($person)
            );
        }

        $this->deletePortalAccount($person, 'deleted');
    }

    protected function handlePortalSyncOnUpdating(Person $person): void
    {
        if (! $this->shouldManagePortal($person)) {
            return;
        }

        $dirtyFields = array_keys($person->getDirty());

        if (empty($dirtyFields)) {
            return;
        }

        $relevantFields = [
            'emails',
            'first_name',
            'last_name',
            'lastname_prefix',
            'married_name',
            'married_name_prefix',
            'password',
        ];

        $portalFields = array_values(array_intersect($relevantFields, $dirtyFields));

        if (! empty($portalFields)) {
            $this->updatePortalAccount($person, $portalFields);
        }
    }

    protected function handlePortalDeactivationOnUpdate(Person $person): void
    {
        if (! $this->isKeycloakConfigured()) {
            return;
        }

        // Use original/current comparison here to avoid reliance on the model changeset.
        $wasActive = (bool) $person->getOriginal('is_active');
        $isActive = (bool) $person->is_active;

        if ($wasActive && ! $isActive) {
            $this->deletePortalAccount($person, 'deactivated');
        }
    }

    protected function updatePortalAccount(Person $person, array $changedFields): void
    {
        $result = $this->personKeycloakService->update($person, $changedFields);

        if (! $result['success']) {
            Log::error('Failed to update portal account for person', [
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
            Log::error('Failed to delete portal account for person', [
                'person_id' => $person->id,
                'reason'    => $reason,
                'message'   => $result['message'] ?? null,
            ]);
        }

        // Use direct DB update to avoid dirty-state issues: during the
        // `updated` event, $this->original has not yet been synced by
        // finishSave(), so calling $person->save() here would re-persist
        // every field from the original update (and any attributes leaked
        // into $this->attributes by the CustomAttribute trait).
        DB::table('persons')->where('id', $person->id)->update([
            'keycloak_user_id' => null,
            'is_active'        => false,
        ]);

        $person->keycloak_user_id = null;
        $person->is_active = false;

        Log::info('Person portal account deleted', [
            'person_id' => $person->id,
            'reason'    => $reason,
        ]);
    }

    /**
     * @return bool true if sync portal is required (person has existing Keycloak account)
     */
    protected function shouldManagePortal(Person $person): bool
    {
        if (! $this->isKeycloakConfigured()) {
            return false;
        }

        return ! empty($person->keycloak_user_id) && ! empty($person->findDefaultEmail());
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

                $this->activityRepository->createSystem([
                    'title'      => "$fieldLabel gewijzigd",
                    'person_id'  => $person->id,
                    'additional' => [
                        'attribute' => $fieldLabel,
                        'new'       => ['value' => $newValue, 'label' => $newValue],
                        'old'       => ['value' => $oldValue, 'label' => $oldValue],
                    ],
                    'user_id' => auth()->id() ?? 1,
                ]);
            }
        }
    }
}
