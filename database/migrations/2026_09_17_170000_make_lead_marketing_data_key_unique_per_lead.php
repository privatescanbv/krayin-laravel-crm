<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A lead merge before the replaceMarketingData() fix could leave two rows for the same
        // (lead_id, key) - e.g. two campaign_id rows, with whichever had the higher id winning
        // by accident wherever this table is read. Keep the newest (highest id) row per group and
        // drop the rest before the unique index can be added, or it fails on existing data.
        // Subquery form (not a MySQL multi-table DELETE JOIN) so it also runs on the SQLite
        // in-memory database the test suite migrates fresh. The inner select is wrapped in an
        // extra derived table because MySQL refuses "delete from t where id in (select ... from t)"
        // (error 1093) unless the subquery result is materialized first.
        DB::table('lead_marketing_data')
            ->whereNotIn('id', function ($query) {
                $query->select('id')->fromSub(function ($query) {
                    $query->selectRaw('MAX(id) as id')
                        ->from('lead_marketing_data')
                        ->groupBy('lead_id', 'key');
                }, 'ids_to_keep');
            })
            ->delete();

        // Add the unique index before dropping the old plain one: the FK on lead_id is satisfied by
        // whichever (lead_id, ...) index exists, and MySQL refuses to drop the last one covering it.
        Schema::table('lead_marketing_data', function (Blueprint $table) {
            $table->unique(['lead_id', 'key']);
        });

        Schema::table('lead_marketing_data', function (Blueprint $table) {
            $table->dropIndex(['lead_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::table('lead_marketing_data', function (Blueprint $table) {
            $table->index(['lead_id', 'key']);
        });

        Schema::table('lead_marketing_data', function (Blueprint $table) {
            $table->dropUnique(['lead_id', 'key']);
        });
    }
};
