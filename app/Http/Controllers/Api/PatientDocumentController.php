<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PatientDocumentsIndexRequest;
use App\Http\Resources\PatientDocumentResource;
use App\Models\DocumentType;
use App\Models\Order;
use App\Repositories\ActivityRepository;
use App\Services\Keycloak\KeycloakService;
use Illuminate\Http\JsonResponse;
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
     * Get all documents for a patient (FILE activities published to their portal via pivot).
     *
     * @group Patient documents
     *
     * @urlParam id string required The Keycloak user ID of the patient. Example: 3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d
     *
     * @queryParam page integer Page number. Example: 1
     * @queryParam per_page integer Items per page (max 100). Example: 15
     * @queryParam order_id integer Optional: limit documents to a single Order id. Example: 987
     * @queryParam type string Optional: one or more document_types ids, comma-separated. Example: 2,5
     *
     * @response 200 scenario="Success" {"data":[{"id":456,"patient_id":123,"type":{"id":2,"label":"Verslagen"},"group":"Order MRI knie","title":"MRI uitslag knie","file_name":"mri-knie-uitslag.pdf","mime_type":"application/pdf","size":245678,"download_url":"https://api.example.com/api/patient/3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d/documents/456/download","created_at":"2025-01-20T10:15:30Z"}],"meta":{"current_page":1,"per_page":15,"total":42}}
     * @response 200 scenario="Success (empty)" {"data":[],"meta":{"current_page":1,"per_page":15,"total":0}}
     * @response 403 scenario="Caller is not a patient (e.g. a clinic-portal account)" {"message":"Forbidden"}
     * @response 404 scenario="Patient not found" {"message":"Not Found"}
     */
    public function index(PatientDocumentsIndexRequest $request, string $keycloakUserId): AnonymousResourceCollection
    {
        $person = $this->keycloakService->resolvePatientOrFail($keycloakUserId);

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);
        $orderIdFilter = isset($validated['order_id']) ? (int) $validated['order_id'] : null;

        $documentTypeIds = null;
        if (isset($validated['type']) && $validated['type'] !== '') {
            $documentTypeIds = array_values(array_filter(
                array_map('intval', explode(',', (string) $validated['type']))
            ));
        }

        $paginator = $this->activityRepository
            ->paginateDocumentFilesForPerson($person, $perPage, $documentTypeIds, $orderIdFilter)
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
     * List document types a patient can filter their documents by.
     *
     * @group Patient documents
     *
     * @urlParam id string required The Keycloak user ID of the patient. Example: 3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d
     *
     * @response 200 scenario="Success" {"data":[{"id":2,"label":"Verslagen"},{"id":4,"label":"Beelden"}]}
     * @response 403 scenario="Caller is not a patient (e.g. a clinic-portal account)" {"message":"Forbidden"}
     * @response 404 scenario="Patient not found" {"message":"Not Found"}
     */
    public function types(string $keycloakUserId): JsonResponse
    {
        $this->keycloakService->resolvePatientOrFail($keycloakUserId);

        $types = DocumentType::query()
            ->where('show_in_patient_portal', true)
            ->ordered()
            ->get()
            ->map(fn (DocumentType $type) => ['id' => $type->id, 'label' => $type->name])
            ->values();

        return response()->json(['data' => $types]);
    }

    /**
     * Download a patient document (activity file).
     *
     * @group Patient documents
     *
     * @urlParam id string required The Keycloak user ID of the patient. Example: 3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d
     * @urlParam documentId integer required The activity_files id. Example: 456
     *
     * @response 403 scenario="Caller is not a patient (e.g. a clinic-portal account)" {"message":"Forbidden"}
     * @response 404 scenario="Not found" {"message":"Not Found"}
     */
    public function download(string $keycloakUserId, int $documentId): StreamedResponse
    {
        $person = $this->keycloakService->resolvePatientOrFail($keycloakUserId);

        $file = ActivityFile::query()
            ->with(['activity'])
            ->findOrFail($documentId);

        $accessible = Activity::query()
            ->where('id', $file->activity_id)
            ->ofType(ActivityType::FILE)
            ->publishedToPortalForPerson($person)
            ->exists();

        if (! $accessible) {
            abort(404);
        }

        $disk = $file->resolveDisk();

        if ($disk === null) {
            logger()->warning('Patient document download failed: file missing on all disks', [
                'activity_file_id' => $file->id,
                'path'             => $file->path,
            ]);
            abort(404);
        }

        $downloadName = basename($file->path);

        return Storage::disk($disk)->download($file->path, $downloadName);
    }
}
