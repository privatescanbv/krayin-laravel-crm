<?php

namespace Webkul\Admin\Http\Controllers\Contact\Persons;

use App\Enums\DuplicateEntityType;
use App\Services\DuplicateFalsePositiveService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Resources\PersonResource;
use Webkul\Contact\Repositories\PersonRepository;
use App\Services\DuplicateReasonHelpers;

class DuplicateController extends Controller
{
    use DuplicateReasonHelpers;
    
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected PersonRepository $personRepository,
        protected DuplicateFalsePositiveService $falsePositiveService
    ) {
    }

    /**
     * Show potential duplicates for a person.
     */
    public function index(int $personId): View
    {
        $person = $this->personRepository->with(['organization', 'user'])->findOrFail($personId);
        $duplicates = $this->personRepository->findPotentialDuplicates($person);

        // Use PersonResource for consistent data formatting
        $personData = (new PersonResource($person))->resolve();

        // Compute per-duplicate match reasons
        $primaryEmails = $this->extractValues($personData['emails'] ?? []);
        $primaryPhones = $this->extractValues($personData['phones'] ?? []);

        // Populate primary person signals so UI doesn't show '-'
        $personData['matched_emails'] = $primaryEmails;
        $personData['matched_phones'] = array_map(fn($p) => $this->normalizePhone($p), $primaryPhones);
        $personData['name_reason'] = null; // not applicable for primary itself

        $duplicatesData = [];
        foreach ($duplicates as $dup) {
            $dupData = (new PersonResource($dup))->resolve();
            $reasons = $this->computeReasons($personData, $dupData, $primaryEmails, $primaryPhones);

            $dupData['matched_emails'] = $reasons['email'];
            $dupData['matched_phones'] = $reasons['phone'];
            $dupData['name_reason'] = $reasons['name_reason'];

            $duplicatesData[] = $dupData;
        }

        return view('admin::contacts.persons.duplicates.index', [
            'person' => $person,
            'duplicates' => $duplicates,
            'personData' => $personData,
            'duplicatesData' => $duplicatesData,
        ]);
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
            'count' => $duplicates->count(),
        ]);
    }

    /**
     * Merge selected persons.
     */
    public function merge(): JsonResponse
    {
        $this->validate(request(), [
            'primary_person_id' => 'required|exists:persons,id',
            'duplicate_person_ids' => 'required|array|min:1',
            'duplicate_person_ids.*' => 'exists:persons,id',
            'field_mappings' => 'nullable|array',
        ]);

        $primaryPersonId = request('primary_person_id');
        $duplicatePersonIds = request('duplicate_person_ids');
        $fieldMappings = request('field_mappings', []);

        try {
            $mergedPerson = $this->personRepository->mergePersons($primaryPersonId, $duplicatePersonIds, $fieldMappings);

            return response()->json([
                'success' => true,
                'message' => __('messages.person.merge_success'),
                'merged_person' => [
                    'id' => $mergedPerson->id,
                    'name' => $mergedPerson->name,
                ],
            ]);
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
            'entity_ids' => 'required|array|min:2',
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

            return response()->json([
                'success' => true,
                'message' => 'Geselecteerde personen gemarkeerd als geen duplicaat.',
                'pairs'   => $pairs,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Opslaan false positive mislukt: ' . $e->getMessage(),
            ], 500);
        }
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
            'has_duplicates' => $hasDuplicates,
            'duplicates_count' => $duplicatesCount,
        ]);
    }
}