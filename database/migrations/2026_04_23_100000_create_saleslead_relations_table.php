<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saleslead_relations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_saleslead_id');
            $table->unsignedBigInteger('target_saleslead_id');
            $table->string('relation_type')->default('preventie_referral');
            $table->timestamps();

            $table->foreign('source_saleslead_id')->references('id')->on('salesleads')->onDelete('cascade');
            $table->foreign('target_saleslead_id')->references('id')->on('salesleads')->onDelete('cascade');

            $table->unique(['source_saleslead_id', 'target_saleslead_id', 'relation_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saleslead_relations');
    }
};
