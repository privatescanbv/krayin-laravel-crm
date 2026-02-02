<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PatientDocumentsIndexRequest;
use App\Http\Resources\PatientDocumentsCollection;
use App\Repositories\OrderRepository;
use App\Services\Keycloak\KeycloakService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Activity\Models\File as ActivityFile;
use Webkul\Activity\Repositories\ActivityRepository;

class PatientDocumentController extends Controller
{
    public function __construct(
        private readonly KeycloakService $keycloakService,
        private readonly OrderRepository $orderRepository,
        private readonly ActivityRepository $activityRepository,
    ) {}

    /**
     * Get all documents for a patient (derived from Orders -> Activities (type=file) -> activity_files).
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
     * @response 200 scenario="Success" {"data":[{"id":456,"patient_id":123,"type":"report","title":"MRI uitslag knie","file_name":"mri-knie-uitslag.pdf","mime_type":"application/pdf","size":245678,"download_url":"https://api.example.com/api/patient/3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d/documents/456/download","created_at":"2025-01-20T10:15:30Z"}],"meta":{"current_page":1,"per_page":15,"total":42}}
     * @response 200 scenario="Success (empty)" {"data":[],"meta":{"current_page":1,"per_page":15,"total":0}}
     * @response 404 scenario="Patient not found" {"message":"Not Found"}
     */
    public function index(PatientDocumentsIndexRequest $request, string $keycloakUserId): JsonResponse
    {
        [$person, $user] = $this->keycloakService->resolvePersonOrUser($keycloakUserId);

        if (is_null($person)) {
            if (! is_null($user)) {
                $perPage = (int) $request->validated('per_page', 15);

                return PatientDocumentsCollection::empty($perPage)->response();
            }

            abort(404);
        }

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);
        $orderIdFilter = isset($validated['order_id']) ? (int) $validated['order_id'] : null;
        $documentTypeFilter = isset($validated['type']) ? (string) $validated['type'] : null;

        $orderIds = $this->orderRepository->getIdsForPerson($person);

        if ($orderIdFilter !== null) {
            $orderIds = in_array($orderIdFilter, $orderIds, true) ? [$orderIdFilter] : [];
        }

        if (empty($orderIds)) {
            return PatientDocumentsCollection::empty($perPage)->response();
        }

        $paginator = $this->activityRepository
            ->paginateDocumentFilesForOrders($orderIds, $perPage, $documentTypeFilter)
            ->appends($request->query());

        $documents = $paginator->getCollection()->map(function (ActivityFile $file) use ($person) {
            return [
                'file'            => $file,
                'patient_id'      => (int) $person->id,
            ];
        });

        return PatientDocumentsCollection::fromPaginator($paginator, $documents)->response();
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

        if (($file->activity?->type?->value ?? null) !== ActivityType::FILE->value) {
            abort(404);
        }

        $orderIds = $this->orderRepository->getIdsForPerson($person);

        $orderId = $file->activity?->order_id ? (int) $file->activity->order_id : null;

        if ($orderId === null || ! in_array($orderId, $orderIds, true)) {
            abort(404);
        }

        // Stream from storage; filename comes from activity_files.name.
        return Storage::download($file->path, $file->name);
    }
}
