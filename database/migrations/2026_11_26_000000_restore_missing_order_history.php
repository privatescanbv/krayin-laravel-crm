<?php

use App\Services\Repair\OrderHistoryRestorer;
use Illuminate\Database\Migrations\Migration;

/**
 * Restores order tasks, mails and calls that the first SugarCRM import run missed.
 * Needs storage/app/herstel/order-activiteiten.jsonl.gz when such orders exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(OrderHistoryRestorer::class)->restore();
    }

    public function down(): void
    {
        app(OrderHistoryRestorer::class)->revert();
    }
};
