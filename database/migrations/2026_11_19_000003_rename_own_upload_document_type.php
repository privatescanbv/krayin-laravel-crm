<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_types')->where('key', 'own_upload')->update(['name' => 'Extern aangeleverd']);
    }

    public function down(): void
    {
        DB::table('document_types')->where('key', 'own_upload')->update(['name' => 'Door mij aangeleverd']);
    }
};
