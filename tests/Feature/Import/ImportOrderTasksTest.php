<?php

namespace Tests\Feature\Import;

use App\Console\Commands\AbstractSugarCRMImport;
use App\Enums\ActivityType;
use App\Models\Order;
use App\Models\SalesLead;
use App\Services\Importers\SugarCRM\ActivityImporter;
use Database\Seeders\TestSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Webkul\Activity\Models\Activity;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;

uses(RefreshDatabase::class);

beforeEach(function () {
    test()->seed(TestSeeder::class);

    Config::set('database.connections.sugarcrm', [
        'driver'   => 'sqlite',
        'database' => ':memory:',
        'prefix'   => '',
    ]);

    if (Schema::connection('sugarcrm')->hasTable('tasks')) {
        Schema::connection('sugarcrm')->drop('tasks');
    }

    Schema::connection('sugarcrm')->create('tasks', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('name')->nullable();
        $table->text('description')->nullable();
        $table->dateTime('date_entered')->nullable();
        $table->dateTime('date_modified')->nullable();
        $table->string('assigned_user_id')->nullable();
        $table->string('created_by')->nullable();
        $table->dateTime('date_start')->nullable();
        $table->dateTime('date_due')->nullable();
        $table->string('status')->nullable();
        $table->string('parent_type')->nullable();
        $table->string('parent_id')->nullable();
        $table->integer('deleted')->default(0);
    });
});

function makeOrderWithSalesLead(string $sugarOrderId): Order
{
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);

    return Order::factory()->create([
        'external_id'   => $sugarOrderId,
        'sales_lead_id' => $salesLead->id,
    ]);
}

function insertTask(string $taskId, string $orderId, array $overrides = []): void
{
    DB::connection('sugarcrm')->table('tasks')->insert(array_merge([
        'id'            => $taskId,
        'name'          => 'Test taak',
        'description'   => 'Taak beschrijving',
        'date_entered'  => '2025-03-01 09:00:00',
        'date_modified' => '2025-03-01 09:00:00',
        'date_start'    => '2025-03-10 09:00:00',
        'date_due'      => '2025-03-15 17:00:00',
        'status'        => 'Not Started',
        'parent_type'   => 'PCRM_SalesOrder',
        'parent_id'     => $orderId,
        'deleted'       => 0,
    ], $overrides));
}

function makeActivityImporter(): ActivityImporter
{
    $command = Mockery::mock(AbstractSugarCRMImport::class);
    $command->allows('infoV')->andReturnNull();
    $command->allows('infoVV')->andReturnNull();
    $command->allows('error')->andReturnNull();
    $command->allows('validateTableExists')->andReturnNull();

    return new ActivityImporter($command, 'sugarcrm');
}

test('importTaskActivitiesForOrder maakt activiteit aan gekoppeld aan order', function () {
    $order = makeOrderWithSalesLead('sugar-order-aaa');
    insertTask('sugar-task-001', 'sugar-order-aaa');

    $importer = makeActivityImporter();
    $taskActivities = $importer->extractTaskActivitiesForOrders(['sugar-order-aaa']);
    $stats = $importer->importTaskActivitiesForOrder($order, $taskActivities);

    expect($stats['imported'])->toBe(1)
        ->and($stats['skipped'])->toBe(0);

    $activity = Activity::where('external_id', 'sugar-task-001')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->order_id)->toBe($order->id)
        ->and($activity->sales_lead_id)->toBeNull()
        ->and($activity->type)->toBe(ActivityType::TASK)
        ->and($activity->title)->toBe('Test taak');
});

test('importTaskActivitiesForOrder slaat bestaande taak over', function () {
    $order = makeOrderWithSalesLead('sugar-order-bbb');
    insertTask('sugar-task-002', 'sugar-order-bbb');

    $importer = makeActivityImporter();
    $taskActivities = $importer->extractTaskActivitiesForOrders(['sugar-order-bbb']);

    $importer->importTaskActivitiesForOrder($order, $taskActivities);
    $stats = $importer->importTaskActivitiesForOrder($order, $taskActivities);

    expect($stats['imported'])->toBe(0)
        ->and($stats['skipped'])->toBe(1);
    expect(Activity::where('external_id', 'sugar-task-002')->count())->toBe(1);
});

test('importTaskActivitiesForOrder koppelt bestaande taak opnieuw aan nieuw order', function () {
    $oldOrder = makeOrderWithSalesLead('sugar-order-old');
    $newOrder = makeOrderWithSalesLead('sugar-order-new');
    insertTask('sugar-task-relink', 'sugar-order-new');

    $importer = makeActivityImporter();

    // Import task under the old order so it exists with wrong order_id
    $oldActivities = ['sugar-order-old' => [
        (object) DB::connection('sugarcrm')->table('tasks')->where('id', 'sugar-task-relink')->first(),
    ]];
    $importer->importTaskActivitiesForOrder($oldOrder, $oldActivities);

    $activity = Activity::where('external_id', 'sugar-task-relink')->first();
    expect($activity->order_id)->toBe($oldOrder->id);

    // Now import under the correct (new) order — should re-link
    $newActivities = $importer->extractTaskActivitiesForOrders(['sugar-order-new']);
    $stats = $importer->importTaskActivitiesForOrder($newOrder, $newActivities);

    expect($stats['skipped'])->toBe(1);
    expect(Activity::where('external_id', 'sugar-task-relink')->first()->order_id)->toBe($newOrder->id);
});

test('import:orders --tasks-only importeert taken voor bestaande orders', function () {
    $order = makeOrderWithSalesLead('sugar-order-ccc');
    insertTask('sugar-task-003', 'sugar-order-ccc');

    $this->artisan('import:orders', [
        '--tasks-only'        => true,
        '--connection'        => 'sugarcrm',
        '--tasks-parent-type' => 'PCRM_SalesOrder',
    ])->assertSuccessful();

    $activity = Activity::where('external_id', 'sugar-task-003')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->order_id)->toBe($order->id)
        ->and($activity->type)->toBe(ActivityType::TASK);
});

test('--tasks-only importeert ook taken voor gewonnen en verloren orders', function () {
    $wonStage = Stage::where('is_won', true)->first();
    $wonOrder = makeOrderWithSalesLead('sugar-order-won-x');
    $wonOrder->update(['pipeline_stage_id' => $wonStage?->id]);
    insertTask('sugar-task-won-x', 'sugar-order-won-x');

    $lostStage = Stage::where('is_lost', true)->first();
    $lostOrder = makeOrderWithSalesLead('sugar-order-lost-x');
    $lostOrder->update(['pipeline_stage_id' => $lostStage?->id]);
    insertTask('sugar-task-lost-x', 'sugar-order-lost-x');

    $this->artisan('import:orders', [
        '--tasks-only'        => true,
        '--connection'        => 'sugarcrm',
        '--tasks-parent-type' => 'PCRM_SalesOrder',
    ])->assertSuccessful();

    expect(Activity::where('external_id', 'sugar-task-won-x')->count())->toBe(1);
    expect(Activity::where('external_id', 'sugar-task-lost-x')->count())->toBe(1);
});
