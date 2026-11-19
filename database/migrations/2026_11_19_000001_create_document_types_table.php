<?php

use App\Helpers\AuditTrailMigrationHelper;
use App\Models\DocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('key', 50)->nullable()->unique()->comment('Immutable; only set for system types recognized in code');
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('show_in_clinic_portal')->default(false);
            $table->boolean('show_in_patient_portal')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            AuditTrailMigrationHelper::addAuditTrailColumns($table);
        });

        // Startset per tickets/portaal/01-document-type.md.
        $rows = [
            ['key' => DocumentType::KEY_TO_MIGRATE, 'name' => 'Te migreren', 'clinic' => true, 'patient' => true],
            ['key' => null, 'name' => 'Verslagen', 'clinic' => true, 'patient' => true],
            ['key' => DocumentType::KEY_PRESCRIPTION, 'name' => 'Recept', 'clinic' => true, 'patient' => true],
            ['key' => null, 'name' => 'Beelden', 'clinic' => true, 'patient' => true],
            ['key' => null, 'name' => 'Vragenlijsten', 'clinic' => true, 'patient' => true],
            ['key' => null, 'name' => 'Informatie', 'clinic' => true, 'patient' => true],
            ['key' => DocumentType::KEY_FINANCIAL, 'name' => 'Financieel', 'clinic' => false, 'patient' => true],
            ['key' => DocumentType::KEY_OWN_UPLOAD, 'name' => 'Door mij aangeleverd', 'clinic' => true, 'patient' => true],
        ];

        DB::table('document_types')->insert(array_map(fn ($row, $i) => [
            'key'                     => $row['key'],
            'name'                    => $row['name'],
            'show_in_clinic_portal'   => $row['clinic'],
            'show_in_patient_portal'  => $row['patient'],
            'sort_order'              => $i + 1,
            'created_at'              => now(),
            'updated_at'              => now(),
        ], $rows, array_keys($rows)));
    }

    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};
