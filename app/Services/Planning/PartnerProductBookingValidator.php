<?php

namespace App\Services\Planning;

use App\Models\Clinic;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use App\Models\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class PartnerProductBookingValidator
{
    /**
     * Product IDs with an active partner product linked to the given clinic.
     *
     * @return array<int, int>
     */
    public static function activeProductIdsForClinic(int $clinicId): array
    {
        return PartnerProduct::query()
            ->where('active', true)
            ->whereNotNull('product_id')
            ->whereHas('clinics', fn (Builder $query) => $query->where('clinics.id', $clinicId))
            ->pluck('product_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<int, int>> clinic_id => product_id[]
     */
    public static function activeProductIdsByClinicIds(Collection $clinicIds): array
    {
        if ($clinicIds->isEmpty()) {
            return [];
        }

        $rows = PartnerProduct::query()
            ->where('active', true)
            ->whereNotNull('product_id')
            ->whereHas('clinics', fn (Builder $query) => $query->whereIn('clinics.id', $clinicIds))
            ->with(['clinics' => fn ($query) => $query->whereIn('clinics.id', $clinicIds)->select('clinics.id')])
            ->get(['id', 'product_id']);

        $map = [];
        foreach ($clinicIds as $clinicId) {
            $map[$clinicId] = [];
        }

        foreach ($rows as $partnerProduct) {
            foreach ($partnerProduct->clinics as $clinic) {
                $map[$clinic->id][] = $partnerProduct->product_id;
            }
        }

        foreach ($map as $clinicId => $productIds) {
            $map[$clinicId] = array_values(array_unique($productIds));
        }

        return $map;
    }

    public function canBook(Resource $resource, int $productId): bool
    {
        $resource->loadMissing('clinicDepartment');
        $clinicId = $resource->clinicDepartment?->clinic_id;

        if (! $clinicId) {
            return false;
        }

        return PartnerProduct::forClinicAndProduct($clinicId, $productId)
            ->where('active', true)
            ->exists();
    }

    public function validate(OrderItem $orderItem, Resource $resource): ?JsonResponse
    {
        if (! $orderItem->product_id) {
            return null;
        }

        $resource->loadMissing('clinicDepartment');

        $clinicId = $resource->clinicDepartment?->clinic_id;

        if (! $clinicId) {
            return response()->json([
                'message' => 'De gekozen resource heeft geen clinic gekoppeld. Kies een andere resource of stel de clinic in bij de resource.',
            ], 422);
        }

        if ($this->canBook($resource, $orderItem->product_id)) {
            return null;
        }

        $clinicName = Clinic::query()->whereKey($clinicId)->value('name') ?? "clinic {$clinicId}";
        $productName = $orderItem->relationLoaded('product')
            ? ($orderItem->product?->name ?? "product {$orderItem->product_id}")
            : ($orderItem->product()->value('name') ?? "product {$orderItem->product_id}");

        return response()->json([
            'message' => sprintf(
                'Voor clinic %s is geen actief partnerproduct gekoppeld aan product %s. Koppel eerst een partnerproduct in Instellingen.',
                $clinicName,
                $productName
            ),
        ], 422);
    }
}
