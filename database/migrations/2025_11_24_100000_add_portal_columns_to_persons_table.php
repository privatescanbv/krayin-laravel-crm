<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->string('keycloak_user_id')->nullable()->after('user_id');
            $table->boolean('is_active')->default(false)->after('keycloak_user_id');
            $table->text('password')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->dropColumn(['keycloak_user_id', 'is_active', 'password']);
        });
    }
};
