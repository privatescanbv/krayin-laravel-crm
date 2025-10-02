<?php

namespace App\Observers;

use App\Services\PersonDuplicateCacheService;
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
        protected PersonDuplicateCacheService $duplicateCacheService
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
    }

    /**
     * Handle the Person "deleted" event.
     */
    public function deleted(Person $person): void
    {
        // Invalidate duplicate cache for this person
        $this->duplicateCacheService->invalidatePersonCache($person->id);
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
                    'user_id' => auth()->id() ?? $person->user_id ?? 1,
                ]);

                $person->activities()->attach($activity->id);
            }
        }
    }
}
