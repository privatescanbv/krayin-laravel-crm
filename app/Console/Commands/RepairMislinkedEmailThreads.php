<?php

namespace App\Console\Commands;

use App\Services\Mail\EmailEntityLinker;
use App\Services\Mail\MailboxConfig;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Webkul\Email\Models\Email;

/**
 * Undo the damage caused by the participant/subject heuristic in {@see RepairEmailThreads}.
 *
 * That command attached emails as parent/child purely on matching mailbox_key + normalized
 * subject + sender, without ever comparing the actual recipient. For templated outbound mail
 * (all sent from the same CRM mailbox with an identical subject) this collapsed unrelated
 * recipients into one thread.
 *
 * This command re-establishes the rule the rest of the system already follows: a parent/child
 * relation only exists when there is an id-based link (message_id / references / conversationId).
 * Any email whose parent link lacks that evidence is detached and re-linked to the correct CRM
 * entity using the exact same {@see EmailEntityLinker} logic that inbound mail uses.
 */
class RepairMislinkedEmailThreads extends Command
{
    private const CHUNK_SIZE = 500;

    /**
     * Entity foreign keys managed by {@see EmailEntityLinker}. Only these are recomputed; other
     * links (e.g. activity_id) are never touched so we cannot destroy unrelated data.
     *
     * @var list<string>
     */
    private const LINKER_MANAGED_KEYS = [
        'order_id',
        'sales_lead_id',
        'lead_id',
        'person_id',
        'clinic_id',
    ];

    protected $signature = 'emails:repair-mislinked-threads
                            {--dry-run : Show proposed changes without persisting them}
                            {--id=* : Limit repair to the thread(s) that contain these email IDs}
                            {--months= : Only repair emails created within the last N months}';

    protected $description = 'Detach emails that were threaded without id-based evidence and re-link them to the correct entity.';

    /**
     * @var array<int, Collection<int, Email>>
     */
    private array $threadMembersCache = [];

    /**
     * Memoized thread-expanded target IDs from the --id option.
     *
     * @var list<int>|null
     */
    private ?array $targetIds = null;

    public function handle(EmailEntityLinker $entityLinker): int
    {
        if ($this->option('months') !== null && $this->option('months') !== '' && (int) $this->option('months') < 1) {
            $this->error('The --months option must be a positive integer.');

            return Command::FAILURE;
        }

        $since = $this->createdSince();
        $total = $this->candidateQuery()->count();

        $this->components->info(sprintf(
            'Scanning %d threaded email(s) for mislinked parent/child relations%s%s',
            $total,
            $this->option('dry-run') ? ' (dry-run)' : '',
            $since ? ' since '.$since->toDateString() : ''
        ));

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%');
        $bar->start();

        $plans = [];

        $this->candidateQuery()->chunkById(self::CHUNK_SIZE, function (Collection $emails) use (&$plans, $entityLinker, $bar) {
            foreach ($emails as $email) {
                if ($this->hasIdBasedThreadLink($email)) {
                    $bar->advance();

                    continue;
                }

                $address = $this->counterpartyAddress($email);
                $resolved = $address !== null ? $entityLinker->link([], $address) : [];
                $links = $this->extractLinkerKeys($resolved);

                $plans[] = [
                    'email'    => $email,
                    'address'  => $address,
                    'links'    => $links,
                    'oldParent'=> $email->parent_id,
                ];

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $rows = [];

        foreach ($plans as $plan) {
            /** @var Email $email */
            $email = $plan['email'];

            $rows[] = [
                $email->id,
                $plan['oldParent'],
                $plan['address'] ?? '—',
                $plan['links'] === [] ? 'none (kept as-is)' : $this->describeLinks($plan['links']),
            ];

            if (! $this->option('dry-run')) {
                $payload = ['parent_id' => null];

                if ($plan['links'] !== []) {
                    foreach (self::LINKER_MANAGED_KEYS as $key) {
                        $payload[$key] = $plan['links'][$key] ?? null;
                    }
                }

                $email->forceFill($payload)->save();
            }
        }

        $this->table(['Email', 'Old parent', 'Counterparty', 'Re-linked entity'], $rows);

        $count = count($plans);

        if ($this->option('dry-run')) {
            $this->info("Dry run completed. {$count} mislinked email(s) can be detached and re-linked.");
        } else {
            $this->info("Repair completed. {$count} mislinked email(s) detached and re-linked.");
        }

        return Command::SUCCESS;
    }

    /**
     * Emails that currently claim a parent and are therefore candidates for verification.
     */
    private function candidateQuery()
    {
        $since = $this->createdSince();
        $targetIds = $this->targetIds();

        return Email::query()
            ->whereNotNull('parent_id')
            ->when(
                $targetIds !== null,
                fn ($query) => $query->whereIn('id', $targetIds)
            )
            ->when(
                $since !== null,
                fn ($query) => $query->where('created_at', '>=', $since)
            )
            ->select([
                'id',
                'parent_id',
                'message_id',
                'reference_ids',
                'from',
                'reply_to',
                'mailbox_key',
                'created_at',
            ])
            ->orderBy('id');
    }

    private function createdSince(): ?Carbon
    {
        $months = $this->option('months');

        if ($months === null || $months === '') {
            return null;
        }

        return now()->subMonths((int) $months)->startOfDay();
    }

    /**
     * Expand the --id option to every email in the same thread(s), so passing any email in a
     * thread repairs the whole thread (the offending child is rarely the id being viewed).
     *
     * @return list<int>|null null when no --id filter is set
     */
    private function targetIds(): ?array
    {
        if ($this->option('id') === []) {
            return null;
        }

        if ($this->targetIds !== null) {
            return $this->targetIds;
        }

        $expanded = [];

        foreach (array_map('intval', (array) $this->option('id')) as $id) {
            $email = Email::query()->find($id, ['id', 'parent_id', 'message_id', 'reference_ids']);

            if (! $email) {
                continue;
            }

            foreach ($this->threadMembers($this->resolveThreadRoot($email)) as $member) {
                $expanded[$member->id] = true;
            }
        }

        return $this->targetIds = array_map('intval', array_keys($expanded));
    }

    /**
     * True when the email shares at least one message-id/reference identifier with any other
     * member of its thread. A false result means the link was fabricated (subject/participant
     * heuristic) and must be undone.
     */
    private function hasIdBasedThreadLink(Email $email): bool
    {
        $parent = Email::query()->find($email->parent_id, ['id', 'parent_id', 'message_id', 'reference_ids']);

        if (! $parent) {
            return false;
        }

        $root = $this->resolveThreadRoot($email);
        $ownIds = $this->identifiers($email);

        if ($ownIds === []) {
            return false;
        }

        foreach ($this->threadMembers($root) as $member) {
            if ($member->id === $email->id) {
                continue;
            }

            if (array_intersect($ownIds, $this->identifiers($member)) !== []) {
                return true;
            }
        }

        return false;
    }

    private function resolveThreadRoot(Email $email): Email
    {
        $current = $email;
        $seen = [$email->id => true];

        while ($current->parent_id) {
            $parent = Email::query()->find($current->parent_id, ['id', 'parent_id', 'message_id', 'reference_ids']);

            if (! $parent || isset($seen[$parent->id])) {
                break;
            }

            $seen[$parent->id] = true;
            $current = $parent;
        }

        return $current;
    }

    /**
     * @return Collection<int, Email>
     */
    private function threadMembers(Email $root): Collection
    {
        if (isset($this->threadMembersCache[$root->id])) {
            return $this->threadMembersCache[$root->id];
        }

        $ids = [$root->id];
        $queue = [$root->id];

        while ($queue !== []) {
            $parentId = array_shift($queue);

            $childIds = Email::query()
                ->where('parent_id', $parentId)
                ->pluck('id')
                ->all();

            foreach ($childIds as $childId) {
                if (! in_array($childId, $ids, true)) {
                    $ids[] = $childId;
                    $queue[] = $childId;
                }
            }
        }

        return $this->threadMembersCache[$root->id] = Email::query()
            ->whereIn('id', $ids)
            ->get(['id', 'parent_id', 'message_id', 'reference_ids']);
    }

    /**
     * Normalized message-id + reference identifiers for an email.
     *
     * @return list<string>
     */
    private function identifiers(Email $email): array
    {
        $ids = $this->messageIdCandidates($email->message_id);

        foreach ($email->reference_ids ?? [] as $reference) {
            if (is_string($reference) && $reference !== '') {
                foreach ($this->messageIdCandidates($reference) as $candidate) {
                    $ids[] = $candidate;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<string>
     */
    private function messageIdCandidates(?string $messageId): array
    {
        if (! is_string($messageId) || trim($messageId) === '') {
            return [];
        }

        $normalized = trim(trim($messageId), '<>');

        return array_values(array_unique(array_filter([
            $messageId,
            $normalized,
            $normalized !== '' ? '<'.$normalized.'>' : null,
        ])));
    }

    /**
     * The external party for entity linking: the sender when it is external, otherwise the
     * recipient (outbound CRM mail is sent from our own mailbox, so the sender is never the
     * customer). Mirrors how inbound mail links on the "from" address.
     */
    private function counterpartyAddress(Email $email): ?string
    {
        $from = strtolower(trim((string) data_get($email->from, 'email', '')));

        if ($from !== '' && ! $this->isOwnMailbox($from)) {
            return $from;
        }

        foreach ($email->reply_to ?? [] as $recipient) {
            $recipient = strtolower(trim((string) $recipient));

            if ($recipient !== '' && ! $this->isOwnMailbox($recipient)) {
                return $recipient;
            }
        }

        return $from !== '' ? $from : null;
    }

    private function isOwnMailbox(string $address): bool
    {
        return MailboxConfig::resolveKeyByAddress($address) !== null;
    }

    /**
     * @param  array<string, mixed>  $resolved
     * @return array<string, int>
     */
    private function extractLinkerKeys(array $resolved): array
    {
        $links = [];

        foreach (self::LINKER_MANAGED_KEYS as $key) {
            if (! empty($resolved[$key])) {
                $links[$key] = (int) $resolved[$key];
            }
        }

        return $links;
    }

    /**
     * @param  array<string, int>  $links
     */
    private function describeLinks(array $links): string
    {
        return implode(', ', array_map(
            fn (string $key, int $id) => "{$key}={$id}",
            array_keys($links),
            array_values($links),
        ));
    }
}
