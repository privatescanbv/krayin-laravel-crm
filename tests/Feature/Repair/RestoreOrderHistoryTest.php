<?php

use App\Models\Order;
use App\Services\Repair\OrderHistoryRestorer;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Webkul\Activity\Models\Activity;
use Webkul\Email\Models\Email;
use Webkul\Lead\Models\Stage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);

    // Never touch the real storage directory: it may hold the actual export file.
    $this->temporaryStorage = sys_get_temp_dir().'/order-history-'.uniqid();
    app()->useStoragePath($this->temporaryStorage);
    File::ensureDirectoryExists(dirname(storage_path(OrderHistoryRestorer::DATA_FILE)));

    DB::table('folders')->updateOrInsert(['name' => 'Importmap']);
});

afterEach(function () {
    File::deleteDirectory($this->temporaryStorage);
});

function importedOrder(string $externalId, bool $won = false): Order
{
    $stage = Stage::factory()->create([
        'lead_pipeline_id' => Stage::query()->value('lead_pipeline_id') ?? 1,
        'is_won'           => $won,
    ]);

    $order = Order::factory()->create(['pipeline_stage_id' => $stage->id]);
    DB::table('orders')->where('id', $order->id)->update(['external_id' => $externalId]);

    return $order->refresh();
}

function activityRecord(string $order, string $externalId, string $type = 'task', bool $isDone = false): array
{
    return [
        'kind'  => 'activity',
        'order' => $order,
        'user'  => 'sugar-user',
        'group' => null,
        'row'   => [
            'external_id' => $externalId,
            'title'       => 'Order bevestigen',
            'type'        => $type,
            'status'      => $isDone ? 'done' : 'active',
            'is_done'     => (int) $isDone,
            'created_at'  => '2026-01-27 12:15:51',
            'updated_at'  => '2026-01-27 12:39:55',
        ],
    ];
}

function emailRecord(string $order, string $uniqueId): array
{
    return [
        'kind'   => 'email',
        'order'  => $order,
        'folder' => 'Importmap',
        'row'    => [
            'unique_id'  => $uniqueId,
            'message_id' => $uniqueId,
            'subject'    => 'Afspraakbevestiging',
            'source'     => 'web',
            'user_type'  => 'person',
            'from'       => json_encode(['name' => 'SugarCRM Import', 'email' => 'import@sugarcrm.local']),
            'created_at' => '2026-01-27 12:40:17',
            'updated_at' => '2026-01-27 12:40:30',
        ],
    ];
}

function writeDataFile(array $records): void
{
    $lines = array_map(fn (array $record) => json_encode($record), $records);

    File::put(storage_path(OrderHistoryRestorer::DATA_FILE), gzencode(implode("\n", $lines)."\n"));
}

test('restores history with the original dates, closes open work on won orders and runs only once', function () {
    $user = getDefaultAdmin();
    DB::table('users')->where('id', $user->id)->update(['external_id' => 'sugar-user']);
    $won = importedOrder('sugar-won', won: true);
    $open = importedOrder('sugar-open');

    writeDataFile([
        activityRecord('sugar-won', 'task-1'),
        activityRecord('sugar-open', 'task-2'),
        activityRecord('sugar-open', 'call-1', 'call', isDone: true),
        emailRecord('sugar-won', 'mail-1'),
        activityRecord('sugar-unknown', 'task-3'),
    ]);

    $counts = app(OrderHistoryRestorer::class)->restore();

    expect($counts)->toBe(['activities' => 3, 'emails' => 1, 'linked_emails' => 0, 'closed_tasks' => 1]);

    $closed = Activity::where('external_id', 'task-1')->sole();
    expect($closed->order_id)->toBe($won->id)
        ->and($closed->user_id)->toBe($user->id)
        ->and($closed->is_done)->toBeTrue()
        ->and($closed->status->value)->toBe('done')
        ->and($closed->created_at->toDateTimeString())->toBe('2026-01-27 12:15:51');

    expect(Activity::where('external_id', 'task-2')->sole()->is_done)->toBeFalse()
        ->and(Activity::where('external_id', 'call-1')->sole()->order_id)->toBe($open->id)
        ->and(Activity::where('external_id', 'task-3')->exists())->toBeFalse();

    $email = Email::where('unique_id', 'mail-1')->sole();
    expect($email->order_id)->toBe($won->id)
        ->and($email->sales_lead_id)->toBe($won->sales_lead_id)
        ->and($email->tags()->pluck('name')->all())->toBe(['import']);

    expect(app(OrderHistoryRestorer::class)->restore())
        ->toBe(['activities' => 0, 'emails' => 0, 'linked_emails' => 0, 'closed_tasks' => 0]);
});

test('leaves a record type alone on orders that already received it', function () {
    $hasTask = importedOrder('sugar-has-task');
    $hasMail = importedOrder('sugar-has-mail');
    DB::table('activities')->insert([...activityRecord('', 'existing-task')['row'], 'order_id' => $hasTask->id]);
    DB::table('emails')->insert([...emailRecord('', 'existing-mail')['row'], 'order_id' => $hasMail->id]);

    writeDataFile([
        activityRecord('sugar-has-task', 'deleted-task'),
        emailRecord('sugar-has-task', 'missing-mail'),
        activityRecord('sugar-has-mail', 'missing-task'),
        activityRecord('sugar-has-mail', 'deleted-call', 'call'),
        emailRecord('sugar-has-mail', 'deleted-mail'),
    ]);

    app(OrderHistoryRestorer::class)->restore();

    expect(Activity::whereIn('external_id', ['deleted-task', 'deleted-call'])->exists())->toBeFalse()
        ->and(Email::where('unique_id', 'deleted-mail')->exists())->toBeFalse()
        ->and(Activity::where('external_id', 'missing-task')->exists())->toBeTrue()
        ->and(Email::where('unique_id', 'missing-mail')->exists())->toBeTrue();
});

test('links an existing email without an order and revert undoes everything', function () {
    $order = importedOrder('sugar-order');
    DB::table('emails')->insert(emailRecord('', 'lead-mail')['row']);

    writeDataFile([
        ['kind' => 'email_link', 'order' => 'sugar-order', 'unique_id' => 'lead-mail'],
        activityRecord('sugar-order', 'task-1'),
        emailRecord('sugar-order', 'new-mail'),
    ]);

    app(OrderHistoryRestorer::class)->restore();
    // A rerun inserts nothing and must not forget what the first run added.
    app(OrderHistoryRestorer::class)->restore();

    expect(Email::where('unique_id', 'lead-mail')->sole()->order_id)->toBe($order->id);

    app(OrderHistoryRestorer::class)->revert();

    expect(Email::where('unique_id', 'lead-mail')->sole()->order_id)->toBeNull()
        ->and(Email::where('unique_id', 'new-mail')->exists())->toBeFalse()
        ->and(Activity::where('external_id', 'task-1')->exists())->toBeFalse();
});

test('fails without the data file while imported orders have no history', function () {
    importedOrder('sugar-order');

    app(OrderHistoryRestorer::class)->restore();
})->throws(RuntimeException::class, 'databestand ontbreekt');

test('does nothing and writes no files when there are no imported orders', function () {
    Order::factory()->create();

    expect(app(OrderHistoryRestorer::class)->restore())->toBeNull();

    writeDataFile([activityRecord('sugar-order', 'task-1')]);

    expect(app(OrderHistoryRestorer::class)->restore())->toBeNull()
        ->and(File::exists(storage_path(OrderHistoryRestorer::MANIFEST_FILE)))->toBeFalse();
});

test('inserts nothing when the data file is damaged', function () {
    importedOrder('sugar-order');
    $valid = json_encode(activityRecord('sugar-order', 'task-1'));
    File::put(storage_path(OrderHistoryRestorer::DATA_FILE), gzencode($valid."\n{kapot\n"));

    expect(fn () => app(OrderHistoryRestorer::class)->restore())->toThrow(JsonException::class)
        ->and(Activity::where('external_id', 'task-1')->exists())->toBeFalse();
});
