<?php

namespace App\Console\Commands;

use App\Services\Mail\MailboxConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Webkul\Email\Models\Email;

class RepairEmailThreads extends Command
{
    private const CHUNK_SIZE = 500;

    private const PARTICIPANT_HEURISTIC_LIMIT = 200;

    protected $signature = 'emails:repair-threads
                            {--dry-run : Show proposed changes without persisting them}
                            {--id=* : Limit repair to specific email IDs}';

    protected $description = 'Repair orphaned email threads using stored references and conservative subject/participant heuristics.';

    public function handle(): int
    {
        $threadRows = [];
        $threadUpdatedCount = 0;
        $threadTotal = $this->threadRepairQuery()->count();

        $this->components->info(sprintf(
            'Phase 1/2: scanning %d email(s) for thread repairs%s',
            $threadTotal,
            $this->option('dry-run') ? ' (dry-run)' : ''
        ));

        $threadBar = $this->output->createProgressBar($threadTotal);
        $threadBar->setFormat(' %current%/%max% [%bar%] %percent:3s%%');
        $threadBar->start();

        $this->threadRepairQuery()
            ->chunkById(self::CHUNK_SIZE, function (Collection $emails) use (&$threadRows, &$threadUpdatedCount, $threadBar) {
                foreach ($emails as $email) {
                    $parent = $this->resolveParentEmail($email);

                    if (! $parent) {
                        $threadBar->advance();

                        continue;
                    }

                    $threadRoot = $parent->getThreadRoot();

                    if ($threadRoot->id === $email->id) {
                        $threadBar->advance();

                        continue;
                    }

                    $threadRows[] = [
                        $email->id,
                        $threadRoot->id,
                        $this->normalizeSubject((string) $email->subject),
                        $this->detectRepairStrategy($email, $parent),
                    ];

                    if (! $this->option('dry-run')) {
                        $inheritSource = $parent;

                        $email->forceFill([
                            'parent_id'   => $threadRoot->id,
                            'activity_id' => $email->activity_id ?? $threadRoot->activity_id ?? $inheritSource->activity_id,
                            'lead_id'     => $email->lead_id ?? $threadRoot->lead_id ?? $inheritSource->lead_id,
                            'person_id'   => $email->person_id ?? $threadRoot->person_id ?? $inheritSource->person_id,
                        ])->save();
                    }

                    $threadUpdatedCount++;
                    $threadBar->advance();
                }
            });

        $threadBar->finish();
        $this->newLine(2);

        $replyToRows = [];
        $replyToUpdatedCount = 0;
        $replyToTotal = $this->replyToBackfillQuery()->count();

        $this->components->info(sprintf(
            'Phase 2/2: scanning %d email(s) for reply_to backfill%s',
            $replyToTotal,
            $this->option('dry-run') ? ' (dry-run)' : ''
        ));

        $replyToBar = $this->output->createProgressBar($replyToTotal);
        $replyToBar->setFormat(' %current%/%max% [%bar%] %percent:3s%%');
        $replyToBar->start();

        $this->replyToBackfillQuery()
            ->with(['parent:id,mailbox_key'])
            ->chunkById(self::CHUNK_SIZE, function (Collection $emails) use (&$replyToRows, &$replyToUpdatedCount, $replyToBar) {
                foreach ($emails as $email) {
                    $replyTo = $this->resolveReplyToBackfill($email);

                    if ($replyTo === []) {
                        $replyToBar->advance();

                        continue;
                    }

                    $replyToRows[] = [
                        $email->id,
                        implode(', ', $replyTo),
                        $this->detectReplyToStrategy($email, $replyTo),
                    ];

                    if (! $this->option('dry-run')) {
                        $email->forceFill([
                            'reply_to' => $replyTo,
                        ])->save();
                    }

                    $replyToUpdatedCount++;
                    $replyToBar->advance();
                }
            });

        $replyToBar->finish();
        $this->newLine(2);

        $this->table(['Email', 'Thread root', 'Subject', 'Strategy'], $threadRows);
        $this->table(['Email', 'Reply-To backfill', 'Strategy'], $replyToRows);

        if ($this->option('dry-run')) {
            $this->info("Dry run completed. {$threadUpdatedCount} thread repair(s) and {$replyToUpdatedCount} reply_to backfill(s) can be applied.");
        } else {
            $this->info("Repair completed. {$threadUpdatedCount} thread repair(s) and {$replyToUpdatedCount} reply_to backfill(s) applied.");
        }

        return Command::SUCCESS;
    }

    private function replyToBackfillQuery()
    {
        return $this->baseEmailQuery()
            ->whereNotNull('message_id')
            ->where(function ($query) {
                $query->whereNull('reply_to')
                    ->orWhere('reply_to', '[]')
                    ->orWhere('reply_to', 'null')
                    ->orWhere('reply_to', '');
            })
            ->select([
                'id',
                'parent_id',
                'message_id',
                'reply_to',
                'mailbox_key',
            ])
            ->orderBy('id');
    }

    private function resolveParentEmail(Email $email): ?Email
    {
        return $this->findParentByReferences($email)
            ?? $this->findParentByParticipantHeuristic($email);
    }

    private function findParentByReferences(Email $email): ?Email
    {
        $currentCandidates = $this->messageIdCandidates($email->message_id);
        $referenceIds = collect($email->reference_ids ?? [])
            ->filter(fn ($reference) => is_string($reference) && $reference !== '')
            ->reject(fn (string $reference) => in_array($reference, $currentCandidates, true))
            ->values();

        foreach ($referenceIds as $referenceId) {
            $candidate = $this->findEmailByMessageIdentifier($referenceId, $email);

            if ($candidate) {
                return $candidate;
            }

            $candidate = Email::query()
                ->where('id', '!=', $email->id)
                ->where('created_at', '<=', $email->created_at)
                ->whereJsonContains('reference_ids', $referenceId)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            if ($candidate) {
                return $candidate;
            }
        }

        return null;
    }

    private function findParentByParticipantHeuristic(Email $email): ?Email
    {
        $participantEmail = $this->participantEmail($email);

        if (! $participantEmail) {
            return null;
        }

        return Email::query()
            ->where('id', '!=', $email->id)
            ->where('mailbox_key', $email->mailbox_key)
            ->where('created_at', '<=', $email->created_at)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::PARTICIPANT_HEURISTIC_LIMIT)
            ->get()
            ->first(function (Email $candidate) use ($email, $participantEmail) {
                if ($this->normalizeSubject((string) $candidate->subject) !== $this->normalizeSubject((string) $email->subject)) {
                    return false;
                }

                $candidateFrom = strtolower((string) data_get($candidate->from, 'email', ''));
                $candidateReplyTo = collect($candidate->reply_to ?? [])->map(fn ($value) => strtolower((string) $value));

                return $candidateFrom === $participantEmail || $candidateReplyTo->contains($participantEmail);
            });
    }

    private function findEmailByMessageIdentifier(string $referenceId, Email $email): ?Email
    {
        $candidates = $this->messageIdCandidates($referenceId);

        foreach ($candidates as $candidateId) {
            $candidate = Email::query()
                ->where('id', '!=', $email->id)
                ->where('created_at', '<=', $email->created_at)
                ->where('message_id', $candidateId)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            if ($candidate) {
                return $candidate;
            }
        }

        return null;
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

    private function participantEmail(Email $email): ?string
    {
        $fromEmail = strtolower((string) data_get($email->from, 'email', ''));

        if ($fromEmail !== '') {
            return $fromEmail;
        }

        $replyTo = collect($email->reply_to ?? [])->map(fn ($value) => strtolower((string) $value))->filter()->first();

        return $replyTo ?: null;
    }

    private function normalizeSubject(string $subject): string
    {
        $normalized = strtolower(trim($subject));

        while (preg_match('/^(re|fwd):\s*/i', $normalized) === 1) {
            $normalized = preg_replace('/^(re|fwd):\s*/i', '', $normalized) ?? $normalized;
        }

        return trim($normalized);
    }

    private function detectRepairStrategy(Email $email, Email $parent): string
    {
        $parentCandidates = $this->messageIdCandidates($parent->message_id);

        foreach ($email->reference_ids ?? [] as $referenceId) {
            if (in_array($referenceId, $parentCandidates, true)) {
                return 'references';
            }
        }

        return 'subject+participant';
    }

    /**
     * @return list<string>
     */
    private function resolveReplyToBackfill(Email $email): array
    {
        if (! empty($email->reply_to)) {
            return [];
        }

        $mailboxAddress = MailboxConfig::address($email->mailbox_key);

        if (is_string($mailboxAddress) && $mailboxAddress !== '') {
            return [$mailboxAddress];
        }

        $parentMailbox = $email->parent?->mailbox_key
            ? MailboxConfig::address($email->parent->mailbox_key)
            : null;

        if (is_string($parentMailbox) && $parentMailbox !== '') {
            return [$parentMailbox];
        }

        return [];
    }

    /**
     * @param  list<string>  $replyTo
     */
    private function detectReplyToStrategy(Email $email, array $replyTo): string
    {
        $mailboxAddress = MailboxConfig::address($email->mailbox_key);

        if (is_string($mailboxAddress) && in_array($mailboxAddress, $replyTo, true)) {
            return 'mailbox_key';
        }

        return 'parent_mailbox';
    }

    private function baseEmailQuery()
    {
        return Email::query()->when(
            $this->option('id') !== [],
            fn ($query) => $query->whereIn('id', array_map('intval', (array) $this->option('id')))
        );
    }

    private function threadRepairQuery()
    {
        return $this->baseEmailQuery()
            ->whereNull('parent_id')
            ->whereNotNull('message_id')
            ->select([
                'id',
                'subject',
                'message_id',
                'reference_ids',
                'created_at',
                'mailbox_key',
                'from',
                'reply_to',
                'activity_id',
                'lead_id',
                'person_id',
            ])
            ->orderBy('id');
    }
}
