<?php

namespace Tests\Feature;

use App\Enums\LostReason;
use App\Enums\OrderItemStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\PipelineStage;
use App\Enums\ProductReports;
use App\Enums\PurchasePriceType;
use App\Models\Anamnesis;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PartnerProduct;
use App\Models\Resource;
use App\Models\ResourceOrderItem;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Product\Models\Product;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    // Default Sugar row label in fixtures; import requires a matching CRM product (name or external_id).
    Product::factory()->create(['name' => 'TB1 Business Class']);

    Config::set('database.connections.sugarcrm', [
        'driver'   => 'sqlite',
        'database' => ':memory:',
        'prefix'   => '',
    ]);

    $tables = [
        'leads_pcrm_salesorder_c',
        'pcrm_salesorow_contacts_c',
        'pcrm_salesoalesorderrow_c',
        'pcrm_salesorderrow_cstm',
        'pcrm_salesorderrow',
        'pcrm_salesorder_cstm',
        'pcrm_salesorder',
        'aos_products',
        'pcrm_salesoder_accounts_c',
        'accounts',
    ];

    foreach ($tables as $tbl) {
        if (Schema::connection('sugarcrm')->hasTable($tbl)) {
            Schema::connection('sugarcrm')->drop($tbl);
        }
    }

    Schema::connection('sugarcrm')->create('pcrm_salesorder', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('name')->nullable();
        $table->integer('order_num')->nullable();
        $table->double('amount')->nullable();
        $table->string('sales_stage')->nullable();
        $table->date('date_closed')->nullable();
        $table->date('datum_onderzoek_1')->nullable();
        $table->dateTime('date_entered')->nullable();
        $table->dateTime('date_modified')->nullable();
        $table->string('assigned_user_id')->nullable();
        $table->integer('deleted')->default(0);
    });

    Schema::connection('sugarcrm')->create('pcrm_salesorder_cstm', function (Blueprint $table) {
        $table->string('id_c')->primary();
        $table->string('reden_afvoeren_c')->nullable();
        $table->tinyInteger('op_een_factuur_c')->default(0);
        $table->string('d_wfl_status_c')->nullable();
        $table->string('aankomsttijd_c', 5)->nullable();
        $table->decimal('betaald_vooruit_c', 14, 6)->nullable();
        $table->decimal('betaald_kliniek_c', 14, 6)->nullable();
        $table->dateTime('datum_betaling_vr_c')->nullable();
        $table->decimal('openstaand_c', 14, 6)->nullable();
        $table->string('betaal_status_c')->nullable();
        $table->string('pin_contant_c')->nullable();
        $table->string('user_id_c')->nullable();
    });

    Schema::connection('sugarcrm')->create('pcrm_salesorderrow', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('name')->nullable();
        $table->double('sales_price')->nullable();
        $table->string('sales_stage')->nullable();
        $table->string('resource_type')->nullable();
        $table->dateTime('datum_onderzoek')->nullable();
        $table->integer('duration')->nullable();
        $table->string('pcrm_partnerresources_id_c')->nullable();
        $table->integer('deleted')->default(0);
        // Authoritative purchase price fields on the main row table (no _c suffix)
        $table->decimal('purchase_price', 14, 6)->nullable();
        $table->decimal('purchase_clinic', 14, 6)->nullable();
        $table->decimal('purchase_doctor', 14, 6)->nullable();
    });

    Schema::connection('sugarcrm')->create('pcrm_salesorderrow_cstm', function (Blueprint $table) {
        $table->string('id_c')->primary();
        $table->string('aos_products_id_c')->nullable();
        // cstm purchase components (clinic/total/rd live on main row table, not here)
        $table->decimal('purchase_other_c', 10, 2)->nullable();
        $table->decimal('purchase_cardio_c', 10, 2)->nullable();
        $table->decimal('purchase_radio_c', 10, 2)->nullable();
        // aflettering invoice amounts
        $table->decimal('inv_purchase_other_c', 10, 2)->nullable();
        $table->decimal('inv_purchase_cardio_c', 10, 2)->nullable();
        $table->decimal('inv_purchase_clinic_c', 10, 2)->nullable();
        $table->decimal('inv_purchase_radio_c', 10, 2)->nullable();
        $table->decimal('inv_purchase_doctor_c', 10, 2)->nullable();
        $table->decimal('inv_purchase_total_c', 10, 2)->nullable();
        // aflettering statuses
        $table->string('ink_other_status_c')->nullable();
        $table->string('ink_cardio_status_c')->nullable();
        $table->string('ink_clinic_status_c')->nullable();
        $table->string('ink_radio_status_c')->nullable();
        $table->string('ink_doctor_status_c')->nullable();
        $table->string('ink_total_status_c')->nullable();
        $table->text('afb_description_c')->nullable();
    });

    // Join table: order → rows
    Schema::connection('sugarcrm')->create('pcrm_salesoalesorderrow_c', function (Blueprint $table) {
        $table->string('pcrm_salesb9a7esorder_ida');
        $table->string('pcrm_sales509drderrow_idb');
    });

    // Join table: row → contacts
    Schema::connection('sugarcrm')->create('pcrm_salesorow_contacts_c', function (Blueprint $table) {
        $table->string('pcrm_sales80b3rderrow_idb');
        $table->string('pcrm_sales4bd9ontacts_ida');
        $table->integer('deleted')->default(0);
    });

    // Join table: lead → salesorder
    Schema::connection('sugarcrm')->create('leads_pcrm_salesorder_c', function (Blueprint $table) {
        $table->string('leads_p903eeads_ida')->nullable(); // Sugar lead UUID
        $table->string('leads_p5ae2rder_idb')->nullable(); // Sugar salesorder UUID
        $table->integer('deleted')->default(0);
    });

    // Sugar product catalog (used to resolve overridden row names back to original product names)
    Schema::connection('sugarcrm')->create('aos_products', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('name')->nullable();
        $table->integer('deleted')->default(0);
    });

    Schema::connection('sugarcrm')->create('accounts', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('name')->nullable();
        $table->string('billing_address_postalcode')->nullable();
        $table->string('billing_address_state')->nullable();
        $table->text('billing_address_street')->nullable();
        $table->string('billing_address_city')->nullable();
        $table->string('shipping_address_city')->nullable();
        $table->string('billing_address_country')->nullable();
        $table->integer('deleted')->default(0);
    });

    Schema::connection('sugarcrm')->create('accounts_cstm', function (Blueprint $table) {
        $table->string('id_c')->primary();
        $table->string('billing_huisnr_c')->nullable();
        $table->string('billing_huisnr_toevoeging_c')->nullable();
    });

    Schema::connection('sugarcrm')->create('pcrm_salesoder_accounts_c', function (Blueprint $table) {
        $table->string('pcrm_salesd0bfesorder_idb');
        $table->string('pcrm_sales697fccounts_ida');
        $table->integer('deleted')->default(0);
    });
});

// ─── helpers ────────────────────────────────────────────────────────────────

function insertSugarOrder(string $id, array $overrides = []): void
{
    // Fields belonging to pcrm_salesorder_cstm, not pcrm_salesorder
    $cstmKeys = [
        'reden_afvoeren_c',
        'op_een_factuur_c',
        'd_wfl_status_c',
        'aankomsttijd_c',
        'betaald_vooruit_c',
        'betaald_kliniek_c',
        'datum_betaling_vr_c',
        'openstaand_c',
        'betaal_status_c',
        'pin_contant_c',
        'user_id_c',
    ];

    $cstmOverrides = array_intersect_key($overrides, array_flip($cstmKeys));
    $soOverrides = array_diff_key($overrides, array_flip($cstmKeys));

    DB::connection('sugarcrm')->table('pcrm_salesorder')->insert(array_merge([
        'id'               => $id,
        'name'             => "Order {$id}",
        'order_num'        => 202500001,
        'amount'           => 1500.00,
        'sales_stage'      => 'Gewonnen',
        'date_closed'      => '2025-06-01',
        'date_entered'     => '2025-01-15 10:00:00',
        'date_modified'    => '2025-01-15 10:00:00',
        'assigned_user_id' => null,
        'deleted'          => 0,
    ], $soOverrides));

    DB::connection('sugarcrm')->table('pcrm_salesorder_cstm')->insert(array_merge([
        'id_c'                => $id,
        'reden_afvoeren_c'    => null,
        'op_een_factuur_c'    => 0,
        'd_wfl_status_c'      => 'eindeproces',
        'betaald_vooruit_c'   => null,
        'betaald_kliniek_c'   => null,
        'datum_betaling_vr_c' => '2025-03-10 09:00:00',
        'openstaand_c'        => null,
        'betaal_status_c'     => null,
        'pin_contant_c'       => null,
        'user_id_c'           => null,
    ], $cstmOverrides));
}

function insertSugarRow(string $orderId, string $rowId, array $overrides = []): void
{
    DB::connection('sugarcrm')->table('pcrm_salesorderrow')->insert(array_merge([
        'id'              => $rowId,
        'name'            => 'TB1 Business Class',
        'sales_price'     => 1500.00,
        'sales_stage'     => 'Gewonnen',
        'datum_onderzoek' => null,
        'deleted'         => 0,
        'purchase_price'  => null,
        'purchase_clinic' => null,
        'purchase_doctor' => null,
    ], $overrides));

    DB::connection('sugarcrm')->table('pcrm_salesoalesorderrow_c')->insert([
        'pcrm_salesb9a7esorder_ida' => $orderId,
        'pcrm_sales509drderrow_idb' => $rowId,
    ]);
}

function linkRowToContact(string $rowId, string $contactExternalId): void
{
    DB::connection('sugarcrm')->table('pcrm_salesorow_contacts_c')->insert([
        'pcrm_sales80b3rderrow_idb' => $rowId,
        'pcrm_sales4bd9ontacts_ida' => $contactExternalId,
        'deleted'                   => 0,
    ]);
}

function insertSugarRowCstm(string $rowId, array $overrides = []): void
{
    DB::connection('sugarcrm')->table('pcrm_salesorderrow_cstm')->insert(array_merge([
        'id_c'                   => $rowId,
        'purchase_other_c'       => null,
        'purchase_cardio_c'      => null,
        'purchase_radio_c'       => null,
        'inv_purchase_other_c'   => null,
        'inv_purchase_cardio_c'  => null,
        'inv_purchase_clinic_c'  => null,
        'inv_purchase_radio_c'   => null,
        'inv_purchase_doctor_c'  => null,
        'inv_purchase_total_c'   => null,
        'ink_other_status_c'     => null,
        'ink_cardio_status_c'    => null,
        'ink_clinic_status_c'    => null,
        'ink_radio_status_c'     => null,
        'ink_doctor_status_c'    => null,
        'ink_total_status_c'     => null,
        'afb_description_c'      => null,
    ], $overrides));
}

function linkOrderToSugarLead(string $orderId, string $sugarLeadId): void
{
    DB::connection('sugarcrm')->table('leads_pcrm_salesorder_c')->insert([
        'leads_p903eeads_ida' => $sugarLeadId,
        'leads_p5ae2rder_idb' => $orderId,
        'deleted'             => 0,
    ]);
}

function insertSugarAccount(string $accountId, string $accountName, array $billing = [], array $accountCstm = []): void
{
    if (! DB::connection('sugarcrm')->table('accounts')->where('id', $accountId)->exists()) {
        DB::connection('sugarcrm')->table('accounts')->insert(array_merge([
            'id'                                  => $accountId,
            'name'                                => $accountName,
            'deleted'                             => 0,
            'billing_address_postalcode'          => null,
            'billing_address_state'               => null,
            'billing_address_street'              => null,
            'billing_address_city'                => null,
            'shipping_address_city'               => null,
            'billing_address_country'             => null,
        ], $billing));
    }

    // Sugar always has accounts_cstm for an account; import uses INNER JOIN on this table.
    if (! DB::connection('sugarcrm')->table('accounts_cstm')->where('id_c', $accountId)->exists()) {
        DB::connection('sugarcrm')->table('accounts_cstm')->insert(array_merge([
            'id_c'                         => $accountId,
            'billing_huisnr_c'             => null,
            'billing_huisnr_toevoeging_c'  => null,
        ], $accountCstm));
    }
}

function linkOrderToAccount(string $orderId, string $accountId): void
{
    DB::connection('sugarcrm')->table('pcrm_salesoder_accounts_c')->insert([
        'pcrm_salesd0bfesorder_idb' => $orderId,
        'pcrm_sales697fccounts_ida' => $accountId,
        'deleted'                   => 0,
    ]);
}

function runOrderImport(array $args = []): int
{
    return Artisan::call('import:orders', array_merge([
        '--connection' => 'sugarcrm',
        '--table'      => 'pcrm_salesorder',
    ], $args));
}

// ─── tests ──────────────────────────────────────────────────────────────────

test('imports zakelijk Sugar order with organization and is_business', function () {
    Person::factory()->create(['external_id' => 'contact-biz']);
    Lead::factory()->create(['external_id' => 'sugar-lead-biz']);

    insertSugarOrder('order-biz-001', ['order_num' => 202500099]);
    insertSugarRow('order-biz-001', 'row-biz', []);
    linkRowToContact('row-biz', 'contact-biz');
    linkOrderToSugarLead('order-biz-001', 'sugar-lead-biz');
    insertSugarAccount('acc-biz', 'Acme BV', [
        'billing_address_postalcode'   => '1846 LD',
        'billing_address_state'        => 'Noord-Holland',
        'billing_address_street'       => 'Schermerhoek 500',
        'billing_address_city'         => 'Groet',
        'billing_address_country'      => 'Nederland',
    ]);
    linkOrderToAccount('order-biz-001', 'acc-biz');

    expect(runOrderImport())->toBe(0);

    $order = Order::where('external_id', 'order-biz-001')->first();
    expect($order)->not->toBeNull()
        ->and($order->is_business)->toBeTrue()
        ->and($order->organization_id)->not->toBeNull();

    $org = Organization::with('address')->find($order->organization_id);
    expect($org)->not->toBeNull()->and($org->name)->toBe('Acme BV')
        ->and($org->address)->not->toBeNull()
        ->street->toBe('Schermerhoek')
        ->house_number->toBe('500')
        ->postal_code->toBe('1846LD')
        ->city->toBe('Groet')
        ->state->toBe('Noord-Holland')
        ->country->toBe('Nederland');

});

test('Sugar order import creates partner-product order checks from reporting', function () {
    $product = Product::where('name', 'TB1 Business Class')->firstOrFail();
    PartnerProduct::factory()->create([
        'product_id' => $product->id,
        'reporting'  => [ProductReports::RAD_MRI->value],
        'active'     => true,
    ]);

    Person::factory()->create(['external_id' => 'contact-order-checks']);
    Lead::factory()->create(['external_id' => 'sugar-lead-order-checks']);

    insertSugarOrder('order-import-checks-001');
    insertSugarRow('order-import-checks-001', 'row-import-checks', []);
    linkRowToContact('row-import-checks', 'contact-order-checks');
    linkOrderToSugarLead('order-import-checks-001', 'sugar-lead-order-checks');

    expect(runOrderImport())->toBe(0);

    $order = Order::where('external_id', 'order-import-checks-001')->first();
    expect($order)->not->toBeNull()
        ->and($order->orderItems->first()->external_id)->toBe('row-import-checks')
        ->and(
            $order->orderChecks()
                ->where('name', 'Partner product rapportage: Radiologie MRI')
                ->where('removable', false)
                ->exists()
        )->toBeTrue();
});

test('Fam prefixed Sugar account name is particulier not zakelijk', function () {
    Person::factory()->create(['external_id' => 'contact-fam']);
    Lead::factory()->create(['external_id' => 'sugar-lead-fam']);

    insertSugarOrder('order-fam-001');
    insertSugarRow('order-fam-001', 'row-fam', []);
    linkRowToContact('row-fam', 'contact-fam');
    linkOrderToSugarLead('order-fam-001', 'sugar-lead-fam');
    insertSugarAccount('acc-fam', 'Fam Jansen');
    linkOrderToAccount('order-fam-001', 'acc-fam');

    expect(runOrderImport())->toBe(0);

    $order = Order::where('external_id', 'order-fam-001')->first();
    expect($order->is_business)->toBeFalse()
        ->and($order->organization_id)->toBeNull();
});

test('two zakelijk orders with same Sugar account reuse one CRM organization', function () {
    Person::factory()->create(['external_id' => 'contact-shared']);
    Lead::factory()->create(['external_id' => 'sugar-lead-shared-a']);
    Lead::factory()->create(['external_id' => 'sugar-lead-shared-b']);

    insertSugarAccount('acc-shared', 'Shared BV');

    insertSugarOrder('order-biz-shared-a', ['order_num' => 202500101]);
    insertSugarRow('order-biz-shared-a', 'row-shared-a', []);
    linkRowToContact('row-shared-a', 'contact-shared');
    linkOrderToSugarLead('order-biz-shared-a', 'sugar-lead-shared-a');
    linkOrderToAccount('order-biz-shared-a', 'acc-shared');

    insertSugarOrder('order-biz-shared-b', ['order_num' => 202500102]);
    insertSugarRow('order-biz-shared-b', 'row-shared-b', []);
    linkRowToContact('row-shared-b', 'contact-shared');
    linkOrderToSugarLead('order-biz-shared-b', 'sugar-lead-shared-b');
    linkOrderToAccount('order-biz-shared-b', 'acc-shared');

    expect(runOrderImport())->toBe(0)
        ->and(Organization::where('name', 'Shared BV')->count())->toBe(1);

    $orderA = Order::where('external_id', 'order-biz-shared-a')->first();
    $orderB = Order::where('external_id', 'order-biz-shared-b')->first();
    expect($orderA->organization_id)->toBe($orderB->organization_id)
        ->and($orderA->is_business)->toBeTrue()
        ->and($orderB->is_business)->toBeTrue();
});

test('zakelijk import uses fallback house number 9999 when Sugar street has no huisnummer', function () {
    Person::factory()->create(['external_id' => 'contact-nohouse']);
    Lead::factory()->create(['external_id' => 'sugar-lead-nohouse']);

    insertSugarOrder('order-nohouse-001');
    insertSugarRow('order-nohouse-001', 'row-nohouse', []);
    linkRowToContact('row-nohouse', 'contact-nohouse');
    linkOrderToSugarLead('order-nohouse-001', 'sugar-lead-nohouse');
    insertSugarAccount('acc-nohouse', 'Zorg BV', [
        'billing_address_postalcode'   => '1081 AB',
        'billing_address_street'       => 'Burgemeester C. van de Werkenstraat',
        'billing_address_city'         => 'Amsterdam',
        'billing_address_country'      => 'Nederland',
    ]);
    linkOrderToAccount('order-nohouse-001', 'acc-nohouse');

    expect(runOrderImport())->toBe(0);

    $org = Organization::with('address')->where('name', 'Zorg BV')->first();
    expect($org->address)->not->toBeNull()
        ->street->toBe('Burgemeester C. van de Werkenstraat')
        ->house_number->toBe('9999')
        ->postal_code->toBe('1081AB');
});

test('zakelijk import uses accounts_cstm billing_huisnr_c when street has no parseable huisnummer', function () {
    Person::factory()->create(['external_id' => 'contact-cstm-huis']);
    Lead::factory()->create(['external_id' => 'sugar-lead-cstm-huis']);

    insertSugarOrder('order-cstm-huis-001');
    insertSugarRow('order-cstm-huis-001', 'row-cstm-huis', []);
    linkRowToContact('row-cstm-huis', 'contact-cstm-huis');
    linkOrderToSugarLead('order-cstm-huis-001', 'sugar-lead-cstm-huis');
    insertSugarAccount('acc-cstm-huis', 'Cstm Huis BV', [
        'billing_address_postalcode'   => '1081 AB',
        'billing_address_street'       => 'Burgemeester C. van de Werkenstraat',
        'billing_address_city'         => 'Amsterdam',
        'billing_address_country'      => 'Nederland',
    ], [
        'billing_huisnr_c'             => '42',
        'billing_huisnr_toevoeging_c'  => 'A',
    ]);
    linkOrderToAccount('order-cstm-huis-001', 'acc-cstm-huis');

    expect(runOrderImport())->toBe(0);

    $org = Organization::with('address')->where('name', 'Cstm Huis BV')->first();
    expect($org->address)->not->toBeNull()
        ->street->toBe('Burgemeester C. van de Werkenstraat')
        ->house_number->toBe('42')
        ->house_number_suffix->toBe('A')
        ->postal_code->toBe('1081AB');
});

test('zakelijk import uses billing_huisnr_toevoeging_c over suffix parsed from street', function () {
    Person::factory()->create(['external_id' => 'contact-cstm-toev']);
    Lead::factory()->create(['external_id' => 'sugar-lead-cstm-toev']);

    insertSugarOrder('order-cstm-toev-001');
    insertSugarRow('order-cstm-toev-001', 'row-cstm-toev', []);
    linkRowToContact('row-cstm-toev', 'contact-cstm-toev');
    linkOrderToSugarLead('order-cstm-toev-001', 'sugar-lead-cstm-toev');
    insertSugarAccount('acc-cstm-toev', 'Cstm Toev BV', [
        'billing_address_postalcode'   => '9711 BP',
        'billing_address_street'       => 'Willem Barentszroute 12-B',
        'billing_address_city'         => 'Groningen',
        'billing_address_country'      => 'NL',
    ], ['billing_huisnr_toevoeging_c' => 'bis']);
    linkOrderToAccount('order-cstm-toev-001', 'acc-cstm-toev');

    expect(runOrderImport())->toBe(0);

    $addr = Organization::with('address')->where('name', 'Cstm Toev BV')->first()?->address;
    expect($addr)->not->toBeNull()
        ->street->toBe('Willem Barentszroute')
        ->house_number->toBe('12')
        ->house_number_suffix->toBe('bis');
});

test('zakelijk import skips organization address when Sugar billing postcode missing', function () {
    Person::factory()->create(['external_id' => 'contact-nozip']);
    Lead::factory()->create(['external_id' => 'sugar-lead-nozip']);

    insertSugarOrder('order-nozip-001');
    insertSugarRow('order-nozip-001', 'row-nozip', []);
    linkRowToContact('row-nozip', 'contact-nozip');
    linkOrderToSugarLead('order-nozip-001', 'sugar-lead-nozip');
    insertSugarAccount('acc-nozip', 'Losse BV', [
        'billing_address_street'  => 'Ergens 10',
        'billing_address_city'    => 'Utrecht',
    ]);
    linkOrderToAccount('order-nozip-001', 'acc-nozip');

    expect(runOrderImport())->toBe(0);

    $org = Organization::find(Order::where('external_id', 'order-nozip-001')->value('organization_id'));
    expect($org)->not->toBeNull()->and($org->address_id)->toBeNull();
});

test('zakelijk import uses shipping_address_city when billing city missing', function () {
    Person::factory()->create(['external_id' => 'contact-ship-city']);
    Lead::factory()->create(['external_id' => 'sugar-lead-ship-city']);

    insertSugarOrder('order-ship-city-001');
    insertSugarRow('order-ship-city-001', 'row-ship-city', []);
    linkRowToContact('row-ship-city', 'contact-ship-city');
    linkOrderToSugarLead('order-ship-city-001', 'sugar-lead-ship-city');
    insertSugarAccount('acc-ship-city', 'Ship City BV', [
        'billing_address_postalcode'   => '9711 BP',
        'billing_address_street'       => 'Willem Barentszroute 12-B',
        'shipping_address_city'        => 'Groningen',
        'billing_address_country'      => 'NL',
    ]);
    linkOrderToAccount('order-ship-city-001', 'acc-ship-city');

    expect(runOrderImport())->toBe(0);

    $addr = Organization::with('address')->where('name', 'Ship City BV')->first()?->address;
    expect($addr)->not->toBeNull()
        ->street->toBe('Willem Barentszroute')
        ->house_number->toBe('12')
        ->house_number_suffix->toBe('B')
        ->city->toBe('Groningen')
        ->postal_code->toBe('9711BP');
});

test('import sets orderitem to PLANNED when order has examination date', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-planned-001']);
    Person::factory()->create(['external_id' => 'contact-planned']);

    insertSugarOrder('order-planned-001', [
        'sales_stage'       => 'Optie',
        'datum_onderzoek_1' => '2025-08-20',
    ]);
    insertSugarRow('order-planned-001', 'row-planned', ['sales_stage' => 'Optie']);
    linkRowToContact('row-planned', 'contact-planned');
    linkOrderToSugarLead('order-planned-001', 'sugar-lead-planned-001');

    expect(runOrderImport())->toBe(0);

    $order = Order::where('external_id', 'order-planned-001')->first();
    expect($order->orderItems)->toHaveCount(1)
        ->and($order->orderItems->first()->status)->toBe(OrderItemStatus::PLANNED);
});

test('import keeps orderitem LOST when row is verloren even with examination date', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-lost-exam']);

    insertSugarOrder('order-lost-exam', [
        'sales_stage'       => 'Verloren',
        'datum_onderzoek_1' => '2025-08-20',
        'reden_afvoeren_c'  => 'prijs',
    ]);
    insertSugarRow('order-lost-exam', 'row-lost-exam', ['sales_stage' => 'Verloren']);
    linkOrderToSugarLead('order-lost-exam', 'sugar-lead-lost-exam');

    expect(runOrderImport())->toBe(0);

    $order = Order::where('external_id', 'order-lost-exam')->first();
    expect($order->orderItems)->toHaveCount(1)
        ->and($order->orderItems->first()->status)->toBe(OrderItemStatus::LOST);
});

test('imports won order and creates saleslead and orderitem linked to person', function () {
    $person = Person::factory()->create(['external_id' => 'contact-001']);
    $lead = Lead::factory()->create(['external_id' => 'sugar-lead-001']);

    insertSugarOrder('order-001', ['sales_stage' => 'Gewonnen', 'order_num' => 202500001]);
    insertSugarRow('order-001', 'row-001', ['sales_stage' => 'Gewonnen', 'sales_price' => 1500]);
    linkRowToContact('row-001', 'contact-001');
    linkOrderToSugarLead('order-001', 'sugar-lead-001');

    $exit = runOrderImport();

    expect($exit)->toBe(0);

    $order = Order::where('external_id', 'order-001')->first();
    expect($order)->not->toBeNull()
        ->and((int) $order->order_number)->toBe(202500001)
        ->and((float) $order->total_price)->toBe(1500.0)
        ->and($order->pipeline_stage_id)->toBe(PipelineStage::ORDER_GEWONNEN->id());

    // SalesLead created and linked to the imported CRM Lead
    $salesLead = $order->salesLead;
    expect($salesLead)->not->toBeNull()
        ->and($salesLead->pipeline_stage_id)->toBe(PipelineStage::SALES_MET_SUCCES_AFGEROND->id())
        ->and($salesLead->lead_id)->toBe($lead->id)
        ->and($salesLead->persons->contains($person))->toBeTrue()
        ->and(Anamnesis::query()
            ->where('sales_id', $salesLead->id)
            ->where('person_id', $person->id)
            ->exists())->toBeTrue()
        ->and($order->orderItems)->toHaveCount(1);

    // Person attached to SalesLead

    // OrderItem created with correct status
    $item = $order->orderItems->first();
    expect($item->status)->toBe(OrderItemStatus::WON)
        ->and($item->person_id)->toBe($person->id)
        ->and((float) $item->total_price)->toBe(1500.0);
});

test('gewonnen order stage is ORDER_GEWONNEN independent of OrderObserver', function () {
    Person::factory()->create(['external_id' => 'contact-won-stage']);
    Lead::factory()->create(['external_id' => 'sugar-lead-won-stage']);

    insertSugarOrder('order-won-stage', ['sales_stage' => 'Gewonnen']);
    insertSugarRow('order-won-stage', 'row-won-stage', ['sales_stage' => 'Gewonnen']);
    linkRowToContact('row-won-stage', 'contact-won-stage');
    linkOrderToSugarLead('order-won-stage', 'sugar-lead-won-stage');

    expect(runOrderImport())->toBe(0);

    $order = Order::where('external_id', 'order-won-stage')->first();
    expect($order)->not->toBeNull()
        ->and($order->pipeline_stage_id)->toBe(PipelineStage::ORDER_GEWONNEN->id());
});

test('imports lost order with lost reason', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-lost-001']);

    insertSugarOrder('order-lost-001', [
        'sales_stage'      => 'Verloren',
        'reden_afvoeren_c' => 'prijs',
    ]);
    linkOrderToSugarLead('order-lost-001', 'sugar-lead-lost-001');

    $exit = runOrderImport();

    expect($exit)->toBe(0);

    $order = Order::where('external_id', 'order-lost-001')->first();
    expect($order)->not->toBeNull()
        ->and($order->pipeline_stage_id)->toBe(PipelineStage::ORDER_VERLOREN->id())
        ->and($order->lost_reason)->toBe(LostReason::Price)
        ->and($order->salesLead->pipeline_stage_id)
        ->toBe(PipelineStage::SALES_NIET_SUCCESVOL_AFGEROND->id());

});

test('unknown sales_stage maps to first order stage', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-optie-001']);

    insertSugarOrder('order-optie-001', ['sales_stage' => 'Optie']);
    linkOrderToSugarLead('order-optie-001', 'sugar-lead-optie-001');

    runOrderImport();

    $order = Order::where('external_id', 'order-optie-001')->first();
    expect($order->pipeline_stage_id)->toBe(PipelineStage::ORDER_CONFIRM->id())
        ->and($order->salesLead->pipeline_stage_id)->toBe(PipelineStage::SALES_IN_BEHANDELING->id());
});

test('skips already imported order on re-run', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-dup-001']);

    insertSugarOrder('order-dup-001');
    linkOrderToSugarLead('order-dup-001', 'sugar-lead-dup-001');

    runOrderImport();
    runOrderImport(); // second run

    expect(Order::where('external_id', 'order-dup-001')->count())->toBe(1)
        ->and(SalesLead::count())->toBe(1);
});

test('order with multiple rows and multiple persons creates multiple orderitems', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-multi-001']);

    $personA = Person::factory()->create(['external_id' => 'contact-A']);
    $personB = Person::factory()->create(['external_id' => 'contact-B']);

    Product::factory()->create(['name' => 'Scan A']);
    Product::factory()->create(['name' => 'Scan B']);

    insertSugarOrder('order-multi-001', ['amount' => 3000]);
    insertSugarRow('order-multi-001', 'row-A', ['sales_price' => 1500, 'name' => 'Scan A']);
    insertSugarRow('order-multi-001', 'row-B', ['sales_price' => 1500, 'name' => 'Scan B']);
    linkRowToContact('row-A', 'contact-A');
    linkRowToContact('row-B', 'contact-B');
    linkOrderToSugarLead('order-multi-001', 'sugar-lead-multi-001');

    runOrderImport();

    $order = Order::where('external_id', 'order-multi-001')->first();
    expect($order->orderItems)->toHaveCount(2);

    $personIds = $order->orderItems->pluck('person_id')->sort()->values();
    expect($personIds)->toContain($personA->id, $personB->id);
});

test('order row without matching person still imports with person_id null', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-noperson-001']);

    insertSugarOrder('order-noperson-001');
    insertSugarRow('order-noperson-001', 'row-noperson', []);
    linkRowToContact('row-noperson', 'contact-does-not-exist');
    linkOrderToSugarLead('order-noperson-001', 'sugar-lead-noperson-001');

    runOrderImport();

    $order = Order::where('external_id', 'order-noperson-001')->first();
    expect($order)->not->toBeNull()
        ->and($order->orderItems)->toHaveCount(1)
        ->and($order->orderItems->first()->person_id)->toBeNull();
});

test('order row with matching product sets product_id on orderitem', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-product-001']);

    $product = Product::factory()->create(['external_id' => 'product-template-001']);

    insertSugarOrder('order-product-001');
    insertSugarRow('order-product-001', 'row-product-001');
    insertSugarRowCstm('row-product-001', ['aos_products_id_c' => 'product-template-001']);
    linkOrderToSugarLead('order-product-001', 'sugar-lead-product-001');

    runOrderImport();

    $order = Order::where('external_id', 'order-product-001')->first();
    expect($order->orderItems->first()->product_id)->toBe($product->id);
});

test('order row resolves product by exact CRM name when template id has no match', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-by-name-001']);

    $product = Product::factory()->create(['name' => 'TB3 Regular Bodyscan + Wervelkolom zonder cardio']);

    insertSugarOrder('order-by-name-001');
    insertSugarRow('order-by-name-001', 'row-by-name-001', [
        'name'        => 'TB3 Regular Bodyscan + Wervelkolom zonder cardio',
        'sales_price' => 1890,
    ]);
    insertSugarRowCstm('row-by-name-001', ['aos_products_id_c' => 'no-such-template-in-crm']);
    linkOrderToSugarLead('order-by-name-001', 'sugar-lead-by-name-001');

    runOrderImport();

    $order = Order::where('external_id', 'order-by-name-001')->first();
    expect($order)->not->toBeNull()
        ->and($order->orderItems)->toHaveCount(1)
        ->and($order->orderItems->first()->product_id)->toBe($product->id);
});

test('order row resolves product via partner product name when Sugar line differs from catalog Product name', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-pp-name-001']);

    $product = Product::factory()->create(['name' => 'TB1 catalog internal label']);
    PartnerProduct::factory()->create([
        'product_id' => $product->id,
        'name'       => 'TB1 Royal Bodyscan',
        'active'     => true,
    ]);

    insertSugarOrder('order-pp-name-001');
    insertSugarRow('order-pp-name-001', 'row-pp-001', [
        'name' => 'TB1 Royal+ Bodyscan',
    ]);
    insertSugarRowCstm('row-pp-001', ['aos_products_id_c' => 'unknown-sugar-template-uuid']);
    linkOrderToSugarLead('order-pp-name-001', 'sugar-lead-pp-name-001');

    runOrderImport();

    $order = Order::where('external_id', 'order-pp-name-001')->first();
    expect($order)->not->toBeNull()
        ->and($order->orderItems)->toHaveCount(1)
        ->and($order->orderItems->first()->product_id)->toBe($product->id);
});

test('imports invoice purchase prices from Sugar order row cstm', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-inv-001']);

    Person::factory()->create(['external_id' => 'contact-inv-1']);

    insertSugarOrder('order-inv-001');
    // purchase_clinic lives on the main row table; purchase_price is the authoritative total
    insertSugarRow('order-inv-001', 'row-inv-001', [
        'purchase_price'  => 11,
        'purchase_clinic' => 3,
    ]);
    insertSugarRowCstm('row-inv-001', [
        'purchase_other_c'      => 1.5,
        'purchase_cardio_c'     => 2.25,
        'purchase_radio_c'      => 4.25,
        'inv_purchase_other_c'  => 1.5,
        'inv_purchase_cardio_c' => 2.25,
        'inv_purchase_clinic_c' => 3,
        'inv_purchase_radio_c'  => 4.25,
        'inv_purchase_total_c'  => 11,
    ]);
    linkRowToContact('row-inv-001', 'contact-inv-1');
    linkOrderToSugarLead('order-inv-001', 'sugar-lead-inv-001');

    runOrderImport();

    $item = Order::where('external_id', 'order-inv-001')->first()->orderItems->first();
    $inv = $item->invoicePurchasePrice;
    $main = $item->purchasePrice;

    expect($inv)->not->toBeNull()
        ->and($inv->type)->toBe(PurchasePriceType::INVOICE)
        ->and((float) $inv->purchase_price_misc)->toBe(1.5)
        ->and((float) $inv->purchase_price_cardiology)->toBe(2.25)
        ->and((float) $inv->purchase_price_clinic)->toBe(3.0)
        ->and((float) $inv->purchase_price_radiology)->toBe(4.25)
        ->and((float) $inv->purchase_price)->toBe(11.0)
        ->and((float) $inv->purchase_price_doctor)->toBe(0.0)
        ->and($main)->not->toBeNull()
        ->and($main->type)->toBe(PurchasePriceType::MAIN)
        ->and((float) $main->purchase_price)->toBe(11.0);

    $resolved = $item->fresh(['product.partnerProducts.purchasePrice'])->resolvedPurchasePrice();
    expect((float) $resolved->purchase_price)->toBe(11.0)
        ->and((float) $resolved->purchase_price_misc)->toBe(1.5)
        ->and((float) $resolved->purchase_price_cardiology)->toBe(2.25);
});

test('invoice import ignores inv_purchase_radio_c when ink_radio_status_c is geen', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-ink-geen']);

    Person::factory()->create(['external_id' => 'contact-ink-geen']);

    insertSugarOrder('order-ink-geen-001');
    insertSugarRow('order-ink-geen-001', 'row-ink-geen-001');
    insertSugarRowCstm('row-ink-geen-001', [
        'purchase_radio_c'     => 169,
        'inv_purchase_radio_c' => 50,
        'ink_radio_status_c'   => 'geen',
        'inv_purchase_total_c' => 50,
    ]);
    linkRowToContact('row-ink-geen-001', 'contact-ink-geen');
    linkOrderToSugarLead('order-ink-geen-001', 'sugar-lead-ink-geen');

    runOrderImport();

    $item = Order::where('external_id', 'order-ink-geen-001')->first()->orderItems->first();

    expect((float) $item->purchasePrice->purchase_price_radiology)->toBe(169.0)
        ->and((float) $item->invoicePurchasePrice->purchase_price_radiology)->toBe(0.0)
        ->and((float) $item->invoicePurchasePrice->purchase_price)->toBe(0.0)
        ->and((float) $item->invoicePurchasePrice->purchase_price_misc)->toBe(0.0);
});

test('invoice import treats SuiteCRM export row like Result_6.csv as not paid (teontvangen + geen buckets)', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-result6-mri']);

    Person::factory()->create(['external_id' => 'contact-result6-mri']);

    insertSugarOrder('order-result6-mri');
    insertSugarRow('order-result6-mri', 'row-result6-mri');
    insertSugarRowCstm('row-result6-mri', [
        'purchase_radio_c'     => 169,
        'inv_purchase_radio_c' => 169,
        'inv_purchase_total_c' => 169,
        'ink_radio_status_c'   => 'geen',
        'ink_total_status_c'   => 'teontvangen',
    ]);
    linkRowToContact('row-result6-mri', 'contact-result6-mri');
    linkOrderToSugarLead('order-result6-mri', 'sugar-lead-result6-mri');

    runOrderImport();

    $item = Order::where('external_id', 'order-result6-mri')->first()->orderItems->first();
    $inv = $item->invoicePurchasePrice;

    expect((float) $inv->purchase_price_radiology)->toBe(0.0)
        ->and((float) $inv->purchase_price)->toBe(0.0)
        ->and((float) $inv->purchase_price_misc)->toBe(0.0);
});

test('invoice import ignores inv_purchase_radio_c when ink_radio_status_c is open', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-ink-open']);

    Person::factory()->create(['external_id' => 'contact-ink-open']);

    insertSugarOrder('order-ink-open-001');
    insertSugarRow('order-ink-open-001', 'row-ink-open-001');
    insertSugarRowCstm('row-ink-open-001', [
        'purchase_radio_c'     => 169,
        'inv_purchase_radio_c' => 50,
        'ink_radio_status_c'   => 'open',
        'inv_purchase_total_c' => 50,
    ]);
    linkRowToContact('row-ink-open-001', 'contact-ink-open');
    linkOrderToSugarLead('order-ink-open-001', 'sugar-lead-ink-open');

    runOrderImport();

    $item = Order::where('external_id', 'order-ink-open-001')->first()->orderItems->first();

    expect((float) $item->purchasePrice->purchase_price_radiology)->toBe(169.0)
        ->and((float) $item->invoicePurchasePrice->purchase_price_radiology)->toBe(0.0)
        ->and((float) $item->invoicePurchasePrice->purchase_price)->toBe(0.0)
        ->and((float) $item->invoicePurchasePrice->purchase_price_misc)->toBe(0.0);
});

test('invoice import ignores inv_purchase_total_c when ink_total_status_c is geen', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-ink-total-geen']);

    Person::factory()->create(['external_id' => 'contact-ink-total-geen']);

    insertSugarOrder('order-ink-total-geen-001');
    insertSugarRow('order-ink-total-geen-001', 'row-ink-total-geen-001');
    insertSugarRowCstm('row-ink-total-geen-001', [
        'inv_purchase_radio_c' => 20,
        'inv_purchase_total_c' => 100,
        'ink_total_status_c'   => 'geen',
    ]);
    linkRowToContact('row-ink-total-geen-001', 'contact-ink-total-geen');
    linkOrderToSugarLead('order-ink-total-geen-001', 'sugar-lead-ink-total-geen');

    runOrderImport();

    $item = Order::where('external_id', 'order-ink-total-geen-001')->first()->orderItems->first();
    $inv = $item->invoicePurchasePrice;

    expect((float) $inv->purchase_price_radiology)->toBe(20.0)
        ->and((float) $inv->purchase_price)->toBe(20.0)
        ->and((float) $inv->purchase_price_misc)->toBe(0.0);
});

test('creates zero PurchasePrice rows when Sugar inv fields are empty so resolved price ignores catalog', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-noinv']);

    Person::factory()->create(['external_id' => 'contact-noinv']);

    insertSugarOrder('order-noinv-001');
    insertSugarRow('order-noinv-001', 'row-noinv-001');
    insertSugarRowCstm('row-noinv-001', [
        'inv_purchase_other_c'  => null,
        'inv_purchase_cardio_c' => null,
        'inv_purchase_clinic_c' => null,
        'inv_purchase_radio_c'  => null,
        'inv_purchase_total_c'  => null,
    ]);
    linkRowToContact('row-noinv-001', 'contact-noinv');
    linkOrderToSugarLead('order-noinv-001', 'sugar-lead-noinv');

    runOrderImport();

    $item = Order::where('external_id', 'order-noinv-001')->first()->orderItems->first();
    expect($item->invoicePurchasePrice)->not->toBeNull()
        ->and($item->purchasePrice)->not->toBeNull()
        ->and((float) $item->purchasePrice->purchase_price)->toBe(0.0);

    $resolved = $item->fresh(['product.partnerProducts.purchasePrice'])->resolvedPurchasePrice();
    expect((float) $resolved->purchase_price)->toBe(0.0);
});

test('imports inv_purchase_total_c only into MAIN and resolved purchase total', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-totalonly']);

    Person::factory()->create(['external_id' => 'contact-totalonly']);

    insertSugarOrder('order-totalonly-001');
    // purchase_total_c does not exist in Sugar; total lives on the main row table
    insertSugarRow('order-totalonly-001', 'row-totalonly-001', ['purchase_price' => 99.5]);
    insertSugarRowCstm('row-totalonly-001', [
        'inv_purchase_total_c' => 99.5,
    ]);
    linkRowToContact('row-totalonly-001', 'contact-totalonly');
    linkOrderToSugarLead('order-totalonly-001', 'sugar-lead-totalonly');

    runOrderImport();

    $item = Order::where('external_id', 'order-totalonly-001')->first()->orderItems->first();

    expect((float) $item->purchasePrice->purchase_price)->toBe(99.5)
        ->and((float) $item->purchasePrice->purchase_price_misc)->toBe(99.5)
        ->and((float) $item->invoicePurchasePrice->purchase_price)->toBe(99.5);

    $resolved = $item->fresh(['product.partnerProducts.purchasePrice'])->resolvedPurchasePrice();
    expect((float) $resolved->purchase_price)->toBe(99.5)
        ->and((float) $resolved->purchase_price_misc)->toBe(99.5);
});

test('Sugar purchase prices override partner product in resolvedPurchasePrice', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-sugarpp']);

    Person::factory()->create(['external_id' => 'contact-sugarpp']);

    $product = Product::where('name', 'TB1 Business Class')->first();
    $pp = PartnerProduct::factory()->create(['product_id' => $product->id]);
    $pp->purchasePrice->update([
        'purchase_price_misc'        => 100,
        'purchase_price_doctor'      => 100,
        'purchase_price_cardiology'  => 100,
        'purchase_price_clinic'      => 100,
        'purchase_price_radiology'   => 100,
        'purchase_price'             => 500,
    ]);

    insertSugarOrder('order-sugarpp-001');
    // purchase_clinic and purchase_price live on the main row table
    insertSugarRow('order-sugarpp-001', 'row-sugarpp-001', [
        'purchase_price'  => 40,
        'purchase_clinic' => 10,
    ]);
    insertSugarRowCstm('row-sugarpp-001', [
        'purchase_other_c'      => 10,
        'purchase_cardio_c'     => 10,
        'purchase_radio_c'      => 10,
        'inv_purchase_other_c'  => 10,
        'inv_purchase_cardio_c' => 10,
        'inv_purchase_clinic_c' => 10,
        'inv_purchase_radio_c'  => 10,
        'inv_purchase_total_c'  => 40,
    ]);
    linkRowToContact('row-sugarpp-001', 'contact-sugarpp');
    linkOrderToSugarLead('order-sugarpp-001', 'sugar-lead-sugarpp');

    runOrderImport();

    $item = Order::where('external_id', 'order-sugarpp-001')->first()->orderItems->first();
    $resolved = $item->fresh(['product.partnerProducts.purchasePrice'])->resolvedPurchasePrice();

    expect((float) $resolved->purchase_price)->toBe(40.0)
        ->and((float) $resolved->purchase_price_misc)->toBe(10.0)
        ->and((float) $resolved->purchase_price_cardiology)->toBe(10.0);
});

test('orders entered before 2025 are excluded by the date filter', function () {
    insertSugarOrder('order-old-001', [
        'date_entered'  => '2024-12-31 23:59:59',
        'date_modified' => '2024-12-31 23:59:59',
    ]);

    runOrderImport();

    expect(Order::where('external_id', 'order-old-001')->exists())->toBeFalse();
});

test('combine_order flag is imported correctly', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-combined']);

    insertSugarOrder('order-combined-001', ['op_een_factuur_c' => 1]);
    linkOrderToSugarLead('order-combined-001', 'sugar-lead-combined');

    runOrderImport();

    $order = Order::where('external_id', 'order-combined-001')->first();
    expect($order->combine_order)->toBeTrue();
});

test('import limit counts distinct Sugar orders when account join duplicates rows', function () {
    insertSugarOrder('order-limit-a', [
        'date_entered' => '2025-03-02 12:00:00',
        'order_num'    => 202500901,
    ]);
    insertSugarOrder('order-limit-b', [
        'date_entered' => '2025-03-01 12:00:00',
        'order_num'    => 202500902,
    ]);

    insertSugarAccount('acc-lim-1', 'Lim One');
    insertSugarAccount('acc-lim-2', 'Lim Two');
    linkOrderToAccount('order-limit-a', 'acc-lim-1');
    linkOrderToAccount('order-limit-a', 'acc-lim-2');
    linkOrderToAccount('order-limit-b', 'acc-lim-2');

    Artisan::call('import:orders', [
        '--connection' => 'sugarcrm',
        '--table'      => 'pcrm_salesorder',
        '--dry-run'    => true,
        '--limit'      => '2',
    ]);

    expect(Artisan::output())->toContain('Found 2 orders to import');
});

test('dry run does not persist any data', function () {
    insertSugarOrder('order-dryrun-001');

    $exit = runOrderImport(['--dry-run' => true]);

    expect($exit)->toBe(0)
        ->and(Order::where('external_id', 'order-dryrun-001')->exists())->toBeFalse()
        ->and(SalesLead::count())->toBe(0);
});

test('imports Sugar advance payment as OrderPayment with date_entered as paid_at', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-pay-adv']);

    insertSugarOrder('order-pay-adv-001', [
        'betaald_vooruit_c'        => 2206.0,
        'betaal_status_c'          => 'volledig',
        'date_entered'             => '2025-02-10 09:00:00',
        'datum_betaling_vr_c'      => '2025-03-10 09:00:00',
        'date_closed'              => '2025-08-20',
    ]);
    linkOrderToSugarLead('order-pay-adv-001', 'sugar-lead-pay-adv');

    runOrderImport();

    $order = Order::where('external_id', 'order-pay-adv-001')->first();
    expect($order)->not->toBeNull();

    $payments = OrderPayment::where('order_id', $order->id)->get();
    expect($payments)->toHaveCount(1);

    $p = $payments->first();
    expect($p->type)->toBe(PaymentType::ADVANCE)
        ->and($p->method)->toBe(PaymentMethod::BANK)
        ->and((float) $p->amount)->toBe(2206.0)
        ->and($p->currency)->toBe('EUR')
        ->and($p->paid_at?->format('Y-m-d'))->toBe('2025-03-10');
});

test('imports Sugar clinic payment with pin_contant mapping', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-pay-clinic']);

    insertSugarOrder('order-pay-clinic-001', [
        'betaald_kliniek_c' => 50.0,
        'pin_contant_c'     => 'contant',
    ]);
    linkOrderToSugarLead('order-pay-clinic-001', 'sugar-lead-pay-clinic');

    runOrderImport();

    $order = Order::where('external_id', 'order-pay-clinic-001')->first();
    $p = OrderPayment::where('order_id', $order->id)->sole();

    expect($p->type)->toBe(PaymentType::PAYED_IN_CLINIC)
        ->and($p->method)->toBe(PaymentMethod::CASH)
        ->and((float) $p->amount)->toBe(50.0);
});

test('imports Sugar clinic payment with datum_onderzoek_1 as paid_at', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-pay-clinic-date']);

    insertSugarOrder('order-pay-clinic-date-001', [
        'betaald_kliniek_c'  => 75.0,
        'pin_contant_c'      => 'pin',
        'datum_onderzoek_1'  => '2025-07-15',
    ]);
    linkOrderToSugarLead('order-pay-clinic-date-001', 'sugar-lead-pay-clinic-date');

    runOrderImport();

    $order = Order::where('external_id', 'order-pay-clinic-date-001')->first();
    expect($order)->not->toBeNull();

    $p = OrderPayment::where('order_id', $order->id)->sole();
    expect($p->type)->toBe(PaymentType::PAYED_IN_CLINIC)
        ->and($p->method)->toBe(PaymentMethod::PIN)
        ->and((float) $p->amount)->toBe(75.0)
        ->and($p->paid_at?->format('Y-m-d'))->toBe('2025-07-15');
});

test('payment dates differ for advance and clinic on same order', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-pay-both']);

    insertSugarOrder('order-pay-both-001', [
        'betaald_vooruit_c'   => 100.0,
        'betaald_kliniek_c'   => 50.0,
        'pin_contant_c'       => 'pin',
        'date_entered'        => '2025-01-01 08:30:00',
        'datum_betaling_vr_c' => '2025-02-01 08:30:00',
        'datum_onderzoek_1'   => '2025-06-20',
    ]);
    linkOrderToSugarLead('order-pay-both-001', 'sugar-lead-pay-both');

    runOrderImport();

    $order = Order::where('external_id', 'order-pay-both-001')->first();
    $payments = OrderPayment::where('order_id', $order->id)->get();
    expect($payments)->toHaveCount(2);

    $adv = $payments->firstWhere('type', PaymentType::ADVANCE);
    $cli = $payments->firstWhere('type', PaymentType::PAYED_IN_CLINIC);

    expect($adv->paid_at?->format('Y-m-d'))->toBe('2025-02-01')
        ->and($cli->paid_at?->format('Y-m-d'))->toBe('2025-06-20');
});

test('does not create OrderPayments when Sugar payment amounts are empty', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-pay-none']);

    insertSugarOrder('order-pay-none-001', [
        'betaald_vooruit_c' => null,
        'betaald_kliniek_c' => null,
    ]);
    linkOrderToSugarLead('order-pay-none-001', 'sugar-lead-pay-none');

    runOrderImport();

    $order = Order::where('external_id', 'order-pay-none-001')->first();
    expect(OrderPayment::where('order_id', $order->id)->count())->toBe(0);
});

test('creates ResourceOrderItem with correct times when duration and resource are present', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-roi-001']);
    Person::factory()->create(['external_id' => 'contact-roi-001']);
    $resource = Resource::factory()->create(['external_id' => 'sugar-resource-uuid-001']);

    insertSugarOrder('order-roi-001', [
        'datum_onderzoek_1' => '2025-07-15',
        'aankomsttijd_c'    => '07:00', // UTC → 09:00 Amsterdam (CEST)
    ]);
    insertSugarRow('order-roi-001', 'row-roi-001', [
        'duration'                   => 60,
        'pcrm_partnerresources_id_c' => 'sugar-resource-uuid-001',
        'datum_onderzoek'            => '2025-07-15 07:00:00', // UTC → 09:00 Amsterdam (CEST)
    ]);
    linkRowToContact('row-roi-001', 'contact-roi-001');
    linkOrderToSugarLead('order-roi-001', 'sugar-lead-roi-001');

    expect(runOrderImport())->toBe(0);

    $order = Order::where('external_id', 'order-roi-001')->first();
    $orderItem = $order->orderItems->first();
    expect($orderItem)->not->toBeNull();

    $roi = ResourceOrderItem::where('orderitem_id', $orderItem->id)->first();
    expect($roi)->not->toBeNull()
        ->and($roi->resource_id)->toBe($resource->id)
        ->and($roi->from->format('Y-m-d H:i:s'))->toBe('2025-07-15 09:00:00')
        ->and($roi->to->format('Y-m-d H:i:s'))->toBe('2025-07-15 10:00:00');
});

test('does not create ResourceOrderItem when duration is null', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-roi-nodur']);
    Person::factory()->create(['external_id' => 'contact-roi-nodur']);
    Resource::factory()->create(['external_id' => 'sugar-resource-nodur']);

    insertSugarOrder('order-roi-nodur', [
        'datum_onderzoek_1' => '2025-07-15',
        'aankomsttijd_c'    => '09:00',
    ]);
    insertSugarRow('order-roi-nodur', 'row-roi-nodur', [
        'duration'                   => null,
        'pcrm_partnerresources_id_c' => 'sugar-resource-nodur',
        'datum_onderzoek'            => '2025-07-15 09:00:00',
    ]);
    linkRowToContact('row-roi-nodur', 'contact-roi-nodur');
    linkOrderToSugarLead('order-roi-nodur', 'sugar-lead-roi-nodur');

    runOrderImport();

    $order = Order::where('external_id', 'order-roi-nodur')->first();
    expect($order)->not->toBeNull();
    expect(ResourceOrderItem::whereHas('orderItem', fn ($q) => $q->where('order_id', $order->id))->count())->toBe(0);
});

test('logs warning and still imports order when resource external_id not found', function () {
    Log::spy();

    Lead::factory()->create(['external_id' => 'sugar-lead-roi-nores']);
    Person::factory()->create(['external_id' => 'contact-roi-nores']);

    insertSugarOrder('order-roi-nores', [
        'datum_onderzoek_1' => '2025-07-15',
        'aankomsttijd_c'    => '09:00',
    ]);
    insertSugarRow('order-roi-nores', 'row-roi-nores', [
        'duration'                   => 45,
        'pcrm_partnerresources_id_c' => 'non-existent-resource-uuid',
        'datum_onderzoek'            => '2025-07-15 09:00:00',
    ]);
    linkRowToContact('row-roi-nores', 'contact-roi-nores');
    linkOrderToSugarLead('order-roi-nores', 'sugar-lead-roi-nores');

    expect(runOrderImport())->toBe(0);

    $order = Order::where('external_id', 'order-roi-nores')->first();
    expect($order)->not->toBeNull()
        ->and($order->orderItems)->toHaveCount(1);

    expect(ResourceOrderItem::whereHas('orderItem', fn ($q) => $q->where('order_id', $order->id))->count())->toBe(0);

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('ImportOrdersFromSugarCRM: resource not found for order row', Mockery::on(fn ($ctx) => ($ctx['resource_external_id'] ?? null) === 'non-existent-resource-uuid'));
});

test('creates ResourceOrderItems using each row examination date and time', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-roi-rows']);
    Person::factory()->create(['external_id' => 'contact-roi-rows']);
    $resource = Resource::factory()->create(['external_id' => 'sugar-resource-uuid-rows']);

    insertSugarOrder('order-roi-rows', [
        'datum_onderzoek_1' => '2025-07-15',
        'aankomsttijd_c'    => '09:00',
    ]);
    insertSugarRow('order-roi-rows', 'row-roi-a', [
        'duration'                   => 60,
        'pcrm_partnerresources_id_c' => 'sugar-resource-uuid-rows',
        'datum_onderzoek'            => '2025-07-15 07:00:00', // UTC → 09:00 Amsterdam (CEST)
    ]);
    insertSugarRow('order-roi-rows', 'row-roi-b', [
        'duration'                   => 30,
        'pcrm_partnerresources_id_c' => 'sugar-resource-uuid-rows',
        'datum_onderzoek'            => '2025-07-20 12:00:00', // UTC → 14:00 Amsterdam (CEST)
    ]);
    linkRowToContact('row-roi-a', 'contact-roi-rows');
    linkRowToContact('row-roi-b', 'contact-roi-rows');
    linkOrderToSugarLead('order-roi-rows', 'sugar-lead-roi-rows');

    expect(runOrderImport())->toBe(0);

    $order = Order::where('external_id', 'order-roi-rows')->first();
    expect($order)->not->toBeNull()
        ->and($order->first_examination_at->format('Y-m-d'))->toBe('2025-07-15')
        ->and($order->first_examination_time)->toBe('09:00');

    $rowA = ResourceOrderItem::whereHas('orderItem', fn ($q) => $q->where('order_id', $order->id))
        ->where('from', '2025-07-15 09:00:00')
        ->first();
    $rowB = ResourceOrderItem::whereHas('orderItem', fn ($q) => $q->where('order_id', $order->id))
        ->where('from', '2025-07-20 14:00:00')
        ->first();

    expect($rowA)->not->toBeNull()
        ->and($rowA->resource_id)->toBe($resource->id)
        ->and($rowA->from->format('Y-m-d H:i:s'))->toBe('2025-07-15 09:00:00')
        ->and($rowA->to->format('Y-m-d H:i:s'))->toBe('2025-07-15 10:00:00')
        ->and($rowB)->not->toBeNull()
        ->and($rowB->resource_id)->toBe($resource->id)
        ->and($rowB->from->format('Y-m-d H:i:s'))->toBe('2025-07-20 14:00:00')
        ->and($rowB->to->format('Y-m-d H:i:s'))->toBe('2025-07-20 14:30:00');

});

// ─── purchase price mapping: main row table fields ────────────────────────────

test('MAIN purchase uses sor.purchase_clinic when cstm purchase fields are zero (order 202502011 scenario)', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-clinic-main']);

    insertSugarOrder('order-clinic-main');
    // purchase_clinic=5900 lives on the main row table; all cstm purchase fields are 0
    insertSugarRow('order-clinic-main', 'row-clinic-main', [
        'purchase_price'  => 5900,
        'purchase_clinic' => 5900,
    ]);
    insertSugarRowCstm('row-clinic-main', [
        'purchase_other_c'  => 0,
        'purchase_cardio_c' => 0,
        'purchase_radio_c'  => 0,
    ]);
    linkOrderToSugarLead('order-clinic-main', 'sugar-lead-clinic-main');

    runOrderImport();

    $item = Order::where('external_id', 'order-clinic-main')->first()->orderItems->first();
    expect((float) $item->purchasePrice->purchase_price)->toBe(5900.0)
        ->and((float) $item->purchasePrice->purchase_price_clinic)->toBe(5900.0)
        ->and((float) $item->purchasePrice->purchase_price_misc)->toBe(0.0)
        ->and((float) $item->purchasePrice->purchase_price_doctor)->toBe(0.0)
        ->and((float) $item->purchasePrice->purchase_price_radiology)->toBe(0.0);
});

test('MAIN purchase uses sor.purchase_doctor', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-doctor-main']);

    insertSugarOrder('order-doctor-main');
    insertSugarRow('order-doctor-main', 'row-doctor-main', [
        'purchase_price'  => 150,
        'purchase_doctor' => 150,
    ]);
    linkOrderToSugarLead('order-doctor-main', 'sugar-lead-doctor-main');

    runOrderImport();

    $item = Order::where('external_id', 'order-doctor-main')->first()->orderItems->first();
    expect((float) $item->purchasePrice->purchase_price)->toBe(150.0)
        ->and((float) $item->purchasePrice->purchase_price_doctor)->toBe(150.0)
        ->and((float) $item->purchasePrice->purchase_price_misc)->toBe(0.0);
});

test('MAIN purchase combines sor.purchase_clinic with cstm purchase_other_c (202502011 two-row scenario)', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-combo-main']);

    Product::factory()->create(['name' => 'Vertaling artsbrief']);

    insertSugarOrder('order-combo-main', ['amount' => 14050]);

    // Row 1: inkoop 5900 op purchase_clinic (main table)
    insertSugarRow('order-combo-main', 'row-combo-1', [
        'name'            => 'TB1 Business Class',
        'sales_price'     => 13900,
        'purchase_price'  => 5900,
        'purchase_clinic' => 5900,
    ]);
    insertSugarRowCstm('row-combo-1', [
        'purchase_other_c'  => 0,
        'purchase_cardio_c' => 0,
        'purchase_radio_c'  => 0,
    ]);

    // Row 2: inkoop 80 op purchase_other_c (cstm)
    insertSugarRow('order-combo-main', 'row-combo-2', [
        'name'           => 'Vertaling artsbrief',
        'sales_price'    => 150,
        'purchase_price' => 80,
    ]);
    insertSugarRowCstm('row-combo-2', [
        'purchase_other_c' => 80,
    ]);

    linkOrderToSugarLead('order-combo-main', 'sugar-lead-combo-main');

    runOrderImport();

    $order = Order::where('external_id', 'order-combo-main')->first();
    $items = $order->orderItems->keyBy('name');

    $row1 = $items['TB1 Business Class'] ?? $items->first();
    $row2 = $items->last();

    expect((float) $row1->purchasePrice->purchase_price)->toBe(5900.0)
        ->and((float) $row1->purchasePrice->purchase_price_clinic)->toBe(5900.0)
        ->and((float) $row2->purchasePrice->purchase_price)->toBe(80.0)
        ->and((float) $row2->purchasePrice->purchase_price_misc)->toBe(80.0);
});

test('MAIN purchase: remainder from sor.purchase_price minus components lands in misc', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-remainder']);

    insertSugarOrder('order-remainder');
    insertSugarRow('order-remainder', 'row-remainder', [
        'purchase_price'  => 200,
        'purchase_clinic' => 150,
    ]);
    insertSugarRowCstm('row-remainder', [
        'purchase_cardio_c' => 30,
        // 200 - 150 - 30 = 20 remainder → misc
    ]);
    linkOrderToSugarLead('order-remainder', 'sugar-lead-remainder');

    runOrderImport();

    $pp = Order::where('external_id', 'order-remainder')->first()->orderItems->first()->purchasePrice;
    expect((float) $pp->purchase_price)->toBe(200.0)
        ->and((float) $pp->purchase_price_clinic)->toBe(150.0)
        ->and((float) $pp->purchase_price_cardiology)->toBe(30.0)
        ->and((float) $pp->purchase_price_misc)->toBe(20.0);
});

// ─── purchase price mapping: invoice (aflettering) ───────────────────────────

test('invoice import includes inv_purchase_doctor_c when ink_doctor_status_c is afgerond', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-inv-doctor-afg']);

    insertSugarOrder('order-inv-doctor-afg');
    insertSugarRow('order-inv-doctor-afg', 'row-inv-doctor-afg', ['purchase_price' => 100, 'purchase_doctor' => 100]);
    insertSugarRowCstm('row-inv-doctor-afg', [
        'inv_purchase_doctor_c' => 100,
        'ink_doctor_status_c'   => 'afgerond',
        'inv_purchase_total_c'  => 100,
        'ink_total_status_c'    => 'afgerond',
    ]);
    linkOrderToSugarLead('order-inv-doctor-afg', 'sugar-lead-inv-doctor-afg');

    runOrderImport();

    $item = Order::where('external_id', 'order-inv-doctor-afg')->first()->orderItems->first();
    expect((float) $item->invoicePurchasePrice->purchase_price_doctor)->toBe(100.0)
        ->and((float) $item->invoicePurchasePrice->purchase_price)->toBe(100.0)
        ->and((float) $item->invoicePurchasePrice->purchase_price_misc)->toBe(0.0);
});

test('invoice import blocks inv_purchase_doctor_c when ink_doctor_status_c is open', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-inv-doctor-open']);

    insertSugarOrder('order-inv-doctor-open');
    insertSugarRow('order-inv-doctor-open', 'row-inv-doctor-open', ['purchase_price' => 100, 'purchase_doctor' => 100]);
    insertSugarRowCstm('row-inv-doctor-open', [
        'inv_purchase_doctor_c' => 100,
        'ink_doctor_status_c'   => 'open',
        'inv_purchase_total_c'  => 100,
    ]);
    linkOrderToSugarLead('order-inv-doctor-open', 'sugar-lead-inv-doctor-open');

    runOrderImport();

    $item = Order::where('external_id', 'order-inv-doctor-open')->first()->orderItems->first();
    // doctor blocked; total also blocked because only non-empty status is "open"
    expect((float) $item->invoicePurchasePrice->purchase_price_doctor)->toBe(0.0)
        ->and((float) $item->invoicePurchasePrice->purchase_price)->toBe(0.0);
});

test('invoice import includes inv_purchase_clinic_c when ink_clinic_status_c is afgerond', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-inv-clinic-afg']);

    insertSugarOrder('order-inv-clinic-afg');
    insertSugarRow('order-inv-clinic-afg', 'row-inv-clinic-afg', ['purchase_price' => 5900, 'purchase_clinic' => 5900]);
    insertSugarRowCstm('row-inv-clinic-afg', [
        'inv_purchase_clinic_c' => 5900,
        'ink_clinic_status_c'   => 'afgerond',
        'inv_purchase_total_c'  => 5900,
        'ink_total_status_c'    => 'afgerond',
    ]);
    linkOrderToSugarLead('order-inv-clinic-afg', 'sugar-lead-inv-clinic-afg');

    runOrderImport();

    $item = Order::where('external_id', 'order-inv-clinic-afg')->first()->orderItems->first();
    expect((float) $item->invoicePurchasePrice->purchase_price_clinic)->toBe(5900.0)
        ->and((float) $item->invoicePurchasePrice->purchase_price)->toBe(5900.0)
        ->and((float) $item->invoicePurchasePrice->purchase_price_misc)->toBe(0.0);
});

test('invoice purchase price has force_received=true (already processed old data)', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-fr']);

    insertSugarOrder('order-fr', ['order_num' => '999001']);
    insertSugarRow('order-fr', 'row-fr', [
        'purchase_price' => 200.00,
    ]);
    insertSugarRowCstm('row-fr', [
        'inv_purchase_other_c' => 200.00,
        'ink_other_status_c'   => 'afgerond',
        'inv_purchase_total_c' => 200.00,
        'ink_total_status_c'   => 'afgerond',
    ]);
    linkOrderToSugarLead('order-fr', 'sugar-lead-fr');

    runOrderImport();

    $item = Order::where('external_id', 'order-fr')->first()->orderItems->first();
    expect($item->invoicePurchasePrice->force_received)->toBeTrue()
        ->and($item->purchasePrice->force_received)->toBeFalse();
});

// ─── repair purchase prices ─────────────────────────────────────────────────

function runRepairPurchasePrices(array $args = []): int
{
    return Artisan::call('orders:repair-sugar-purchase-prices', array_merge([
        '--connection' => 'sugarcrm',
    ], $args));
}

test('repair command toont afwijkende inkoopprijzen in tabel (standaard rapport)', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-repair-report']);

    Product::factory()->create(['name' => 'Vertaling artsbrief']);

    insertSugarOrder('order-repair-report', ['order_num' => 202502011, 'amount' => 14050]);
    insertSugarRow('order-repair-report', 'row-repair-1', [
        'name'            => 'TB1 Business Class',
        'sales_price'     => 13900,
        'purchase_price'  => 5900,
        'purchase_clinic' => 5900,
    ]);
    insertSugarRowCstm('order-repair-1', [
        'purchase_other_c'  => 0,
        'purchase_cardio_c' => 0,
        'purchase_radio_c'  => 0,
    ]);
    insertSugarRow('order-repair-report', 'row-repair-2', [
        'name'           => 'Vertaling artsbrief',
        'sales_price'    => 150,
        'purchase_price' => 80,
    ]);
    insertSugarRowCstm('row-repair-2', [
        'purchase_other_c' => 80,
    ]);
    linkOrderToSugarLead('order-repair-report', 'sugar-lead-repair-report');

    runOrderImport(['--order-ids' => '202502011']);

    $order = Order::where('external_id', 'order-repair-report')->first();
    $clinicItem = $order->orderItems->firstWhere('name', 'TB1 Business Class')
        ?? $order->orderItems->first();

    // Simuleer fout uit oude import: alleen cstm-other werd meegenomen
    $clinicItem->purchasePrice()->update([
        'purchase_price'        => 0,
        'purchase_price_clinic' => 0,
        'purchase_price_misc'   => 0,
    ]);

    $exitCode = runRepairPurchasePrices(['--order-nums' => '202502011']);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('202502011')
        ->and($output)->toContain('5.900,00')
        ->and($output)->toContain('0,00')
        ->and($output)->toContain('--apply');

    expect((float) $clinicItem->fresh()->purchasePrice->purchase_price)->toBe(0.0);
});

test('repair command past inkoopprijzen toe met --apply', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-repair-apply']);

    insertSugarOrder('order-repair-apply', ['order_num' => 202502099]);
    insertSugarRow('order-repair-apply', 'row-repair-apply', [
        'purchase_price'  => 5900,
        'purchase_clinic' => 5900,
    ]);
    insertSugarRowCstm('row-repair-apply', [
        'purchase_other_c'  => 0,
        'purchase_cardio_c' => 0,
        'purchase_radio_c'  => 0,
    ]);
    linkOrderToSugarLead('order-repair-apply', 'sugar-lead-repair-apply');

    runOrderImport(['--order-ids' => '202502099']);

    $order = Order::where('external_id', 'order-repair-apply')->first();
    $item = $order->orderItems->first();
    $item->purchasePrice()->update([
        'purchase_price'        => 80,
        'purchase_price_clinic' => 0,
        'purchase_price_misc'   => 80,
    ]);

    $exitCode = runRepairPurchasePrices([
        '--order-nums' => '202502099',
        '--apply'      => true,
    ]);

    expect($exitCode)->toBe(0);

    $item->refresh();
    expect((float) $item->purchasePrice->purchase_price)->toBe(5900.0)
        ->and((float) $item->purchasePrice->purchase_price_clinic)->toBe(5900.0)
        ->and((float) $item->purchasePrice->purchase_price_misc)->toBe(0.0);
});

test('repair command meldt geen afwijkingen wanneer prijzen al correct zijn', function () {
    Lead::factory()->create(['external_id' => 'sugar-lead-repair-ok']);

    insertSugarOrder('order-repair-ok', ['order_num' => 202502088]);
    insertSugarRow('order-repair-ok', 'row-repair-ok', [
        'purchase_price'  => 150,
        'purchase_doctor' => 150,
    ]);
    linkOrderToSugarLead('order-repair-ok', 'sugar-lead-repair-ok');

    runOrderImport(['--order-ids' => '202502088']);

    $exitCode = runRepairPurchasePrices(['--order-nums' => '202502088']);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Geen afwijkende inkoopprijzen gevonden.');
});
