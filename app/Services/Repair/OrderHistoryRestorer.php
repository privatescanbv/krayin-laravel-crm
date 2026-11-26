<?php

namespace App\Services\Repair;

use App\Actions\Leads\RecordLeadContactAction;
use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use Closure;
use Generator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * One-off repair: orders from the first SugarCRM import run never got their tasks, mails and calls.
 *
 * The records are read from a gzipped JSON-lines file that holds patient data and is therefore placed
 * on the server by hand instead of being committed. Everything is matched on stable external keys
 * (order external_id, user external_id, group and folder name), never on ids from the export database.
 *
 * Writes through the query builder on purpose: historical records must not fire observers.
 */
class OrderHistoryRestorer
{
    public const DATA_FILE = 'app/herstel/order-activiteiten.jsonl.gz';

    public const MANIFEST_FILE = 'app/herstel/order-activiteiten-ingevoegd.json';

    private const SUGAR_SENDER = '%import@sugarcrm.local%';

    public function __construct(private readonly RecordLeadContactAction $recordLeadContact) {}

    /**
     * @return array{activities: int, emails: int, linked_emails: int, closed_tasks: int}|null null when there is nothing to repair
     */
    public function restore(): ?array
    {
        // Fresh and test databases migrate too: leave the files on disk untouched there.
        if (! DB::table('orders')->whereNotNull('external_id')->exists()) {
            return null;
        }

        $path = storage_path(self::DATA_FILE);

        if (! File::exists($path)) {
            if ($this->hasOrdersWithoutHistory()) {
                throw new RuntimeException(
                    "Er zijn geïmporteerde orders zonder taken en mails, maar het databestand ontbreekt: {$path}. "
                    .'Zet het bestand neer en draai de migratie opnieuw.'
                );
            }

            return null;
        }

        $manifest = $this->withoutQueryEvents(fn () => DB::transaction(fn () => $this->insertRecords($path)));

        $this->rememberInserted($manifest);

        $counts = [
            'activities'    => count($manifest['activity_ids']),
            'emails'        => count($manifest['email_ids']),
            'linked_emails' => count($manifest['linked_email_ids']),
            'closed_tasks'  => $manifest['closed_tasks'],
        ];

        Log::info('Order history restored', ['actor' => 'migration', ...$counts]);

        return $counts;
    }

    public function revert(): void
    {
        $path = storage_path(self::MANIFEST_FILE);

        if (! File::exists($path)) {
            return;
        }

        $manifest = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

        $this->withoutQueryEvents(fn () => DB::transaction(function () use ($manifest) {
            foreach (array_chunk($manifest['activity_ids'], 1000) as $ids) {
                DB::table('activities')->whereIn('id', $ids)->delete();
            }

            $salesLeadIds = [];

            foreach (array_chunk($manifest['email_ids'], 1000) as $ids) {
                $salesLeadIds += DB::table('emails')->whereIn('id', $ids)->whereNotNull('sales_lead_id')->pluck('id', 'sales_lead_id')->all();
                DB::table('email_tags')->whereIn('email_id', $ids)->delete();
                DB::table('emails')->whereIn('id', $ids)->delete();
            }

            foreach (array_chunk($manifest['linked_email_ids'], 1000) as $ids) {
                DB::table('emails')->whereIn('id', $ids)->update(['order_id' => null]);
            }

            $this->recordLeadContacts(array_keys($salesLeadIds));
        }));

        File::delete($path);
    }

    /**
     * Adds to the manifest of earlier runs, so a rerun never loses what revert() has to remove.
     *
     * @param  array{activity_ids: list<int>, email_ids: list<int>, linked_email_ids: list<int>, closed_tasks: int}  $inserted
     */
    private function rememberInserted(array $inserted): void
    {
        if (! $inserted['activity_ids'] && ! $inserted['email_ids'] && ! $inserted['linked_email_ids']) {
            return;
        }

        $path = storage_path(self::MANIFEST_FILE);

        if (File::exists($path)) {
            $inserted = array_merge_recursive(json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR), $inserted);
            $inserted['closed_tasks'] = array_sum((array) $inserted['closed_tasks']);
        }

        File::put($path, json_encode($inserted));
    }

    /**
     * Keeps Telescope, Debugbar and query logging from buffering tens of thousands of mail bodies:
     * that exhausts memory and copies patient data into their storage.
     */
    private function withoutQueryEvents(Closure $callback): mixed
    {
        $connection = DB::connection();
        $dispatcher = $connection->getEventDispatcher();
        $connection->unsetEventDispatcher();

        try {
            return $callback();
        } finally {
            if ($dispatcher) {
                $connection->setEventDispatcher($dispatcher);
            }
        }
    }

    /**
     * The skipped EmailObserver keeps "had contact" on lost leads up to date.
     *
     * @param  list<int>  $salesLeadIds
     */
    private function recordLeadContacts(array $salesLeadIds): void
    {
        foreach ($salesLeadIds as $salesLeadId) {
            $this->recordLeadContact->executeFor(null, $salesLeadId);
        }
    }

    private function hasOrdersWithoutHistory(): bool
    {
        return DB::table('orders')
            ->whereNotNull('external_id')
            ->whereNotIn('id', $this->ordersWithImportedTask())
            ->whereNotIn('id', $this->ordersWithSugarMail())
            ->exists();
    }

    private function ordersWithImportedTask(): Builder
    {
        return DB::table('activities')
            ->select('order_id')
            ->whereNotNull('order_id')
            ->whereNotNull('external_id')
            ->where('type', ActivityType::TASK->value);
    }

    private function ordersWithSugarMail(): Builder
    {
        return DB::table('emails')
            ->select('order_id')
            ->whereNotNull('order_id')
            ->where('from', 'like', self::SUGAR_SENDER);
    }

    /**
     * @return array{activity_ids: list<int>, email_ids: list<int>, linked_email_ids: list<int>, closed_tasks: int}
     */
    private function insertRecords(string $path): array
    {
        // Determined before the first insert: a type is only restored for orders that have none of it yet,
        // so records that users deleted or moved elsewhere on other orders stay that way.
        $haveTasks = $this->ordersWithImportedTask()->distinct()->pluck('order_id')->flip();
        $haveMail = $this->ordersWithSugarMail()->distinct()->pluck('order_id')->flip();

        $orders = $this->orders();
        $users = DB::table('users')->whereNotNull('external_id')->pluck('id', 'external_id');
        $groups = DB::table('groups')->pluck('id', 'name');
        $folders = DB::table('folders')->pluck('id', 'name');

        $manifest = ['activity_ids' => [], 'email_ids' => [], 'linked_email_ids' => [], 'closed_tasks' => 0];
        $salesLeadIds = [];
        $importTagId = null;

        foreach ($this->records($path) as $record) {
            $order = $orders->get($record['order']);

            if (! $order) {
                continue;
            }

            if ($record['kind'] === 'activity') {
                $row = $record['row'];
                $alreadyHas = $row['type'] === ActivityType::TASK->value ? $haveTasks : $haveMail;

                if ($alreadyHas->has($order->id) || DB::table('activities')->where('external_id', $row['external_id'])->exists()) {
                    continue;
                }

                // Open work on a finished order would only clutter the work queues.
                if ($order->is_closed && ! $row['is_done']) {
                    $row['is_done'] = 1;
                    $row['status'] = ActivityStatus::DONE->value;
                    $manifest['closed_tasks']++;
                }

                $manifest['activity_ids'][] = DB::table('activities')->insertGetId([
                    ...$row,
                    'order_id' => $order->id,
                    'user_id'  => $users->get($record['user'] ?? ''),
                    'group_id' => $groups->get($record['group'] ?? ''),
                ]);

                continue;
            }

            if ($haveMail->has($order->id)) {
                continue;
            }

            if ($record['kind'] === 'email_link') {
                $emailId = DB::table('emails')->where('unique_id', $record['unique_id'])->whereNull('order_id')->value('id');

                if ($emailId) {
                    DB::table('emails')->where('id', $emailId)->update(['order_id' => $order->id]);
                    $manifest['linked_email_ids'][] = $emailId;
                }

                continue;
            }

            if (DB::table('emails')->where('unique_id', $record['row']['unique_id'])->exists()) {
                continue;
            }

            $folderId = $folders->get($record['folder'] ?? '')
                ?? throw new RuntimeException('E-mailmap ontbreekt: '.($record['folder'] ?? '(leeg)'));

            $emailId = DB::table('emails')->insertGetId([
                ...$record['row'],
                'order_id'      => $order->id,
                'sales_lead_id' => $order->sales_lead_id,
                'folder_id'     => $folderId,
            ]);

            $importTagId ??= $this->importTagId();
            DB::table('email_tags')->insert(['tag_id' => $importTagId, 'email_id' => $emailId]);

            $manifest['email_ids'][] = $emailId;

            if ($order->sales_lead_id) {
                $salesLeadIds[$order->sales_lead_id] = true;
            }
        }

        $this->recordLeadContacts(array_keys($salesLeadIds));

        return $manifest;
    }

    /**
     * @return Collection<string, object{id: int, external_id: string, sales_lead_id: int|null, is_closed: bool}>
     */
    private function orders(): Collection
    {
        return DB::table('orders')
            ->leftJoin('lead_pipeline_stages as stages', 'stages.id', '=', 'orders.pipeline_stage_id')
            ->whereNotNull('orders.external_id')
            ->get(['orders.id', 'orders.external_id', 'orders.sales_lead_id', 'stages.is_won', 'stages.is_lost'])
            ->map(function (object $order) {
                $order->is_closed = $order->is_won || $order->is_lost;

                return $order;
            })
            ->keyBy('external_id');
    }

    private function importTagId(): int
    {
        return DB::table('tags')->where('name', 'import')->value('id')
            ?? DB::table('tags')->insertGetId([
                'name'       => 'import',
                'color'      => '#6B7280',
                'user_id'    => DB::table('users')->min('id'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    private function records(string $path): Generator
    {
        $handle = gzopen($path, 'rb') ?: throw new RuntimeException("Databestand niet leesbaar: {$path}");

        try {
            while (($line = gzgets($handle)) !== false) {
                if (trim($line) !== '') {
                    yield json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                }
            }

            if (! gzeof($handle)) {
                throw new RuntimeException("Databestand is onvolledig gelezen: {$path}");
            }
        } finally {
            gzclose($handle);
        }
    }
}
