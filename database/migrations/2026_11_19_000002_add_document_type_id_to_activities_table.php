<?php

use App\Models\DocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds activities.document_type_id and backfills it for existing FILE activities, replacing
 * additional->document_type (tickets/portaal/01-document-type.md). The column stays nullable:
 * it is only meaningful for FILE activities, and "required" is enforced at the application layer
 * (ActivityController) rather than as a blanket NOT NULL across every activity type (call, note, ...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedBigInteger('document_type_id')->nullable()->after('additional');
            $table->foreign('document_type_id')->references('id')->on('document_types')->restrictOnDelete();
        });

        $financialId = DocumentType::idForKey(DocumentType::KEY_FINANCIAL);
        $toMigrateId = DocumentType::idForKey(DocumentType::KEY_TO_MIGRATE);

        DB::table('activities')
            ->where('type', 'file')
            ->where('additional->document_type', 'order_confirmation')
            ->update(['document_type_id' => $financialId]);

        DB::table('activities')
            ->where('type', 'file')
            ->whereNull('document_type_id')
            ->update(['document_type_id' => $toMigrateId]);
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['document_type_id']);
            $table->dropColumn('document_type_id');
        });
    }
};
