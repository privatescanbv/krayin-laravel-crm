<?php

use App\Enums\PipelineStage;
use App\Models\Anamnesis;
use App\Models\Order;
use App\Services\Repair\MissingOrderRestorer;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Email\Models\Email;
use Webkul\Lead\Models\Lead;
use Webkul\Product\Models\Product;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);

    // Never touch the real storage directory: it may hold the actual export files.
    $this->temporaryStorage = sys_get_temp_dir().'/missing-orders-'.uniqid();
    app()->useStoragePath($this->temporaryStorage);
    File::ensureDirectoryExists(dirname(storage_path(MissingOrderRestorer::DATA_FILE)));

    DB::table('folders')->updateOrInsert(['name' => 'Verwerkt']);

    // The repair only runs on databases that hold imported orders.
    $imported = Order::factory()->create();
    DB::table('orders')->where('id', $imported->id)->update(['external_id' => 'sugar-earlier-import']);

    $this->product = Product::factory()->create(['name' => 'PTED beoordeling']);
});

afterEach(function () {
    File::deleteDirectory($this->temporaryStorage);
});

function missingOrderRecord(array $overrides = []): array
{
    $prices = [
        'purchase_price_misc'       => 0,
        'purchase_price_doctor'     => 0,
        'purchase_price_cardiology' => 0,
        'purchase_price_clinic'     => 0,
        'purchase_price_radiology'  => 0,
        'purchase_price'            => 0,
    ];
    $dates = ['created_at' => '2026-01-07 13:06:31', 'updated_at' => '2026-08-04 14:49:56'];

    return array_replace([
        'order_number' => '202600044',
        'external_id'  => 'sugar-order',
        'persons'      => [[
            'external_id' => 'sugar-person',
            'row'         => ['first_name' => 'Test', 'last_name' => 'Patient', 'date_of_birth' => '1954-07-07', ...$dates],
        ]],
        'lead' => [
            'external_id'         => 'sugar-lead',
            'department'          => 'Herniapoli',
            'person_external_ids' => ['sugar-person'],
            'user_external_id'    => null,
            'row'                 => ['first_name' => 'Test', 'last_name' => 'Patient', 'description' => '', 'lead_channel_id' => 1, 'lead_type_id' => 1, 'lead_source_id' => 1, ...$dates],
            'address'             => ['street' => 'Dorpsstraat', 'house_number' => '33', 'postal_code' => '1186MB'],
        ],
        'organization' => null,
        'sales'        => ['name' => 'Test Patient', 'pipeline_stage' => PipelineStage::SALES_COMPLETE_HERNIA->value, 'closed_at' => '2026-02-07', ...$dates],
        'order'        => [
            'pipeline_stage'          => PipelineStage::ORDER_GEWONNEN_HERNIA->value,
            'user_external_id'        => null,
            'coordinator_external_id' => null,
            'row'                     => ['title' => '202600044_Test Patient', 'total_price' => 100, ...$dates],
        ],
        'items' => [[
            'external_id'                 => 'sugar-row',
            'partner_product_external_id' => null,
            'product_name'                => 'PTED beoordeling',
            'person_external_id'          => 'sugar-person',
            'row'                         => ['name' => 'PTED beoordeling', 'total_price' => 100, 'status' => 'won'],
            'booking'                     => null,
            'invoice'                     => $prices,
            'main'                        => $prices,
        ]],
        'payments' => [['amount' => 100, 'type' => 'advance', 'method' => 'bank', 'paid_at' => '2026-01-08', 'currency' => 'EUR']],
    ], $overrides);
}

function writeMissingOrderFiles(array $orders): void
{
    $task = [
        'kind'  => 'activity',
        'order' => 'sugar-order',
        'user'  => null,
        'group' => null,
        'row'   => ['external_id' => 'sugar-task', 'title' => 'Order bevestigen', 'type' => 'task', 'status' => 'done', 'is_done' => 1],
    ];
    $mail = [
        'kind'   => 'email',
        'order'  => 'sugar-order',
        'folder' => 'Verwerkt',
        'row'    => [
            'unique_id'  => 'sugar-mail',
            'message_id' => 'sugar-mail',
            'subject'    => 'Afspraakbevestiging',
            'source'     => 'web',
            'user_type'  => 'person',
            'from'       => json_encode(['name' => 'SugarCRM Import', 'email' => 'import@sugarcrm.local']),
        ],
    ];

    File::put(storage_path(MissingOrderRestorer::DATA_FILE), gzencode(json_encode(['orders' => $orders])));
    File::put(storage_path(MissingOrderRestorer::HISTORY_FILE), gzencode(json_encode($task)."\n".json_encode($mail)."\n"));
}

test('creates a missing person and lead, then the order with items, payment, tasks and mails once', function () {
    writeMissingOrderFiles([missingOrderRecord()]);

    expect(app(MissingOrderRestorer::class)->restore())->toBe(['orders' => 1, 'leads' => 1, 'persons' => 1]);

    $order = Order::where('external_id', 'sugar-order')->sole();
    $lead = Lead::where('external_id', 'sugar-lead')->sole();
    $person = Person::where('external_id', 'sugar-person')->sole();

    expect($order->order_number)->toBe('202600044')
        ->and($order->created_at->toDateTimeString())->toBe('2026-01-07 13:06:31')
        ->and($order->pipeline_stage_id)->toBe(PipelineStage::ORDER_GEWONNEN_HERNIA->id())
        ->and($order->salesLead->lead_id)->toBe($lead->id)
        ->and($order->salesLead->persons()->pluck('persons.id')->all())->toBe([$person->id])
        ->and($lead->department->name)->toBe('Herniapoli')
        ->and($lead->address_id)->not->toBeNull()
        ->and(Anamnesis::where('lead_id', $lead->id)->where('person_id', $person->id)->exists())->toBeTrue();

    $item = $order->orderItems()->sole();
    expect($item->product_id)->toBe($this->product->id)
        ->and($item->person_id)->toBe($person->id)
        ->and(DB::table('purchase_prices')->where('priceable_id', $item->id)->count())->toBe(2)
        ->and((float) DB::table('order_payments')->where('order_id', $order->id)->value('amount'))->toBe(100.0)
        ->and(Activity::where('external_id', 'sugar-task')->sole()->order_id)->toBe($order->id)
        ->and(Email::where('unique_id', 'sugar-mail')->sole()->order_id)->toBe($order->id);

    app(MissingOrderRestorer::class)->restore();

    expect(Order::where('external_id', 'sugar-order')->count())->toBe(1)
        ->and(Lead::where('external_id', 'sugar-lead')->count())->toBe(1);
});

test('reuses an existing lead and person, and creates the organization for a business order', function () {
    $person = Person::factory()->create(['external_id' => 'sugar-person']);
    $lead = Lead::factory()->create(['external_id' => 'sugar-lead']);
    $lead->attachPersons([$person->id]);

    writeMissingOrderFiles([missingOrderRecord(['organization' => ['name' => 'J.L.M. VOF', 'address' => null]])]);

    expect(app(MissingOrderRestorer::class)->restore())->toBe(['orders' => 1, 'leads' => 0, 'persons' => 0]);

    $order = Order::where('external_id', 'sugar-order')->sole();
    expect($order->salesLead->lead_id)->toBe($lead->id)
        ->and($order->is_business)->toBeTrue()
        ->and($order->organization->name)->toBe('J.L.M. VOF');
});

test('finds a person without the external id on birth date and last name', function () {
    $person = Person::factory()->create(['last_name' => 'Patient', 'date_of_birth' => '1954-07-07']);

    writeMissingOrderFiles([missingOrderRecord()]);

    expect(app(MissingOrderRestorer::class)->restore())->toBe(['orders' => 1, 'leads' => 1, 'persons' => 0])
        ->and(Order::where('external_id', 'sugar-order')->sole()->orderItems()->sole()->person_id)->toBe($person->id);
});

test('revert removes what was added and nothing else', function () {
    $person = Person::factory()->create(['external_id' => 'sugar-person']);
    writeMissingOrderFiles([missingOrderRecord()]);

    app(MissingOrderRestorer::class)->restore();
    app(MissingOrderRestorer::class)->revert();

    expect(Order::where('external_id', 'sugar-order')->exists())->toBeFalse()
        ->and(Lead::withTrashed()->where('external_id', 'sugar-lead')->exists())->toBeFalse()
        ->and(Activity::where('external_id', 'sugar-task')->exists())->toBeFalse()
        ->and(Email::where('unique_id', 'sugar-mail')->exists())->toBeFalse()
        ->and(Person::whereKey($person->id)->exists())->toBeTrue()
        ->and(Order::where('external_id', 'sugar-earlier-import')->exists())->toBeTrue();
});

test('inserts nothing when a product cannot be found', function () {
    $record = missingOrderRecord();
    $record['items'][0]['product_name'] = 'Onbekend product';
    writeMissingOrderFiles([$record]);

    expect(fn () => app(MissingOrderRestorer::class)->restore())->toThrow(RuntimeException::class, 'Product niet eenduidig')
        ->and(Order::where('external_id', 'sugar-order')->exists())->toBeFalse()
        ->and(Lead::where('external_id', 'sugar-lead')->exists())->toBeFalse()
        ->and(Person::where('external_id', 'sugar-person')->exists())->toBeFalse();
});

test('fails without the data files while orders are missing', function () {
    app(MissingOrderRestorer::class)->restore();
})->throws(RuntimeException::class, 'databestand ontbreekt');

test('does nothing without imported orders', function () {
    DB::table('orders')->update(['external_id' => null]);

    expect(app(MissingOrderRestorer::class)->restore())->toBeNull();
});
