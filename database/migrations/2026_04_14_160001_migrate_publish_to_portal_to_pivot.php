<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Webkul\Activity\Models\Activity;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('activities', 'publish_to_portal')) {
            return;
        }

        $this->backfillPivot();

        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('publish_to_portal');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('activities', 'publish_to_portal')) {
            return;
        }

        Schema::table('activities', function (Blueprint $table) {
            $table->boolean('publish_to_portal')->default(false)->after('is_done');
        });

        DB::table('activities')
            ->whereIn('id', DB::table('activity_portal_persons')->distinct()->pluck('activity_id'))
            ->update(['publish_to_portal' => true]);
    }

    private function backfillPivot(): void
    {
        $query = DB::table('activities')->where('publish_to_portal', true);
        $total = $query->count();

        if ($total === 0) {
            return;
        }

        Log::info("Backfilling activity_portal_persons for {$total} activities");

        $query->orderBy('id')->chunk(200, function ($activities) {
            foreach ($activities as $row) {
                $activity = Activity::withoutGlobalScopes()->find($row->id);

                if (! $activity) {
                    continue;
                }

                $personIds = $activity->getPatientsFromActivity()->pluck('id')->all();

                if (empty($personIds)) {
                    continue;
                }

                $inserts = array_map(fn (int $pid) => [
                    'activity_id' => $activity->id,
                    'person_id'   => $pid,
                    'created_at'  => $activity->created_at ?? now(),
                    'updated_at'  => now(),
                ], $personIds);

                DB::table('activity_portal_persons')->insertOrIgnore($inserts);
            }
        });
    }
};
