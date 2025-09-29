<?php

namespace App\Repositories;

use App\Models\Shift;
use Illuminate\Support\Carbon;
use Webkul\Core\Eloquent\Repository;

class ShiftRepository extends Repository
{
    public function model(): string
    {
        return Shift::class;
    }

    public function forResource(int $resourceId)
    {
        return $this->model->newQuery()->where('resource_id', $resourceId);
    }

    public function upcomingForResource(int $resourceId, int $limit = 20)
    {
        $today = Carbon::today();

        return $this->forResource($resourceId)
            ->where(function ($q) use ($today) {
                $q->whereNull('period_end')
                    ->orWhereDate('period_end', '>=', $today);
            })
            ->orderBy('period_start')
            ->limit($limit)
            ->get();
    }
}
