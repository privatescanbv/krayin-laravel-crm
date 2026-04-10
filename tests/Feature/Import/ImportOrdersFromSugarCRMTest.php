<?php

namespace Tests\Feature;

use App\Enums\LostReason;
use App\Enums\OrderItemStatus;
use App\Enums\PipelineStage;
use App\Models\Anamnesis;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        'pcrm_salesorderrow',
        'pcrm_salesorder_cstm',
        'pcrm_salesorder',
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
        $table->integer('deleted')->default(0);
    });

    Schema::connection('sugarcrm')->create('pcrm_salesorder_cstm', function (Blueprint $table) {
        $table->string('id_c')->primary();
        $table->string('reden_afvoeren_c')->nullable();
        $table->tinyInteger('op_een_factuur_c')->default(0);
        $table->string('d_wfl_status_c')->nullable();
    });

    Schema::connection('sugarcrm')->create('pcrm_salesorderrow', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('name')->nullable();
        $table->double('sales_price')->nullable();
        $table->string('sales_stage')->nullable();
        $table->string('resource_type')->nullable();
        $table->string('producttemplate_id_c')->nullable();
        $table->integer('deleted')->default(0);
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
});

// ─── helpers ────────────────────────────────────────────────────────────────

function insertSugarOrder(string $id, array $overrides = []): void
{
    // Fields belonging to pcrm_salesorder_cstm, not pcrm_salesorder
    $cstmKeys = ['reden_afvoeren_c', 'op_een_factuur_c', 'd_wfl_status_c'];

    $cstmOverrides = array_intersect_key($overrides, array_flip($cstmKeys));
    $soOverrides = array_diff_key($overrides, array_flip($cstmKeys));

    DB::connection('sugarcrm')->table('pcrm_salesorder')->insert(array_merge([
        'id'            => $id,
        'name'          => "Order {$id}",
        'order_num'     => 202500001,
        'amount'        => 1500.00,
        'sales_stage'   => 'Gewonnen',
        'date_closed'   => '2025-06-01',
        'date_entered'  => '2025-01-15 10:00:00',
        'date_modified' => '2025-01-15 10:00:00',
        'deleted'       => 0,
    ], $soOverrides));

    DB::connection('sugarcrm')->table('pcrm_salesorder_cstm')->insert(array_merge([
        'id_c'             => $id,
        'reden_afvoeren_c' => null,
        'op_een_factuur_c' => 0,
        'd_wfl_status_c'   => 'eindeproces',
    ], $cstmOverrides));
}

function insertSugarRow(string $orderId, string $rowId, array $overrides = []): void
{
    DB::connection('sugarcrm')->table('pcrm_salesorderrow')->insert(array_merge([
        'id'          => $rowId,
        'name'        => 'TB1 Business Class',
        'sales_price' => 1500.00,
        'sales_stage' => 'Gewonnen',
        'deleted'     => 0,
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

function linkOrderToSugarLead(string $orderId, string $sugarLeadId): void
{
    DB::connection('sugarcrm')->table('leads_pcrm_salesorder_c')->insert([
        'leads_p903eeads_ida' => $sugarLeadId,
        'leads_p5ae2rder_idb' => $orderId,
        'deleted'             => 0,
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
        ->and($salesLead->lead_id)->toBe($lead->id);

    // Person attached to SalesLead
    expect($salesLead->persons->contains($person))->toBeTrue();

    expect(Anamnesis::query()
        ->where('sales_id', $salesLead->id)
        ->where('person_id', $person->id)
        ->exists())->toBeTrue();

    // OrderItem created with correct status
    expect($order->orderItems)->toHaveCount(1);
    $item = $order->orderItems->first();
    expect($item->status)->toBe(OrderItemStatus::WON)
        ->and($item->person_id)->toBe($person->id)
        ->and((float) $item->total_price)->toBe(1500.0);
});

test('imports lost order with lost reason', function () {
    insertSugarOrder('order-lost-001', [
        'sales_stage'      => 'Verloren',
        'reden_afvoeren_c' => 'prijs',
    ]);

    $exit = runOrderImport();

    expect($exit)->toBe(0);

    $order = Order::where('external_id', 'order-lost-001')->first();
    expect($order)->not->toBeNull()
        ->and($order->pipeline_stage_id)->toBe(PipelineStage::ORDER_VERLOREN->id())
        ->and($order->lost_reason)->toBe(LostReason::Price);

    expect($order->salesLead->pipeline_stage_id)
        ->toBe(PipelineStage::SALES_NIET_SUCCESVOL_AFGEROND->id());
});

test('unknown sales_stage maps to first order stage', function () {
    insertSugarOrder('order-optie-001', ['sales_stage' => 'Optie']);

    runOrderImport();

    $order = Order::where('external_id', 'order-optie-001')->first();
    expect($order->pipeline_stage_id)->toBe(PipelineStage::ORDER_CONFIRM->id());
    expect($order->salesLead->pipeline_stage_id)->toBe(PipelineStage::SALES_IN_BEHANDELING->id());
});

test('skips already imported order on re-run', function () {
    insertSugarOrder('order-dup-001');

    runOrderImport();
    runOrderImport(); // second run

    expect(Order::where('external_id', 'order-dup-001')->count())->toBe(1);
    expect(SalesLead::count())->toBe(1);
});

test('order with multiple rows and multiple persons creates multiple orderitems', function () {
    $personA = Person::factory()->create(['external_id' => 'contact-A']);
    $personB = Person::factory()->create(['external_id' => 'contact-B']);

    Product::factory()->create(['name' => 'Scan A']);
    Product::factory()->create(['name' => 'Scan B']);

    insertSugarOrder('order-multi-001', ['amount' => 3000]);
    insertSugarRow('order-multi-001', 'row-A', ['sales_price' => 1500, 'name' => 'Scan A']);
    insertSugarRow('order-multi-001', 'row-B', ['sales_price' => 1500, 'name' => 'Scan B']);
    linkRowToContact('row-A', 'contact-A');
    linkRowToContact('row-B', 'contact-B');

    runOrderImport();

    $order = Order::where('external_id', 'order-multi-001')->first();
    expect($order->orderItems)->toHaveCount(2);

    $personIds = $order->orderItems->pluck('person_id')->sort()->values();
    expect($personIds)->toContain($personA->id, $personB->id);
});

test('order row without matching person still imports with person_id null', function () {
    insertSugarOrder('order-noperson-001');
    insertSugarRow('order-noperson-001', 'row-noperson', []);
    linkRowToContact('row-noperson', 'contact-does-not-exist');

    runOrderImport();

    $order = Order::where('external_id', 'order-noperson-001')->first();
    expect($order)->not->toBeNull()
        ->and($order->orderItems)->toHaveCount(1)
        ->and($order->orderItems->first()->person_id)->toBeNull();
});

test('order row with matching product sets product_id on orderitem', function () {
    $product = Product::factory()->create(['external_id' => 'product-template-001']);

    insertSugarOrder('order-product-001');
    insertSugarRow('order-product-001', 'row-product-001', [
        'producttemplate_id_c' => 'product-template-001',
    ]);

    runOrderImport();

    $order = Order::where('external_id', 'order-product-001')->first();
    expect($order->orderItems->first()->product_id)->toBe($product->id);
});

test('order row resolves product by exact CRM name when template id has no match', function () {
    $product = Product::factory()->create(['name' => 'TB3 Regular Bodyscan + Wervelkolom zonder cardio']);

    insertSugarOrder('order-by-name-001');
    insertSugarRow('order-by-name-001', 'row-by-name-001', [
        'name'                 => 'TB3 Regular Bodyscan + Wervelkolom zonder cardio',
        'producttemplate_id_c' => 'no-such-template-in-crm',
        'sales_price'          => 1890,
    ]);

    runOrderImport();

    $order = Order::where('external_id', 'order-by-name-001')->first();
    expect($order)->not->toBeNull()
        ->and($order->orderItems)->toHaveCount(1)
        ->and($order->orderItems->first()->product_id)->toBe($product->id);
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
    insertSugarOrder('order-combined-001', ['op_een_factuur_c' => 1]);

    runOrderImport();

    $order = Order::where('external_id', 'order-combined-001')->first();
    expect($order->combine_order)->toBeTrue();
});

test('dry run does not persist any data', function () {
    insertSugarOrder('order-dryrun-001');

    $exit = runOrderImport(['--dry-run' => true]);

    expect($exit)->toBe(0)
        ->and(Order::where('external_id', 'order-dryrun-001')->exists())->toBeFalse()
        ->and(SalesLead::count())->toBe(0);
});
