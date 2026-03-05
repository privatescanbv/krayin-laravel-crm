<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PatientDocumentsIndexRequest;
use App\Http\Resources\PatientDocumentResource;
use App\Models\Order;
use App\Repositories\ActivityRepository;
use App\Services\Keycloak\KeycloakService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Models\File as ActivityFile;

class PatientDocumentController extends Controller
{
    public function __construct(
        private readonly KeycloakService $keycloakService,
        private readonly ActivityRepository $activityRepository,
    ) {}

    /**
     * Get all documents for a patient (FILE activities with publish_to_portal = true).
     *
     * Documents are linked to the patient via any known relation:
     * person_activities, lead, sales lead, or order.
     *
     * @group Patient documents
     *
     * @urlParam id string required The Keycloak user ID of the patient. Example: 3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d
     *
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Items per page (max 100). Example: 15
     * @queryParam order_id integer Optional: limit documents to a single Order id. Example: 987
     * @queryParam type string Optional: document kind (stored in activity.additional.document_type). Example: report
     *
     * @response 200 scenario="Success" {"data":[{"id":456,"patient_id":123,"type":"report","group":"Order MRI knie","title":"MRI uitslag knie","file_name":"mri-knie-uitslag.pdf","mime_type":"application/pdf","size":245678,"download_url":"https://api.example.com/api/patient/3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d/documents/456/download","created_at":"2025-01-20T10:15:30Z"}],"meta":{"current_page":1,"per_page":15,"total":42}}
     * @response 200 scenario="Success (empty)" {"data":[],"meta":{"current_page":1,"per_page":15,"total":0}}
     * @response 404 scenario="Patient not found" {"message":"Not Found"}
     */
    public function index(PatientDocumentsIndexRequest $request, string $keycloakUserId): AnonymousResourceCollection
    {
        [$person, $user] = $this->keycloakService->resolvePersonOrUser($keycloakUserId);

        if (is_null($person)) {
            if (! is_null($user)) {
                return PatientDocumentResource::collection(collect());
            }

            abort(404);
        }

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);
        $orderIdFilter = isset($validated['order_id']) ? (int) $validated['order_id'] : null;
        $documentTypeFilter = isset($validated['type']) ? (string) $validated['type'] : null;

        $paginator = $this->activityRepository
            ->paginateDocumentFilesForPerson($person, $perPage, $documentTypeFilter, $orderIdFilter)
            ->appends($request->query());

        // Build order title map lazily from the current page results.
        $orderIdsOnPage = $paginator->getCollection()
            ->map(fn (ActivityFile $file) => $file->activity?->order_id)
            ->filter()
            ->unique()
            ->all();

        $orderTitlesById = empty($orderIdsOnPage) ? [] : Order::query()
            ->whereIn('id', $orderIdsOnPage)
            ->pluck('title', 'id')
            ->map(fn ($title) => (string) $title)
            ->all();

        $items = $paginator->getCollection()->map(function (ActivityFile $file) use ($person, $orderTitlesById) {
            $orderId = (int) ($file->activity?->order_id ?? 0);
            $orderTitle = $orderTitlesById[$orderId] ?? null;
            $group = $orderTitle ? trim('Order '.$orderTitle) : null;

            return [
                'file'        => $file,
                'description' => $file->activity?->comment ?: '',
                'patient_id'  => (int) $person->id,
                'group'       => $group !== '' ? $group : null,
            ];
        });

        $mappedPaginator = new LengthAwarePaginator(
            $items,
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return PatientDocumentResource::collection($mappedPaginator);
    }

    /**
     * Download a patient document (activity file).
     *
     * @group Patient documents
     *
     * @urlParam id string required The Keycloak user ID of the patient. Example: 3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d
     * @urlParam documentId integer required The activity_files id. Example: 456
     *
     * @response 404 scenario="Not found" {"message":"Not Found"}
     */
    public function download(string $keycloakUserId, int $documentId): StreamedResponse
    {
        [$person, $user] = $this->keycloakService->resolvePersonOrUser($keycloakUserId);

        if (is_null($person)) {
            abort(404);
        }

        $file = ActivityFile::query()
            ->with(['activity'])
            ->findOrFail($documentId);

        $accessible = Activity::query()
            ->where('id', $file->activity_id)
            ->ofType(ActivityType::FILE)
            ->publishedToPortal()
            ->forPerson($person)
            ->exists();

        if (! $accessible) {
            abort(404);
        }
        $downloadName = basename($file->path);
        return Storage::download($file->path, $downloadName);
    }
}
