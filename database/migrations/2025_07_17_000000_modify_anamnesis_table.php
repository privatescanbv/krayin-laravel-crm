<?php

use App\Helpers\AuditTrailMigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create anamnesis table (2025_07_17_152123)
        Schema::create('anamnesis', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name', 255)->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at')->nullable();
            $table->text('description')->nullable();
            $table->tinyInteger('deleted')->default(0);
            $table->char('team_id', 36)->nullable();
            $table->char('team_set_id', 36)->nullable();
            $table->text('comment_clinic')->nullable();
            $table->integer('height')->nullable();
            $table->integer('weight')->nullable();
            $table->tinyInteger('metals')->nullable();
            $table->text('metals_notes')->nullable();
            $table->tinyInteger('medications')->nullable();
            $table->text('medications_notes')->nullable();
            $table->tinyInteger('glaucoma')->nullable();
            $table->text('glaucoma_notes')->nullable();
            $table->tinyInteger('claustrophobia')->nullable();
            $table->tinyInteger('dormicum')->nullable();
            $table->tinyInteger('heart_surgery')->nullable();
            $table->text('heart_surgery_notes')->nullable();
            $table->tinyInteger('implant')->nullable();
            $table->text('implant_notes')->nullable();
            $table->tinyInteger('surgeries')->nullable();
            $table->text('surgeries_notes')->nullable();
            $table->string('remarks', 255)->nullable();
            $table->tinyInteger('hereditary_heart')->nullable();
            $table->text('hereditary_heart_notes')->nullable();
            $table->tinyInteger('hereditary_vascular')->nullable();
            $table->text('hereditary_vascular_notes')->nullable();
            $table->tinyInteger('hereditary_tumors')->nullable();
            $table->text('hereditary_tumors_notes')->nullable();
            $table->tinyInteger('allergies')->nullable();
            $table->text('allergies_notes')->nullable();
            $table->tinyInteger('back_problems')->nullable();
            $table->text('back_problems_notes')->nullable();
            $table->tinyInteger('heart_problems')->nullable();
            $table->text('heart_problems_notes')->nullable();
            $table->tinyInteger('smoking')->nullable();
            $table->text('smoking_notes')->nullable();
            $table->tinyInteger('diabetes')->nullable();
            $table->text('diabetes_notes')->nullable();
            $table->tinyInteger('digestive_problems')->nullable();
            $table->text('digestive_problems_notes')->nullable();
            $table->text('heart_attack_risk')->nullable();
            $table->tinyInteger('active')->nullable();
            $table->text('advice_notes')->nullable();

            // Add spijsverteringsklachten fields (2025_08_26_000001)
            $table->boolean('spijsverteringsklachten')->default(false)->after('diabetes');
            $table->text('digestive_complaints_notes')->nullable()->after('spijsverteringsklachten');

            // Foreign keys
            $table->unsignedInteger('lead_id')->nullable();
            $table->unsignedInteger('person_id')->nullable();
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('set null');
            $table->foreign('person_id')->references('id')->on('persons')->onDelete('set null');
        });

        // Fix audit trail columns (2025_07_25_000004) - use correct unsignedInteger type
        Schema::table('anamnesis', function (Blueprint $table) {
            AuditTrailMigrationHelper::addAuditTrailColumns($table);
        });

        // Add unique constraint (2025_08_25_000001)
        Schema::table('anamnesis', function (Blueprint $table) {
            $table->unique(['lead_id', 'person_id'], 'anamnesis_lead_person_unique');
        });

        // Note: assigned_user_id is not added as it gets dropped in 2025_08_26_000002
    }

    public function down(): void
    {
        Schema::dropIfExists('anamnesis');
    }

    /**
     * Remove duplicate anamnesis records, keeping the newest one for each lead_id + person_id combination.
     * This is included from 2025_08_25_000001
     */
    private function removeDuplicateAnamnesis(): void
    {
        // Find all duplicate combinations
        $duplicates = DB::select('
            SELECT lead_id, person_id, COUNT(*) as count
            FROM anamnesis
            WHERE lead_id IS NOT NULL AND person_id IS NOT NULL
            GROUP BY lead_id, person_id
            HAVING COUNT(*) > 1
        ');

        foreach ($duplicates as $duplicate) {
            // Get all anamnesis records for this lead_id + person_id combination
            $records = DB::table('anamnesis')
                ->where('lead_id', $duplicate->lead_id)
                ->where('person_id', $duplicate->person_id)
                ->orderBy('updated_at', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            // Keep the first (newest) record, delete the rest
            $recordsToDelete = $records->slice(1);

            foreach ($recordsToDelete as $record) {
                DB::table('anamnesis')->where('id', $record->id)->delete();
            }
        }
    }
};