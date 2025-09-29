<?php

use App\Models\Resource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('shifts', 'clinic_id')) {
            Schema::table('shifts', function (Blueprint $table) {
                $table->unsignedBigInteger('clinic_id')->nullable()->after('id');
            });

            // Backfill clinic_id from resource's clinic
            DB::table('shifts')->orderBy('id')->chunkById(1000, function ($rows) {
                foreach ($rows as $row) {
                    if (! $row->resource_id) {
                        continue;
                    }

                    $clinicId = DB::table('resources')->where('id', $row->resource_id)->value('clinic_id');

                    if ($clinicId) {
                        DB::table('shifts')->where('id', $row->id)->update(['clinic_id' => $clinicId]);
                    }
                }
            });

            Schema::table('shifts', function (Blueprint $table) {
                $table->unsignedBigInteger('clinic_id')->nullable(false)->change();
                $table->foreign('clinic_id')->references('id')->on('clinics')->onDelete('cascade');
                $table->index(['clinic_id', 'resource_id', 'starts_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shifts', 'clinic_id')) {
            Schema::table('shifts', function (Blueprint $table) {
                $table->dropForeign(['clinic_id']);
                $table->dropIndex(['clinic_id', 'resource_id', 'starts_at']);
                $table->dropColumn('clinic_id');
            });
        }
    }
};

