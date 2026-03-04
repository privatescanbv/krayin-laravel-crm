<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Services\Keycloak\KeycloakService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\Activity\Repositories\ActivityRepository;

class PatientFileUploadController extends Controller
{
    public function __construct(
        private readonly KeycloakService $keycloakService,
        private readonly ActivityRepository $activityRepository,
    ) {}

    /**
     * Upload a file and attach it as a FILE activity for the patient.
     *
     * @group Patient documents
     *
     * @urlParam id string required The Keycloak user ID of the patient. Example: 3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d
     *
     * @bodyParam name string required Display name for the document. Example: Bloeduitslag
     * @bodyParam description string optional Description / comment. Example: Resultaten van het bloedonderzoek.
     * @bodyParam file file required The file to upload (max 20 MB).
     *
     * @response 201 scenario="Created" {"message":"File uploaded successfully","id":42}
     * @response 404 scenario="Patient not found" {"message":"Not Found"}
     * @response 422 scenario="Validation error" {"message":"The name field is required.","errors":{}}
     */
    public function store(Request $request, string $keycloakUserId): JsonResponse
    {
        $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file'        => ['required', 'file', 'max:20480'],
        ]);

        [$person, $user] = $this->keycloakService->resolvePersonOrUser($keycloakUserId);

        if (is_null($person)) {
            abort(404);
        }

        $activity = $this->activityRepository->create([
            'title'              => $request->input('name'),
            'comment'            => $request->input('description'),
            'name'               => $request->input('name'),
            'type'               => ActivityType::FILE->value,
            'publish_to_portal'  => true,
            'is_done'            => true,
            'file'               => $request->file('file'),
        ]);

        $activity->persons()->attach($person->id);

        return response()->json(['message' => 'File uploaded successfully', 'id' => $activity->id], 201);
    }
}
