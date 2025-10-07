<?php

namespace Webkul\Installer\Database\Seeders\Attribute;

use App\Enums\LeadAttributeKeys;
use App\Enums\PersonAttributeKeys;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Webkul\Attribute\Models\Attribute;

class AttributeSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Note: code needs to be unique over alle entity types!
     * @param array $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        $numberOfRecords = DB::table('attributes')->count();
        // Check if attributes already exist to prevent duplicate key errors
        if ($numberOfRecords > 0 && $numberOfRecords <= 12) {
            // 12 is strange, but I can't find we is adding them
            DB::table('attributes')->delete();
        } elseif (DB::table('attributes')->count() > 12) {
            return;
        }
        $now = Carbon::now();

        $defaultLocale = $parameters['locale'] ?? config('app.locale');

        $personSortNumber = 0;
        DB::table('attributes')->insert([
            /**
             * Persons Attributes
             */
             [
                'code' => PersonAttributeKeys::USER_ID->value,
                'name' => 'Contactpersoon',
                'type' => 'lookup',
                'entity_type' => 'persons',
                'lookup_type' => 'users',
                'validation' => null,
                'sort_order' => ++$personSortNumber,
                'is_required' => '0',
                'is_unique' => '0',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ], [
                'code' => PersonAttributeKeys::ORGANIZATION_ID->value,
                'name' => 'Organisatie',
                'type' => 'lookup',
                'entity_type' => 'persons',
                'lookup_type' => 'organizations',
                'validation' => null,
                'sort_order' => ++$personSortNumber,
                'is_required' => '0',
                'is_unique' => '0',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            /**
             * Organizations Attributes
             */
            [
                'code' => 'name',
                'name' => 'Naam',
                'type' => 'text',
                'entity_type' => 'organizations',
                'lookup_type' => null,
                'validation' => null,
                'sort_order' => '1',
                'is_required' => '1',
                'is_unique' => '1',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ], [
                'code' => 'user_id',
                'name' => 'Contactpersoon',
                'type' => 'lookup',
                'entity_type' => 'organizations',
                'lookup_type' => 'users',
                'validation' => null,
                'sort_order' => '2',
                'is_required' => '0',
                'is_unique' => '0',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

    }
}
