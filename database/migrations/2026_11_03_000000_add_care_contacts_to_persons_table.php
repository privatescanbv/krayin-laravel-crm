<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $columns = [
        'general_practitioner_name',
        'general_practitioner_practice',
        'general_practitioner_phone',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
    ];

    public function up(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            foreach ($this->columns as $column) {
                $table->string($column)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->dropColumn($this->columns);
        });
    }
};
