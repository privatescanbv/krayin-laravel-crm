<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inkoop_invoices', function (Blueprint $table) {
            $table->unsignedInteger('activity_id')->nullable()->after('clinic_id');
            $table->foreign('activity_id')->references('id')->on('activities')->nullOnDelete();
        });

        // Backfill: the upload creates the file activity right after the invoice, titled after the original filename.
        $claimed = [];

        DB::table('inkoop_invoices')->whereNotNull('name')->orderBy('id')->get()->each(function ($invoice) use (&$claimed) {
            $activityId = DB::table('activities')
                ->where('clinic_id', $invoice->clinic_id)
                ->where('type', 'file')
                ->where('title', 'Inkoop factuur: '.$invoice->name)
                ->where('created_at', '>=', $invoice->created_at)
                ->whereNotIn('id', $claimed)
                ->orderBy('created_at')
                ->orderBy('id')
                ->value('id');

            if ($activityId) {
                $claimed[] = $activityId;

                DB::table('inkoop_invoices')->where('id', $invoice->id)->update(['activity_id' => $activityId]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('inkoop_invoices', function (Blueprint $table) {
            $table->dropForeign(['activity_id']);
            $table->dropColumn('activity_id');
        });
    }
};
