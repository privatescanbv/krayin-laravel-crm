<?php

use App\Helpers\AuditTrailMigrationHelper;
use App\Models\Address;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create addresses table (2025_07_07_124439)
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->string('street')->nullable();
            $table->string('house_number');
            $table->string('postal_code');
            $table->string('house_number_suffix')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();

            // Foreign key for Lead
            $table->unsignedInteger('lead_id')->nullable();
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');

            // Foreign key for Person
            $table->unsignedInteger('person_id')->nullable();
            $table->foreign('person_id')->references('id')->on('persons')->onDelete('cascade');

            $table->timestamps();

            // Add audit trail columns
            AuditTrailMigrationHelper::addAuditTrailColumns($table);
        });

        // Add organization_id (2025_07_23_000000)
        Schema::table('addresses', function (Blueprint $table) {
            $table->unsignedInteger('organization_id')->nullable()->after('person_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
        });

        // Migrate organization addresses data (2025_07_23_000002)
        $this->migrateOrganizationAddresses();
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }

    private function migrateOrganizationAddresses(): void
    {
        // Migrate data from the organizations.address column
        $organizationsWithAddress = DB::table('organizations')
            ->whereNotNull('address')
            ->get();

        foreach ($organizationsWithAddress as $organization) {
            $addressData = json_decode($organization->address, true);

            if ($addressData && is_array($addressData)) {
                Address::create([
                    'organization_id'     => $organization->id,
                    'street'              => $addressData['street'] ?? null,
                    'house_number'        => $addressData['house_number'] ?? null,
                    'postal_code'         => $addressData['postal_code'] ?? null,
                    'house_number_suffix' => $addressData['house_number_suffix'] ?? null,
                    'state'               => $addressData['state'] ?? null,
                    'city'                => $addressData['city'] ?? null,
                    'country'             => $addressData['country'] ?? 'Nederland',
                ]);
            }
        }

        // Migrate data from attribute_values (if any exists)
        $addressAttribute = DB::table('attributes')->where([
            'code'        => 'address',
            'entity_type' => 'organizations',
        ])->first();

        if ($addressAttribute) {
            $addressValues = DB::table('attribute_values')
                ->where('attribute_id', $addressAttribute->id)
                ->where('entity_type', 'organizations')
                ->get();

            foreach ($addressValues as $addressValue) {
                // Skip if we already created an address for this organization
                $existingAddress = Address::where('organization_id', $addressValue->entity_id)->first();
                if ($existingAddress) {
                    continue;
                }

                $addressData = json_decode($addressValue->json_value, true);

                if ($addressData && is_array($addressData)) {
                    Address::create([
                        'organization_id'     => $addressValue->entity_id,
                        'street'              => $addressData['street'] ?? null,
                        'house_number'        => $addressData['house_number'] ?? null,
                        'postal_code'         => $addressData['postal_code'] ?? null,
                        'house_number_suffix' => $addressData['house_number_suffix'] ?? null,
                        'state'               => $addressData['state'] ?? null,
                        'city'                => $addressData['city'] ?? null,
                        'country'             => $addressData['country'] ?? 'Nederland',
                    ]);
                }
            }
        }
    }
};