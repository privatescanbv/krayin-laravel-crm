<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add address_id columns to entity tables
        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedBigInteger('address_id')->nullable()->after('contact_person_id');
        });

        Schema::table('persons', function (Blueprint $table) {
            $table->unsignedBigInteger('address_id')->nullable()->after('national_identification_number');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->unsignedBigInteger('address_id')->nullable()->after('updated_by');
        });

        // Step 2: Migrate existing data - set address_id on entities from addresses table
        // Using database-agnostic approach (works with both MySQL and SQLite)
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite-compatible syntax
            DB::statement('
                UPDATE leads
                SET address_id = (
                    SELECT id FROM addresses
                    WHERE addresses.lead_id = leads.id
                    LIMIT 1
                )
            ');

            DB::statement('
                UPDATE persons
                SET address_id = (
                    SELECT id FROM addresses
                    WHERE addresses.person_id = persons.id
                    LIMIT 1
                )
            ');

            DB::statement('
                UPDATE organizations
                SET address_id = (
                    SELECT id FROM addresses
                    WHERE addresses.organization_id = organizations.id
                    LIMIT 1
                )
            ');
        } else {
            // MySQL/PostgreSQL syntax
            DB::statement('
                UPDATE leads
                SET address_id = (
                    SELECT id FROM addresses
                    WHERE addresses.lead_id = leads.id
                    LIMIT 1
                )
                WHERE EXISTS (
                    SELECT 1 FROM addresses
                    WHERE addresses.lead_id = leads.id
                )
            ');

            DB::statement('
                UPDATE persons
                SET address_id = (
                    SELECT id FROM addresses
                    WHERE addresses.person_id = persons.id
                    LIMIT 1
                )
                WHERE EXISTS (
                    SELECT 1 FROM addresses
                    WHERE addresses.person_id = persons.id
                )
            ');

            DB::statement('
                UPDATE organizations
                SET address_id = (
                    SELECT id FROM addresses
                    WHERE addresses.organization_id = organizations.id
                    LIMIT 1
                )
                WHERE EXISTS (
                    SELECT 1 FROM addresses
                    WHERE addresses.organization_id = organizations.id
                )
            ');
        }

        // Step 3: Add foreign key constraints to entity tables (skip for SQLite as it doesn't enforce them well)
        if ($driver !== 'sqlite') {
            Schema::table('leads', function (Blueprint $table) {
                $table->foreign('address_id')->references('id')->on('addresses')->onDelete('set null');
            });

            Schema::table('persons', function (Blueprint $table) {
                $table->foreign('address_id')->references('id')->on('addresses')->onDelete('set null');
            });

            Schema::table('organizations', function (Blueprint $table) {
                $table->foreign('address_id')->references('id')->on('addresses')->onDelete('set null');
            });
        }

        // Step 4: Clear the old foreign key columns in addresses (set to null before dropping)
        DB::statement('UPDATE addresses SET lead_id = NULL, person_id = NULL, organization_id = NULL');

        // Step 5: Drop old foreign key constraints and columns from addresses table
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropForeign(['lead_id']);
            $table->dropForeign(['person_id']);
            $table->dropForeign(['organization_id']);
            $table->dropColumn(['lead_id', 'person_id', 'organization_id']);
        });
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        // Step 1: Re-add the old columns to addresses table
        Schema::table('addresses', function (Blueprint $table) {
            $table->unsignedInteger('lead_id')->nullable();
            $table->unsignedInteger('person_id')->nullable();
            $table->unsignedInteger('organization_id')->nullable();
        });

        // Step 2: Migrate data back - set entity IDs on addresses from entity tables
        if ($driver === 'sqlite') {
            // SQLite-compatible syntax
            DB::statement('
                UPDATE addresses
                SET lead_id = (
                    SELECT id FROM leads
                    WHERE leads.address_id = addresses.id
                    LIMIT 1
                )
            ');

            DB::statement('
                UPDATE addresses
                SET person_id = (
                    SELECT id FROM persons
                    WHERE persons.address_id = addresses.id
                    LIMIT 1
                )
            ');

            DB::statement('
                UPDATE addresses
                SET organization_id = (
                    SELECT id FROM organizations
                    WHERE organizations.address_id = addresses.id
                    LIMIT 1
                )
            ');
        } else {
            // MySQL syntax
            DB::statement('
                UPDATE addresses
                INNER JOIN leads ON leads.address_id = addresses.id
                SET addresses.lead_id = leads.id
            ');

            DB::statement('
                UPDATE addresses
                INNER JOIN persons ON persons.address_id = addresses.id
                SET addresses.person_id = persons.id
            ');

            DB::statement('
                UPDATE addresses
                INNER JOIN organizations ON organizations.address_id = addresses.id
                SET addresses.organization_id = organizations.id
            ');
        }

        // Step 3: Add back foreign key constraints to addresses (skip for SQLite)
        if ($driver !== 'sqlite') {
            Schema::table('addresses', function (Blueprint $table) {
                $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');
                $table->foreign('person_id')->references('id')->on('persons')->onDelete('cascade');
                $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            });

            // Step 4: Drop foreign key constraints from entity tables
            Schema::table('leads', function (Blueprint $table) {
                $table->dropForeign(['address_id']);
            });

            Schema::table('persons', function (Blueprint $table) {
                $table->dropForeign(['address_id']);
            });

            Schema::table('organizations', function (Blueprint $table) {
                $table->dropForeign(['address_id']);
            });
        }

        // Step 5: Drop address_id columns from entity tables
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('address_id');
        });

        Schema::table('persons', function (Blueprint $table) {
            $table->dropColumn('address_id');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('address_id');
        });
    }
};
