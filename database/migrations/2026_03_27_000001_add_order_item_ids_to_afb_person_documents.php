<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('afb_person_documents', function (Blueprint $table) {
            $table->json('order_item_ids')->nullable()->after('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('afb_person_documents', function (Blueprint $table) {
            $table->dropColumn('order_item_ids');
        });
    }
};
