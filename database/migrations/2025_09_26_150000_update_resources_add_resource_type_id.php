<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            if (!Schema::hasColumn('resources', 'resource_type_id')) {
                $table->unsignedBigInteger('resource_type_id')->after('id');
                $table->foreign('resource_type_id')->references('id')->on('resource_types')->onDelete('cascade');
            }

            if (Schema::hasColumn('resources', 'type')) {
                $table->dropColumn('type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            if (!Schema::hasColumn('resources', 'type')) {
                $table->string('type')->after('id');
            }

            if (Schema::hasColumn('resources', 'resource_type_id')) {
                $table->dropForeign(['resource_type_id']);
                $table->dropColumn('resource_type_id');
            }
        });
    }
};

