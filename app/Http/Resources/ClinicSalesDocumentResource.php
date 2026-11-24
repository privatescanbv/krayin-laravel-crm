<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Webkul\Activity\Models\File as ActivityFile;

/**
 * Document (bestand van een file-activiteit) van een sale in het kliniekportaal. Verwacht `activity.documentType`
 * eager-loaded. `size` is null als het bestand op geen enkele schijf staat.
 *
 * @mixin ActivityFile
 */
class ClinicSalesDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $type = $this->activity?->documentType;

        return [
            'id'           => $this->id,
            'title'        => (string) ($this->activity?->title ?: $this->name),
            'file_name'    => $this->name,
            'type'         => $type ? ['id' => $type->id, 'label' => $type->name] : null,
            'size'         => $this->size(),
            'created_at'   => $this->created_at?->toIso8601String(),
            'download_url' => route('api.clinic.sales-leads.documents.download', [
                'keycloakUserId' => $request->route('keycloakUserId'),
                'saleId'         => $request->route('saleId'),
                'documentId'     => $this->id,
            ]),
        ];
    }

    private function size(): ?int
    {
        $disk = $this->resolveDisk();

        if ($disk === null) {
            return null;
        }

        try {
            return Storage::disk($disk)->size($this->path);
        } catch (Throwable) {
            return null;
        }
    }
}
