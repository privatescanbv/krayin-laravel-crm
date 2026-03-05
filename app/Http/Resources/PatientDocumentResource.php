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
     * - group: string|null
     */
    public function toArray(Request $request): array
    {
        /** @var array{file: ActivityFile, patient_id: int, group?: string|null} $payload */
        $payload = is_array($this->resource) ? $this->resource : [];

        /** @var ActivityFile $file */
        $file = $payload['file'];
        $description = $payload['description'];
        $patientId = (int) ($payload['patient_id'] ?? 0);
        $group = isset($payload['group']) ? (string) $payload['group'] : null;
        $keycloakUserId = (string) ($request->route('id') ?? '');

        $activity = $file->activity;

        $mimeType = 'application/octet-stream';
        $size = 0;

        $disk = $file->resolveDisk();

        if ($disk === null) {
            logger()->warning('PatientDocumentResource: file missing on all disks', [
                'activity_file_id' => $file->id,
                'path'             => $file->path,
            ]);
        } else {
            try {
                $mimeType = Storage::disk($disk)->mimeType($file->path) ?: $mimeType;
            } catch (Throwable $e) {
                logger()->warning('PatientDocumentResource: could not retrieve mime type', [
                    'activity_file_id' => $file->id,
                    'path'             => $file->path,
                    'error'            => $e->getMessage(),
                ]);
            }

            try {
                $size = Storage::disk($disk)->size($file->path) ?? 0;
            } catch (Throwable $e) {
                logger()->warning('PatientDocumentResource: could not retrieve file size', [
                    'activity_file_id' => $file->id,
                    'path'             => $file->path,
                    'error'            => $e->getMessage(),
                ]);
            }
        }

        $documentType = data_get($activity?->additional, 'document_type')
            ?? data_get($activity?->additional, 'type')
            ?? 'file';

        $title = (string) ($activity?->title ?: $file->name);

        return [
            'id'           => (int) $file->id,
            'patient_id'   => $patientId,
            'type'         => (string) $documentType,
            'group'        => $group,
            'title'        => $title,
            'description'  => $description,
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
