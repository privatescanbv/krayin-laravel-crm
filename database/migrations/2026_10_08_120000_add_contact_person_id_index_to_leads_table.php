<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leads are looked up by contact person for the customer history and the AI person
 * scope; without this index every lookup scans the whole leads table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('leads', 'leads_contact_person_id_index')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->index('contact_person_id', 'leads_contact_person_id_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('leads', 'leads_contact_person_id_index')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_contact_person_id_index');
        });
    }
};
