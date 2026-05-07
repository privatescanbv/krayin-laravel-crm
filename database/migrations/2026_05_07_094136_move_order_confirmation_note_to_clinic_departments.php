<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_departments', function (Blueprint $table) {
            $table->text('order_confirmation_note')->nullable()->after('description');
        });

        // Copy existing note from each clinic to all its departments.
        DB::table('clinics')
            ->whereNotNull('order_confirmation_note')
            ->where('order_confirmation_note', '!=', '')
            ->get(['id', 'order_confirmation_note'])
            ->each(function ($clinic) {
                DB::table('clinic_departments')
                    ->where('clinic_id', $clinic->id)
                    ->update(['order_confirmation_note' => $clinic->order_confirmation_note]);
            });

        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn('order_confirmation_note');
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->text('order_confirmation_note')->nullable();
        });

        // Restore: copy the first department's note back to the clinic.
        DB::table('clinic_departments')
            ->whereNotNull('order_confirmation_note')
            ->where('order_confirmation_note', '!=', '')
            ->orderBy('id')
            ->get(['clinic_id', 'order_confirmation_note'])
            ->unique('clinic_id')
            ->each(function ($dept) {
                DB::table('clinics')
                    ->where('id', $dept->clinic_id)
                    ->update(['order_confirmation_note' => $dept->order_confirmation_note]);
            });

        Schema::table('clinic_departments', function (Blueprint $table) {
            $table->dropColumn('order_confirmation_note');
        });
    }
};
