<?php

use App\Enums\FormStatus;
use App\Services\FormService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        $service = app(FormService::class);

        DB::table('anamnesis')
            ->whereNotNull('gvl_form_id')
            ->where(function ($q) {
                $q->whereNull('gvl_form_status')
                    ->orWhere('gvl_form_status', '!=', FormStatus::Completed->value);
            })
            ->orderBy('id')
            ->chunkById(50, function ($rows) use ($service) {
                foreach ($rows as $row) {
                    try {
                        $status = $service->getFormStatusAsString((int) $row->gvl_form_id);

                        DB::table('anamnesis')
                            ->where('id', $row->id)
                            ->update(['gvl_form_status' => $status->value]);

                        Log::info('Backfill gvl_form_status', [
                            'anamnesis_id' => $row->id,
                            'form_id'      => $row->gvl_form_id,
                            'status'       => $status->value,
                        ]);
                    } catch (Throwable $e) {
                        Log::warning('Backfill gvl_form_status: kon status niet ophalen', [
                            'anamnesis_id' => $row->id,
                            'form_id'      => $row->gvl_form_id,
                            'error'        => $e->getMessage(),
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Intentionally empty — reverting API-fetched data is not meaningful.
    }
};
