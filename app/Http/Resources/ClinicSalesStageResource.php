<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Webkul\Lead\Models\Stage;

/** @mixin Stage */
class ClinicSalesStageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'code'       => $this->code,
            'name'       => $this->name,
            'sort_order' => $this->sort_order,
            'is_won'     => $this->is_won,
            'is_lost'    => $this->is_lost,
        ];
    }
}
