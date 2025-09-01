<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id')->nullable(); // Optional field
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['group_id']);
            }
            $table->dropColumn('group_id');
        });
    }
};
