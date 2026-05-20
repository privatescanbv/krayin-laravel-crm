<?php

use App\Enums\Departments;
use App\Models\Department;
use App\Services\Activities\ActivityGroupResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Models\Activity;

return new class extends Migration
{
    public function up(): void
    {
        // Only needed once, for existing data.
        //        $privatescanDepartmentId = Department::query()
        //            ->where('name', Departments::PRIVATESCAN->value)
        //            ->value('id');
        //
        //        if (! $privatescanDepartmentId) {
        //            if (app()->runningUnitTests()) {
        //                return;
        //            }
        //
        //            throw new RuntimeException('Department privatescan not found');
        //        }
        //
        //        $fallbackGroupId = Department::getGroupIdForDepartmentId($privatescanDepartmentId);
        //
        //        Activity::query()
        //            ->whereNull('group_id')
        //            ->orderBy('id')
        //            ->chunkById(100, function ($activities) use ($fallbackGroupId): void {
        //                foreach ($activities as $activity) {
        //                    $groupId = ActivityGroupResolver::resolve($activity) ?? $fallbackGroupId;
        //
        //                    Activity::withoutEvents(function () use ($activity, $groupId): void {
        //                        Activity::query()
        //                            ->where('id', $activity->id)
        //                            ->update(['group_id' => $groupId]);
        //                    });
        //                }
        //            });
        //
        //        $remaining = Activity::query()->whereNull('group_id')->count();
        //
        //        if ($remaining > 0) {
        //            Log::warning('backfill_activity_group_ids: activities still missing group_id', [
        //                'count' => $remaining,
        //            ]);
        //        }
    }

    public function down(): void
    {
        // Irreversible data backfill.
    }
};
