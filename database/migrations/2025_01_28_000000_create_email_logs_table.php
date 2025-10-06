<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sync_type')->default('graph'); // 'graph', 'imap', etc.
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('processed_count')->default(0);
            $table->integer('error_count')->default(0);
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable(); // Additional sync metadata
            $table->timestamps();

            $table->index(['sync_type', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('email_logs');
    }
};