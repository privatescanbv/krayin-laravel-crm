<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedBigInteger('person_id')->nullable()->after('clinic_id');
        });

        // Data migration: copy person_id from person_activities to activities
        // for activities that have no other primary entity FK and exactly belong to a person.
        DB::statement('
            UPDATE activities
            SET person_id = (
                SELECT person_id FROM person_activities
                WHERE activity_id = activities.id
                LIMIT 1
            )
            WHERE person_id IS NULL
              AND lead_id IS NULL
              AND sales_lead_id IS NULL
              AND order_id IS NULL
              AND clinic_id IS NULL
              AND EXISTS (SELECT 1 FROM person_activities WHERE activity_id = activities.id)
        ');

        // Remove the migrated rows from person_activities (they're now covered by the FK)
        DB::statement('
            DELETE FROM person_activities
            WHERE activity_id IN (
                SELECT id FROM activities WHERE person_id IS NOT NULL
            )
        ');
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('person_id');
        });
    }
};
