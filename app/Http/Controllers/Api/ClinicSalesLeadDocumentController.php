<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesClinicDoctor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ClinicSalesDocumentIndexRequest;
use App\Http\Resources\ClinicSalesDocumentResource;
use App\Models\SalesLead;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tab Dateien van de patiëntkaart (tickets/clinic-portaal/06-tab-dateien.md). Alleen lezen en downloaden.
 */
class ClinicSalesLeadDocumentController extends Controller
{
    use ResolvesClinicDoctor;

    /**
     * Documents of one sale assigned to the doctor: files of the sale and of its orders, only of document types
     * shown in the clinic portal. Newest first.
     *
     * @group Clinic sales
     *
     * @urlParam keycloakUserId string required The Keycloak user ID of the doctor.
     * @urlParam saleId integer required The sale id (must be assigned to this doctor and visible to the role).
     *
     * @response 200 scenario="Success" {"data":[{"id":456,"title":"MRI uitslag knie","file_name":"mri-knie-uitslag.pdf","type":{"id":2,"label":"Verslagen"},"size":245678,"created_at":"2026-11-10T09:00:00+00:00","download_url":"https://api.example.com/api/clinic/3f0b2d3e/sales-leads/12/documents/456/download"}],"links":{},"meta":{}}
     * @response 403 scenario="User is not a doctor, or Bearer token belongs to another user"
     * @response 404 scenario="Unknown user, or sale not assigned to / not visible for this doctor"
     */
    public function index(ClinicSalesDocumentIndexRequest $request, string $keycloakUserId, int $saleId): AnonymousResourceCollection
    {
        $sale = SalesLead::query()->forPortalDoctor($this->doctor($keycloakUserId))->findOrFail($saleId);

        return ClinicSalesDocumentResource::collection(
            $sale->clinicPortalFiles()
                ->with('activity.documentType')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate($request->integer('per_page', 25))
        );
    }

    /**
     * Download one document of the sale (same scope and document-type check as the list).
     *
     * @group Clinic sales
     *
     * @urlParam keycloakUserId string required The Keycloak user ID of the doctor.
     * @urlParam saleId integer required The sale id.
     * @urlParam documentId integer required The document id from the list.
     *
     * @response 403 scenario="User is not a doctor, or Bearer token belongs to another user"
     * @response 404 scenario="Unknown user, sale or document, document not of this sale, or document type not shown in the clinic portal"
     */
    public function download(string $keycloakUserId, int $saleId, int $documentId): StreamedResponse
    {
        $sale = SalesLead::query()->forPortalDoctor($this->doctor($keycloakUserId))->findOrFail($saleId);
        $file = $sale->clinicPortalFiles()->findOrFail($documentId);

        $disk = $file->resolveDisk();
        abort_if($disk === null, 404);

        return Storage::disk($disk)->download($file->path, basename($file->path));
    }
}
