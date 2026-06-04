<?php

namespace App\Services\Planning;

use App\Models\Clinic;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use App\Models\Resource;
use Illuminate\Http\JsonResponse;

class PartnerProductBookingValidator
{
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

        $hasActivePartnerProduct = PartnerProduct::forClinicAndProduct($clinicId, $orderItem->product_id)
            ->where('active', true)
            ->exists();

        if ($hasActivePartnerProduct) {
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
