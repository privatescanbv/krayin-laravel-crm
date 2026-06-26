<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->string('mailbox_key')->nullable()->after('folder_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropIndex(['mailbox_key']);
            $table->dropColumn('mailbox_key');
        });
    }
};
