<?php

namespace App\Http\Resources;

use App\Enums\NotificationReferenceType;
use App\Models\PatientNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array|mixed $resource
 */
class PatientNotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PatientNotification $notification */
        $notification = $this->resource;

        return [
            'id'          => (int) $notification->id,
            'type'        => (string) $notification->type,
            'dismissable' => (bool) $notification->dismissable,
            'title'       => (string) $notification->title,
            'summary'     => $notification->summary,
            'reference'   => [
                'type' => $notification->reference_type?->value,
                'id'   => (int) $notification->reference_id,
                'url'  => $this->resolveReferenceUrl($notification->reference_type, $notification->reference_id),
            ],
            'created_at'  => $notification->created_at?->toISOString(),
        ];
    }

    /**
     * Resolve the URL for a notification reference.
     *
     * @return string|null URL to the referenced resource, or null if not available
     */
    private function resolveReferenceUrl(?NotificationReferenceType $type, int|string|null $id): ?string
    {
        if ($type === null || $id === null) {
            return null;
        }

        return match ($type) {
            NotificationReferenceType::ACTIVITY => config('services.portal.patient.web_url').'/patient/documents',
            NotificationReferenceType::GVL_FORM => config('services.portal.patient.web_url')."/patient/forms/{{ $id }}/step/1"
        };
    }
}
