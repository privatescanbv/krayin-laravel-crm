<?php

namespace Webkul\Admin\Http\Controllers\Contact\Persons;

use App\Enums\DuplicateEntityType;
use App\Exceptions\CannotMergePersonWithPortalException;
use App\Services\DuplicateFalsePositiveService;
use App\Services\DuplicateReasonHelpers;
use App\Services\PersonDuplicateCacheService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Resources\PersonResource;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;

class DuplicateController extends Controller
{
    use DuplicateReasonHelpers;

    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected PersonRepository $personRepository,
        protected DuplicateFalsePositiveService $falsePositiveService,
        protected PersonDuplicateCacheService $personDuplicateCacheService,
    ) {}

    /**
     * Show potential duplicates for a person.
     */
    public function index(int $personId): View
    {
        $person = $this->personRepository->with(['organization', 'user', 'address'])->findOrFail($personId);
        $duplicates = $this->personRepository->findPotentialDuplicates($person);

        // Viewing this page recomputes duplicates directly (bypassing the cache service), so the
        // persisted has_duplicates flag would otherwise stay stale until the next hourly index
        // rebuild even though this page just proved the real answer. Self-heal it here.
        $this->personDuplicateCacheService->persistHasDuplicatesFlag($person->id, $duplicates->pluck('id'));

        // A stale cache entry would re-flag the person on the next cached lookup, so drop it too.
        if ($duplicates->isEmpty()) {
            $this->personDuplicateCacheService->invalidatePersonCache($person->id);
        }

        // findPotentialDuplicates returns a Support Collection, so load per model.
        $duplicates->each(fn (Person $dup) => $dup->loadMissing(['organization', 'address']));

        $personData = $this->mergeScreenFields($person);

        $primaryEmails = $this->extractValues($personData['emails'] ?? []);
        $primaryPhones = $this->extractValues($personData['phones'] ?? []);

        $personData['matched_emails'] = $primaryEmails;
        $personData['matched_phones'] = array_map(fn ($p) => $this->normalizePhone($p), $primaryPhones);
        $personData['name_reason'] = null;

        $duplicatesData = [];
        foreach ($duplicates as $dup) {
            $dupData = $this->mergeScreenFields($dup);
            $reasons = $this->computeReasons($personData, $dupData, $primaryEmails, $primaryPhones);

            $dupData['matched_emails'] = $reasons['email'];
            $dupData['matched_phones'] = $reasons['phone'];
            $dupData['name_reason'] = $reasons['name_reason'];

            $duplicatesData[] = $dupData;
        }

        return view('admin::contacts.persons.duplicates.index', [
            'person'         => $person,
            'duplicates'     => $duplicates,
            'personData'     => $personData,
            'duplicatesData' => $duplicatesData,
        ]);
    }

    /**
     * Complete field set for the person merge screen.
     *
     * Kept in one place on purpose: the merge UI compares and maps these keys directly.
     * PersonResource stays lean for lookups/API and is not the source of truth here.
     *
     * @return array<string, mixed>
     */
    private function mergeScreenFields(Person $person): array
    {
        return [
            'id'                             => $person->id,
            'name'                           => $person->name,
            'salutation'                     => $person->salutation?->value,
            'first_name'                     => $person->first_name,
            'last_name'                      => $person->last_name,
            'lastname_prefix'                => $person->lastname_prefix,
            'married_name'                   => $person->married_name,
            'married_name_prefix'            => $person->married_name_prefix,
            'initials'                       => $person->initials,
            'date_of_birth'                  => $person->date_of_birth?->format('Y-m-d'),
            'gender'                         => $person->gender?->value,
            'job_title'                      => $person->job_title,
            'national_identification_number' => $person->national_identification_number,
            'preferred_language'             => $person->preferred_language?->value,
            'preferred_language_label'       => $person->preferred_language?->label() ?? 'Geen',
            'is_active'                      => (bool) $person->is_active,
            'is_active_label'                => $person->is_active ? 'Actief' : 'Inactief',
            'organization_id'                => $person->organization_id,
            'organization_name'              => $person->organization?->name,
            // Nested organization kept for the duplicates summary table (organization?.name).
            'organization'                   => $person->organization
                ? ['id' => $person->organization->id, 'name' => $person->organization->name]
                : null,
            'emails'                         => $person->emails ?? [],
            'phones'                         => $person->phones ?? [],
            'address'                        => $person->relationLoaded('address') ? $person->address : null,
            'created_at'                     => $person->created_at,
            'updated_at'                     => $person->updated_at,
            'has_portal_account'             => $person->hasPortalAccount(),
        ];
    }

    /**
     * Get potential duplicates for a person via AJAX.
     */
    public function getDuplicates(int $personId): JsonResponse
    {
        $person = $this->personRepository->findOrFail($personId);
        $duplicates = $this->personRepository->findPotentialDuplicates($person);

        return response()->json([
            'duplicates' => PersonResource::collection($duplicates),
            'count'      => $duplicates->count(),
        ]);
    }

    /**
     * Merge selected persons.
     */
    public function merge(int $id): JsonResponse
    {
        $this->validate(request(), [
            'primary_person_id'     => 'required|exists:persons,id',
            'duplicate_person_ids'  => 'required|array|min:1',
            'duplicate_person_ids.*'=> 'exists:persons,id',
            'field_mappings'        => 'nullable|array',
        ]);

        if ($id !== (int) request('primary_person_id')) {
            return response()->json([
                'success' => false,
                'message' => __('messages.person.merge_failed', ['error' => 'Persoon in URL komt niet overeen met de primaire persoon.']),
            ], 422);
        }

        $primaryPersonId = request('primary_person_id');
        $duplicatePersonIds = request('duplicate_person_ids');
        $fieldMappings = request('field_mappings', []);

        try {
            $mergedPerson = $this->personRepository->mergePersons($primaryPersonId, $duplicatePersonIds, $fieldMappings);

            // Shown on the page the client redirects to (no JS alert).
            session()->flash('success', __('messages.person.merge_success'));

            return response()->json([
                'success'       => true,
                'message'       => __('messages.person.merge_success'),
                'merged_person' => [
                    'id'   => $mergedPerson->id,
                    'name' => $mergedPerson->name,
                ],
            ]);
        } catch (CannotMergePersonWithPortalException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('messages.person.merge_failed', ['error' => $e->getMessage()]),
            ], 500);
        }
    }

    /**
     * Mark selected persons as "not a duplicate" (false positive) for duplicate detection.
     */
    public function markFalsePositive(int $personId): JsonResponse
    {
        $this->validate(request(), [
            'entity_ids'   => 'required|array|min:2',
            'entity_ids.*' => 'integer|distinct|exists:persons,id',
        ]);

        $entityIds = array_map('intval', request('entity_ids', []));

        // Ensure the selection is anchored to the current person page (prevents cross-entity misuse from UI).
        if (! in_array($personId, $entityIds, true)) {
            return response()->json([
                'success' => false,
                'message' => 'De selectie moet de primaire persoon bevatten.',
            ], 422);
        }

        try {
            $pairs = $this->falsePositiveService->storeForEntities(
                DuplicateEntityType::PERSON,
                $entityIds,
                null
            );

            foreach ($entityIds as $id) {
                $this->personDuplicateCacheService->getCachedDuplicates($id);
            }

            session()->flash('success', 'Geselecteerde personen gemarkeerd als geen duplicaat.');

            return response()->json([
                'success' => true,
                'message' => 'Geselecteerde personen gemarkeerd als geen duplicaat.',
                'pairs'   => $pairs,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Opslaan false positive mislukt: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Undo a wrong "not a duplicate" marking so the pair is detected again.
     */
    public function unmarkFalsePositive(int $personId): RedirectResponse
    {
        $this->validate(request(), [
            'entity_id' => 'required|integer|not_in:'.$personId.'|exists:persons,id',
        ]);

        $otherId = (int) request('entity_id');

        $this->falsePositiveService->removePair(DuplicateEntityType::PERSON, $personId, $otherId);

        foreach ([$personId, $otherId] as $id) {
            $this->personDuplicateCacheService->refreshPersonCache($id);
        }

        session()->flash('success', 'Markering "geen duplicaat" is ongedaan gemaakt.');

        return back();
    }

    /**
     * Confirmation screen before undoing a merge (no JS confirm(): browsers can suppress those).
     */
    public function unmergeConfirm(int $personId): View
    {
        $this->validate(request(), [
            'entity_id' => 'required|integer',
        ]);

        return view('admin::contacts.persons.duplicates.unmerge', [
            'person'       => $this->personRepository->findOrFail($personId),
            'mergedPerson' => $this->findMergedPerson($personId, (int) request('entity_id')),
        ]);
    }

    /**
     * Undo a wrong merge: restore the soft-deleted person and mark the pair "not a duplicate".
     *
     * Relations moved during the merge are not tracked, so they are not moved back; staff
     * redistribute those by hand (the steps are logged on both persons).
     */
    public function unmerge(int $personId): RedirectResponse
    {
        $this->validate(request(), [
            'entity_id' => 'required|integer',
        ]);

        $primary = $this->personRepository->findOrFail($personId);
        $restored = $this->findMergedPerson($personId, (int) request('entity_id'));

        $steps = "Controleer bij {$primary->name} (#{$primary->id}) wat van {$restored->name} (#{$restored->id}) is en zet dat met de hand terug: "
            .'contactpersoon op leads/sales, anamneses, orderregels, activiteiten en e-mails. '
            ."Verwijder ook e-mailadres, telefoonnummer en eventueel adres van {$restored->name} bij {$primary->name}.";

        DB::transaction(function () use ($primary, $restored, $steps) {
            $restored->restore();

            $this->falsePositiveService->storeForEntities(DuplicateEntityType::PERSON, [$primary->id, $restored->id]);

            foreach ([$primary, $restored] as $person) {
                app(ActivityRepository::class)->create([
                    'type'      => 'system',
                    'title'     => 'System: Samenvoegen ongedaan gemaakt',
                    'comment'   => "Samenvoegen van {$restored->name} (#{$restored->id}) met {$primary->name} (#{$primary->id}) ongedaan gemaakt. {$steps}",
                    'is_done'   => true,
                    'person_id' => $person->id,
                    'user_id'   => auth()->id(),
                ]);
            }
        });

        foreach ([$primary->id, $restored->id] as $id) {
            $this->personDuplicateCacheService->refreshPersonCache($id);
        }

        session()->flash('success', "Persoon hersteld. {$steps}");

        return redirect()->route('admin.contacts.persons.view', $restored->id);
    }

    /**
     * Only persons merged into this one may be restored, never an arbitrary soft-deleted person.
     */
    private function findMergedPerson(int $personId, int $entityId): Person
    {
        $mergedPerson = $this->personRepository->mergedAwayPersons($personId)->firstWhere('id', $entityId);

        abort_unless($mergedPerson, 422, 'Deze persoon is niet in deze persoon samengevoegd.');

        return $mergedPerson;
    }

    /**
     * Check if a person has potential duplicates (for AJAX calls).
     */
    public function checkDuplicates(int $personId): JsonResponse
    {
        $person = $this->personRepository->findOrFail($personId);
        $hasDuplicates = $this->personRepository->hasPotentialDuplicates($person);
        $duplicatesCount = $hasDuplicates ? $this->personRepository->findPotentialDuplicates($person)->count() : 0;

        return response()->json([
            'has_duplicates'   => $hasDuplicates,
            'duplicates_count' => $duplicatesCount,
        ]);
    }
}
