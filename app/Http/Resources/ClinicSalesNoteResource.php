<?php

namespace App\Http\Resources;

use App\Services\PlainText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Webkul\Activity\Models\Activity;

/**
 * Interne notitie van een sale. `author` komt uit `created_by`, nooit uit `user_id`; null als de maker onbekend is.
 * Verwacht het attribuut `author_user` (zie ClinicSalesLeadController::attachAuthors()).
 *
 * @mixin Activity
 */
class ClinicSalesNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $author = $this->resource->getAttribute('author_user');

        return [
            'id'         => $this->id,
            'text'       => PlainText::fromHtml($this->comment) ?? '',
            'created_at' => $this->created_at?->toIso8601String(),
            'author'     => $author ? ['id' => $author->id, 'name' => $author->name] : null,
        ];
    }
}
