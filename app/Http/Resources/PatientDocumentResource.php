<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Webkul\Activity\Models\File as ActivityFile;

/**
 * @property ActivityFile|mixed $resource
 */
class PatientDocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * The resource is expected to be an array like:
     * - file: \Webkul\Activity\Models\File
     * - patient_id: int
     */
    public function toArray(Request $request): array
    {
        /** @var array{file: ActivityFile, patient_id: int} $payload */
        $payload = is_array($this->resource) ? $this->resource : [];

        /** @var ActivityFile $file */
        $file = $payload['file'];
        $patientId = (int) ($payload['patient_id'] ?? 0);
        $keycloakUserId = (string) ($request->route('id') ?? '');

        $activity = $file->activity;

        $mimeType = 'application/octet-stream';
        $size = 0;

        try {
            $mimeType = Storage::mimeType($file->path) ?: $mimeType;
        } catch (Throwable) {
        }

        try {
            $size = (int) (Storage::size($file->path) ?? 0);
        } catch (Throwable) {
        }

        $documentType = data_get($activity?->additional, 'document_type')
            ?? data_get($activity?->additional, 'type')
            ?? 'file';

        $title = (string) ($activity?->title ?: $file->name);

        return [
            'id'           => (int) $file->id,
            'patient_id'   => $patientId,
            'type'         => (string) $documentType,
            'title'        => $title,
            'file_name'    => (string) $file->name,
            'mime_type'    => (string) $mimeType,
            'size'         => $size,
            'download_url' => route('api.patient.documents.download', [
                'id'         => $keycloakUserId,
                'documentId' => (int) $file->id,
            ]),
            'created_at'   => $file->created_at?->toISOString(),
        ];
    }
}
