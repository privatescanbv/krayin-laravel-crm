<?php

use App\Services\Repair\MissingOrderRestorer;
use Illuminate\Database\Migrations\Migration;

/**
 * Restores SugarCRM orders that were skipped because they had no usable lead during the import.
 * Needs storage/app/herstel/ontbrekende-orders.json.gz and ontbrekende-orders-activiteiten.jsonl.gz
 * when imported orders exist and one of these orders is missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(MissingOrderRestorer::class)->restore();
    }

    public function down(): void
    {
        app(MissingOrderRestorer::class)->revert();
    }
};
