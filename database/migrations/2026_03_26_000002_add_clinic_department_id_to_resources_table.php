<?php

use App\Models\ClinicDepartment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->unsignedBigInteger('clinic_department_id')->nullable()->after('clinic_id');
            $table->foreign('clinic_department_id')->references('id')->on('clinic_departments')->onDelete('set null');
        });

        // Populate existing rows: find the "Standaard" department for each resource's clinic
        $resources = DB::table('resources')->whereNotNull('clinic_id')->get(['id', 'clinic_id']);

        foreach ($resources as $resource) {
            $dept = ClinicDepartment::where('clinic_id', $resource->clinic_id)
                ->where('name', 'Standaard')
                ->first();

            if ($dept) {
                DB::table('resources')
                    ->where('id', $resource->id)
                    ->update(['clinic_department_id' => $dept->id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->dropForeign(['clinic_department_id']);
            $table->dropColumn('clinic_department_id');
        });
    }
};
