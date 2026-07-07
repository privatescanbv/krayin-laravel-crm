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
use Webkul\Email\Models\Email;
use Webkul\Lead\Models\Lead;

uses(RefreshDatabase::class);

beforeEach(function () {
    test()->seed(TestSeeder::class);

    Config::set('database.connections.sugarcrm', [
        'driver'   => 'sqlite',
        'database' => ':memory:',
        'prefix'   => '',
    ]);

    foreach (['emails', 'emails_text', 'notes', 'calls', 'calls_cstm', 'tasks'] as $table) {
        if (Schema::connection('sugarcrm')->hasTable($table)) {
            Schema::connection('sugarcrm')->drop($table);
        }
    }

    // Tasks table is required for the --tasks-only command path (which also triggers
    // the order email/note/call orchestration under test here).
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

    Schema::connection('sugarcrm')->create('emails', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('name')->nullable();
        $table->dateTime('date_entered')->nullable();
        $table->dateTime('date_modified')->nullable();
        $table->string('assigned_user_id')->nullable();
        $table->string('created_by')->nullable();
        $table->dateTime('date_sent')->nullable();
        $table->string('message_id')->nullable();
        $table->string('type')->nullable();
        $table->string('status')->nullable();
        $table->integer('flagged')->nullable();
        $table->string('reply_to_status')->nullable();
        $table->string('intent')->nullable();
        $table->string('mailbox_id')->nullable();
        $table->string('parent_type')->nullable();
        $table->string('parent_id')->nullable();
        $table->integer('deleted')->default(0);
    });

    Schema::connection('sugarcrm')->create('emails_text', function (Blueprint $table) {
        $table->string('email_id')->primary();
        $table->text('description')->nullable();
        $table->text('description_html')->nullable();
        $table->text('raw_source')->nullable();
    });

    Schema::connection('sugarcrm')->create('notes', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('name')->nullable();
        $table->dateTime('date_entered')->nullable();
        $table->dateTime('date_modified')->nullable();
        $table->string('assigned_user_id')->nullable();
        $table->string('created_by')->nullable();
        $table->text('description')->nullable();
        $table->string('parent_type')->nullable();
        $table->string('parent_id')->nullable();
        $table->string('filename')->nullable();
        $table->integer('deleted')->default(0);
    });

    Schema::connection('sugarcrm')->create('calls', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('name')->nullable();
        $table->dateTime('date_entered')->nullable();
        $table->dateTime('date_modified')->nullable();
        $table->string('assigned_user_id')->nullable();
        $table->string('created_by')->nullable();
        $table->text('description')->nullable();
        $table->dateTime('date_start')->nullable();
        $table->dateTime('date_end')->nullable();
        $table->string('parent_type')->nullable();
        $table->string('status')->nullable();
        $table->string('direction')->nullable();
        $table->string('parent_id')->nullable();
        $table->integer('deleted')->default(0);
    });

    Schema::connection('sugarcrm')->create('calls_cstm', function (Blueprint $table) {
        $table->string('id_c')->primary();
        $table->string('belgroep_c')->nullable();
    });
});

function makeOrderForActivities(string $sugarOrderId): Order
{
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);

    return Order::factory()->create([
        'external_id'   => $sugarOrderId,
        'sales_lead_id' => $salesLead->id,
    ]);
}

function orderActivityImporter(): ActivityImporter
{
    $command = Mockery::mock(AbstractSugarCRMImport::class);
    $command->allows('infoV')->andReturnNull();
    $command->allows('infoVV')->andReturnNull();
    $command->allows('info')->andReturnNull();
    $command->allows('error')->andReturnNull();
    $command->allows('validateTableExists')->andReturnNull();

    return new ActivityImporter($command, 'sugarcrm');
}

function insertOrderEmail(string $id, string $orderId, array $overrides = []): void
{
    DB::connection('sugarcrm')->table('emails')->insert(array_merge([
        'id'            => $id,
        'name'          => 'Afspraakbevestiging',
        'date_entered'  => '2022-01-10 09:00:00',
        'date_modified' => '2022-01-10 09:00:00',
        'date_sent'     => '2022-01-10 09:00:00',
        'message_id'    => 'msg-'.$id,
        'status'        => 'sent',
        'parent_type'   => 'PCRM_SalesOrder',
        'parent_id'     => $orderId,
        'deleted'       => 0,
    ], $overrides));

    DB::connection('sugarcrm')->table('emails_text')->insert([
        'email_id'         => $id,
        'description'      => 'Platte tekst body',
        'description_html' => '<p>HTML body</p>',
    ]);
}

test('importEmailsForOrder maakt e-mailrecords gekoppeld aan order', function () {
    $order = makeOrderForActivities('sugar-order-mail');
    insertOrderEmail('mail-1', 'sugar-order-mail');
    insertOrderEmail('mail-2', 'sugar-order-mail', ['name' => 'Factuur']);

    $importer = orderActivityImporter();
    $emails = $importer->extractEmailActivitiesForOrders(['sugar-order-mail']);
    $stats = $importer->importEmailsForOrder($order, $emails);

    expect($stats['imported'])->toBe(2)
        ->and($stats['skipped'])->toBe(0);

    $email = Email::where('unique_id', 'mail-1')->first();
    expect($email)->not->toBeNull()
        ->and($email->order_id)->toBe($order->id)
        ->and($email->sales_lead_id)->toBe($order->sales_lead_id);
});

test('importEmailsForOrder valt terug op sugar id wanneer message_id null is', function () {
    $order = makeOrderForActivities('sugar-order-nullmsg');
    insertOrderEmail('mail-nullmsg', 'sugar-order-nullmsg', ['message_id' => null]);

    $importer = orderActivityImporter();
    $emails = $importer->extractEmailActivitiesForOrders(['sugar-order-nullmsg']);
    $stats = $importer->importEmailsForOrder($order, $emails);

    expect($stats['imported'])->toBe(1);

    $email = Email::where('unique_id', 'mail-nullmsg')->first();
    expect($email)->not->toBeNull()
        ->and($email->message_id)->toBe('mail-nullmsg');
});

test('importEmailsForOrder is idempotent en herkoppelt naar nieuw order', function () {
    $orderA = makeOrderForActivities('sugar-order-mA');
    $orderB = makeOrderForActivities('sugar-order-mB');
    insertOrderEmail('mail-relink', 'sugar-order-mB');

    $importer = orderActivityImporter();

    // First import under order A directly
    $asA = ['sugar-order-mA' => [(object) DB::connection('sugarcrm')->table('emails')
        ->join('emails_text', 'emails.id', '=', 'emails_text.email_id')
        ->where('emails.id', 'mail-relink')->first()]];
    $importer->importEmailsForOrder($orderA, $asA);
    expect(Email::where('unique_id', 'mail-relink')->first()->order_id)->toBe($orderA->id);

    // Now import under correct order B — should re-link, not duplicate
    $emailsB = $importer->extractEmailActivitiesForOrders(['sugar-order-mB']);
    $stats = $importer->importEmailsForOrder($orderB, $emailsB);

    expect($stats['skipped'])->toBe(1);
    expect(Email::where('unique_id', 'mail-relink')->count())->toBe(1);
    expect(Email::where('unique_id', 'mail-relink')->first()->order_id)->toBe($orderB->id);
});

test('importNoteActivitiesForOrder maakt notitie-activiteit gekoppeld aan order', function () {
    $order = makeOrderForActivities('sugar-order-note');
    DB::connection('sugarcrm')->table('notes')->insert([
        'id'            => 'note-1',
        'name'          => 'MRI+CT: advies controle over een jaar',
        'date_entered'  => '2022-01-12 10:00:00',
        'date_modified' => '2022-01-12 10:00:00',
        'parent_type'   => 'PCRM_SalesOrder',
        'parent_id'     => 'sugar-order-note',
        'filename'      => '',
        'deleted'       => 0,
    ]);

    $importer = orderActivityImporter();
    $notes = $importer->extractNoteActivitiesForOrders(['sugar-order-note']);
    $stats = $importer->importNoteActivitiesForOrder($order, $notes);

    expect($stats['imported'])->toBe(1);

    $activity = Activity::where('external_id', 'note-1')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->order_id)->toBe($order->id)
        ->and($activity->type)->toBe(ActivityType::NOTE)
        ->and($activity->comment)->toContain('MRI+CT');
});

test('import:orders --tasks-only importeert mails, notities en calls van de order', function () {
    $order = makeOrderForActivities('sugar-order-e2e');

    insertOrderEmail('mail-e2e', 'sugar-order-e2e');

    DB::connection('sugarcrm')->table('notes')->insert([
        'id'          => 'note-e2e',
        'name'        => 'Notitie tekst',
        'parent_type' => 'PCRM_SalesOrder',
        'parent_id'   => 'sugar-order-e2e',
        'deleted'     => 0,
    ]);

    DB::connection('sugarcrm')->table('calls')->insert([
        'id'          => 'call-e2e',
        'name'        => 'Gebeld',
        'status'      => 'Held',
        'direction'   => 'Outbound',
        'parent_type' => 'PCRM_SalesOrder',
        'parent_id'   => 'sugar-order-e2e',
        'deleted'     => 0,
    ]);

    $this->artisan('import:orders', [
        '--tasks-only'        => true,
        '--connection'        => 'sugarcrm',
        '--tasks-parent-type' => 'PCRM_SalesOrder',
    ])->assertSuccessful();

    expect(Email::where('unique_id', 'mail-e2e')->where('order_id', $order->id)->count())->toBe(1)
        ->and(Activity::where('external_id', 'note-e2e')->where('order_id', $order->id)->where('type', ActivityType::NOTE)->count())->toBe(1)
        ->and(Activity::where('external_id', 'call-e2e')->where('order_id', $order->id)->where('type', ActivityType::CALL)->count())->toBe(1);
});

test('importCallActivitiesForOrder maakt bel-activiteit gekoppeld aan order', function () {
    $order = makeOrderForActivities('sugar-order-call');
    DB::connection('sugarcrm')->table('calls')->insert([
        'id'            => 'call-1',
        'name'          => 'Nagebeld voor akkoord',
        'date_entered'  => '2022-01-11 11:00:00',
        'date_modified' => '2022-01-11 11:00:00',
        'date_start'    => '2022-01-11 11:00:00',
        'date_end'      => '2022-01-11 11:15:00',
        'status'        => 'Held',
        'direction'     => 'Outbound',
        'parent_type'   => 'PCRM_SalesOrder',
        'parent_id'     => 'sugar-order-call',
        'deleted'       => 0,
    ]);

    $importer = orderActivityImporter();
    $calls = $importer->extractCallActivitiesForOrders(['sugar-order-call']);
    $stats = $importer->importCallActivitiesForOrder($order, $calls);

    expect($stats['imported'])->toBe(1);

    $activity = Activity::where('external_id', 'call-1')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->order_id)->toBe($order->id)
        ->and($activity->type)->toBe(ActivityType::CALL)
        ->and($activity->is_done)->toBeTrue();
});
