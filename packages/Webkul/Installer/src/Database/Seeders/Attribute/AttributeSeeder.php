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
             * Leads Attributes
             */
           [
                'code' => 'lead_pipeline_id',
                'name' => trans('installer::app.seeders.attributes.leads.pipeline', [], $defaultLocale),
                'type' => 'lookup',
                'entity_type' => 'leads',
                'lookup_type' => 'lead_pipelines',
                'validation' => null,
                'sort_order' => '8',
                'is_required' => '1',
                'is_unique' => '0',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ], [
                'code' => 'lead_pipeline_stage_id',
                'name' => trans('installer::app.seeders.attributes.leads.stage', [], $defaultLocale),
                'type' => 'lookup',
                'entity_type' => 'leads',
                'lookup_type' => 'lead_pipeline_stages',
                'validation' => null,
                'sort_order' => '9',
                'is_required' => '1',
                'is_unique' => '0',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ],
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

            /**
             * Quotes Attributes
             */
            [
                'code' => 'user_id',
                'name' => 'Verkoop Eigenaar',
                'type' => 'select',
                'entity_type' => 'quotes',
                'lookup_type' => 'users',
                'validation' => null,
                'sort_order' => '1',
                'is_required' => '1',
                'is_unique' => '0',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ], [
                'code' => 'subject',
                'name' => 'Onderwerp',
                'type' => 'text',
                'entity_type' => 'quotes',
                'lookup_type' => null,
                'validation' => null,
                'sort_order' => '2',
                'is_required' => '1',
                'is_unique' => '0',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ], [
                'code' => 'description',
                'name' => 'Omschrijving',
                'type' => 'textarea',
                'entity_type' => 'quotes',
                'lookup_type' => null,
                'validation' => null,
                'sort_order' => '3',
                'is_required' => '0',
                'is_unique' => '0',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ], [
                'code' => 'billing_address',
                'name' => 'Facturatie Adres',
                'type' => 'address',
                'entity_type' => 'quotes',
                'lookup_type' => null,
                'validation' => null,
                'sort_order' => '4',
                'is_required' => '1',
                'is_unique' => '0',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ], [
                'code' => 'shipping_address',
                'name' => 'Verzend Adres',
                'type' => 'address',
                'entity_type' => 'quotes',
                'lookup_type' => null,
                'validation' => null,
                'sort_order' => '5',
                'is_required' => '0',
                'is_unique' => '0',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ], [
                'code' => 'discount_percent',
                'name' => 'Korting Percentage',
                'type' => 'text',
                'entity_type' => 'quotes',
                'lookup_type' => null,
                'validation' => 'decimal',
                'sort_order' => '6',
                'is_required' => '0',
                'is_unique' => '0',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ], [
                'code' => 'discount_amount',
                'name' => 'Korting Bedrag',
                'type' => 'price',
                'entity_type' => 'quotes',
                'lookup_type' => null,
                'validation' => 'decimal',
                'sort_order' => '7',
                'is_required' => '0',
                'is_unique' => '0',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ], [
                'code' => 'tax_amount',
                'name' => 'BTW Bedrag',
                'type' => 'price',
                'entity_type' => 'quotes',
                'lookup_type' => null,
                'validation' => 'decimal',
                'sort_order' => '8',
                'is_required' => '0',
                'is_unique' => '0',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ], [
                'code' => 'adjustment_amount',
                'name' =>' Aanpassing Bedrag',
                'type' => 'price',
                'entity_type' => 'quotes',
                'lookup_type' => null,
                'validation' => 'decimal',
                'sort_order' => '8',
                'is_required' => '0',
                'is_unique' => '0',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ], [
                'code' => 'sub_total',
                'name' => 'Sub Totaal',
                'type' => 'price',
                'entity_type' => 'quotes',
                'lookup_type' => null,
                'validation' => 'decimal',
                'sort_order' => '9',
                'is_required' => '1',
                'is_unique' => '0',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ], [
                'code' => 'grand_total',
                'name' => 'Totaal',
                'type' => 'price',
                'entity_type' => 'quotes',
                'lookup_type' => null,
                'validation' => 'decimal',
                'sort_order' => '11',
                'is_required' => '1',
                'is_unique' => '0',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ], [
                'code' => 'expired_at',
                'name' => 'Vervaldatum',
                'type' => 'date',
                'entity_type' => 'quotes',
                'lookup_type' => null,
                'validation' => null,
                'sort_order' => '12',
                'is_required' => '1',
                'is_unique' => '0',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ], [
                'code' => 'person_id',
                'name' => 'Persoon',
                'type' => 'lookup',
                'entity_type' => 'quotes',
                'lookup_type' => 'persons',
                'validation' => null,
                'sort_order' => '13',
                'is_required' => '1',
                'is_unique' => '0',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            /**
             * Warehouses Attributes
             */
            [
                'code' => 'name',
                'name' => 'Naam',
                'type' => 'text',
                'entity_type' => 'warehouses',
                'lookup_type' => null,
                'validation' => null,
                'sort_order' => '1',
                'is_required' => '1',
                'is_unique' => '0',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ], [
                'code' => 'description',
                'name' => 'Omschrijving',
                'type' => 'textarea',
                'entity_type' => 'warehouses',
                'lookup_type' => null,
                'validation' => null,
                'sort_order' => '2',
                'is_required' => '0',
                'is_unique' => '0',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ], [
                'code' => 'contact_name',
                'name' => 'Contact Naam',
                'type' => 'text',
                'entity_type' => 'warehouses',
                'lookup_type' => null,
                'validation' => null,
                'sort_order' => '3',
                'is_required' => '1',
                'is_unique' => '0',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ], [
                'code' => 'contact_emails',
                'name' => 'Contact E-mails',
                'type' => 'email',
                'entity_type' => 'warehouses',
                'lookup_type' => null,
                'validation' => null,
                'sort_order' => '4',
                'is_required' => '1',
                'is_unique' => '1',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ], [
                'code' => 'contact_numbers',
                'name' => 'Contact Telefoonnummers',
                'type' => 'phone',
                'entity_type' => 'warehouses',
                'lookup_type' => null,
                'validation' => 'numeric',
                'sort_order' => '5',
                'is_required' => '0',
                'is_unique' => '1',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ], [
                'code' => 'contact_address',
                'name' => 'Contact Adres',
                'type' => 'address',
                'entity_type' => 'warehouses',
                'lookup_type' => null,
                'validation' => null,
                'sort_order' => '6',
                'is_required' => '1',
                'is_unique' => '0',
                'quick_add' => '1',
                'is_user_defined' => '0',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

    }
}
