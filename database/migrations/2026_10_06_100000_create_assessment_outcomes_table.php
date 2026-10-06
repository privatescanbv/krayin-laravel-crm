<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_outcomes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('Stored in salesleads.assessment_outcome; immutable');
            $table->string('label', 100);
            $table->boolean('is_surgery_advice')->default(true);
            $table->integer('sort_order')->default(0);
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
        });

        // Former App\Enums\AssessmentOutcome cases.
        $rows = [
            ['pted_1', 'PTED 1 niv.', true],
            ['pted_2', 'PTED 2 niv.', true],
            ['micro_1', 'Mikro 1 niv.', true],
            ['micro_2', 'Mikro 2 niv.', true],
            ['micro_3', 'Mikro 3 niv.', true],
            ['micro_4', 'Mikro 4 niv.', true],
            ['acdf_1', 'ACDF 1 niv.', true],
            ['acdf_2', 'ACDF 2 niv.', true],
            ['tlif_1', 'TLIF 1 niv.', true],
            ['tlif_2', 'TLIF 2 niv.', true],
            ['geen_op_indicatie', 'Geen OP indicatie', false],
            ['injecties_infiltraties', 'Injecties/Infiltraties', false],
        ];

        DB::table('assessment_outcomes')->insert(array_map(fn ($row, $i) => [
            'code'              => $row[0],
            'label'             => $row[1],
            'is_surgery_advice' => $row[2],
            'sort_order'        => $i + 1,
            'created_at'        => now(),
            'updated_at'        => now(),
        ], $rows, array_keys($rows)));
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_outcomes');
    }
};
