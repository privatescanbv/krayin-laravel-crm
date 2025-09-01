<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // First, remove any existing duplicates
        $this->removeDuplicateAnamnesis();

        // Then add the unique constraint
        Schema::table('anamnesis', function (Blueprint $table) {
            $table->unique(['lead_id', 'person_id'], 'anamnesis_lead_person_unique');
        });
    }

    public function down(): void
    {
        Schema::table('anamnesis', function (Blueprint $table) {
            $table->dropUnique('anamnesis_lead_person_unique');
        });
    }

    /**
     * Remove duplicate anamnesis records, keeping the newest one for each lead_id + person_id combination.
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

                // Log the cleanup
                Log::info('Removed duplicate anamnesis record', [
                    'id'         => $record->id,
                    'lead_id'    => $record->lead_id,
                    'person_id'  => $record->person_id,
                    'created_at' => $record->created_at,
                ]);
            }
        }
    }
};
