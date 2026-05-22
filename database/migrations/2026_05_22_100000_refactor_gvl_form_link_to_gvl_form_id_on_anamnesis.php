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
            $table->string('gvl_form_id')->nullable()->after('person_id');
        });

        DB::table('anamnesis')
            ->whereNotNull('gvl_form_link')
            ->lazyById()
            ->each(function (object $anamnesis) {
                if (preg_match('#forms/(\d+)(?:/step|/|$)#', $anamnesis->gvl_form_link, $matches)) {
                    DB::table('anamnesis')
                        ->where('id', $anamnesis->id)
                        ->update(['gvl_form_id' => $matches[1]]);
                }
            });

        Schema::table('anamnesis', function (Blueprint $table) {
            $table->dropColumn('gvl_form_link');
        });
    }

    public function down(): void
    {
        Schema::table('anamnesis', function (Blueprint $table) {
            $table->string('gvl_form_link')->nullable()->after('person_id');
        });

        $baseUrl = rtrim(config('services.portal.patient.web_url'), '/');

        DB::table('anamnesis')
            ->whereNotNull('gvl_form_id')
            ->where('gvl_form_id', '!=', '')
            ->lazyById()
            ->each(function (object $anamnesis) use ($baseUrl) {
                DB::table('anamnesis')
                    ->where('id', $anamnesis->id)
                    ->update([
                        'gvl_form_link' => $baseUrl.'/patient/forms/'.$anamnesis->gvl_form_id.'/step/1',
                    ]);
            });

        Schema::table('anamnesis', function (Blueprint $table) {
            $table->dropColumn('gvl_form_id');
        });
    }
};
