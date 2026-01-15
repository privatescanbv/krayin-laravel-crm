<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->removeDuplicateAnamnesis();

        Schema::table('anamnesis', function (Blueprint $table) {
            $table->unique(['sales_id', 'person_id'], 'anamnesis_sales_person_unique');
        });
    }

    public function down(): void
    {
        Schema::table('anamnesis', function (Blueprint $table) {
            $table->dropUnique('anamnesis_sales_person_unique');
        });
    }

    /**
     * Remove duplicate anamnesis records, keeping the newest one for each sales_id + person_id combination.
     */
    private function removeDuplicateAnamnesis(): void
    {
        $duplicates = DB::select('
            SELECT sales_id, person_id, COUNT(*) as count
            FROM anamnesis
            WHERE sales_id IS NOT NULL AND person_id IS NOT NULL
            GROUP BY sales_id, person_id
            HAVING COUNT(*) > 1
        ');

        foreach ($duplicates as $duplicate) {
            $records = DB::table('anamnesis')
                ->where('sales_id', $duplicate->sales_id)
                ->where('person_id', $duplicate->person_id)
                ->orderBy('updated_at', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            $recordsToDelete = $records->slice(1);

            foreach ($recordsToDelete as $record) {
                DB::table('anamnesis')->where('id', $record->id)->delete();

                Log::info('Removed duplicate anamnesis record (sales-person)', [
                    'id'        => $record->id,
                    'sales_id'  => $record->sales_id,
                    'person_id' => $record->person_id,
                ]);
            }
        }
    }
};
