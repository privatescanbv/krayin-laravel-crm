<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anamnesis', function (Blueprint $table) {
            $table->string('gvl_form_status')->nullable()->after('gvl_form_id');
        });

        DB::table('anamnesis')
            ->whereNotNull('gvl_form_id')
            ->whereNull('gvl_form_status')
            ->update(['gvl_form_status' => 'new']);
    }

    public function down(): void
    {
        Schema::table('anamnesis', function (Blueprint $table) {
            $table->dropColumn('gvl_form_status');
        });
    }
};
