<?php

namespace App\Http\Resources;

use App\Models\PatientNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Response resource for patient notifications index endpoint.
 *
 * Shape:
 * - notifications: array<PatientNotificationResource>
 * - meta: { current_page, per_page, total }
 *
 * @property array{
 *   notifications: \Illuminate\Support\Collection<int, PatientNotification>,
 *   meta: array{current_page:int, per_page:int, total:int}
 * }|mixed $resource
 */
class PatientNotificationsResponseResource extends JsonResource
{
    public static $wrap = null;

    public static function empty(int $perPage = 10): self
    {
        return new self([
            'notifications' => collect(),
            'meta'          => [
                'current_page' => 1,
                'per_page'     => $perPage,
                'total'        => 0,
            ],
        ]);
    }

    public static function fromPaginator(LengthAwarePaginator $paginator): self
    {
        return new self([
            'notifications' => $paginator->getCollection(),
            'meta'          => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    public function toArray(Request $request): array
    {
        $notifications = collect(data_get($this->resource, 'notifications', collect()))
            ->map(fn (PatientNotification $n) => (new PatientNotificationResource($n))->toArray($request))
            ->values();

        return [
            'notifications' => $notifications,
            'meta'          => (array) data_get($this->resource, 'meta', []),
        ];
    }
}
