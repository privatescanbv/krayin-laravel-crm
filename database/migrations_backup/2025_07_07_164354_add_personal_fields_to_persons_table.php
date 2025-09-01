<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->string('salutation')->nullable()->after('name');
            $table->string('first_name')->nullable()->after('salutation');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('lastname_prefix')->nullable()->after('last_name');
            $table->string('married_name')->nullable()->after('lastname_prefix');
            $table->string('married_name_prefix')->nullable()->after('married_name');
            $table->string('initials')->nullable()->after('married_name_prefix');
            $table->date('date_of_birth')->nullable()->after('initials');
        });
    }

    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->dropColumn([
                'salutation',
                'first_name',
                'last_name',
                'lastname_prefix',
                'married_name',
                'married_name_prefix',
                'initials',
                'date_of_birth',
            ]);
        });
    }
};
