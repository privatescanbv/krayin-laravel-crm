<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Contact\Models\Person;
use Webkul\Email\Models\Email;

/**
 * Finds emails linked to more than one CRM entity where the mail's own address isn't
 * actually known to one of those entities — e.g. person_id=Bob Karis + order_id=Margot's
 * order (email #64591): Bob is the right contact, but his address never appears on
 * Margot's order, so that specific relation is wrong.
 *
 * Requirements this implements:
 *  1. Only considers emails linked to MORE than one entity — a single link has nothing to
 *     cross-check against.
 *  2. For each linked field, the mail's own address (Email::counterpartyEmail()) must be
 *     known to that entity:
 *       - person_id / lead_id / clinic_id: the entity's own `emails` array
 *         (HasDefaultContactInfo::hasEmailAddress()).
 *       - sales_lead_id / order_id: indirect — any person attached to that sales lead
 *         (or the order's sales lead) has the address.
 *  3. --fix removes only the relation(s) that fail the check — an email's other, still
 *     -valid links are left untouched.
 *  4. Default is a dry-run: every checked relation is printed (OK/MISMATCH/UNRESOLVABLE)
 *     with the address that was checked, so a human can judge a MISMATCH before trusting
 *     --fix. Each correction --fix makes is logged as a system activity
 *     (additional->email_id, same audit trail as manual link/unlink actions — see
 *     App\Services\Mail\EmailLinkAuditLog) tagged as an automated correction.
 */
class DetectMislinkedEmailEntities extends Command
{
    private const CHUNK_SIZE = 500;

    /**
     * Entity-link fields that carry a verifiable email address. activity_id is deliberately
     * excluded — an Activity isn't an address owner.
     */
    private const ENTITY_FIELDS = ['person_id', 'lead_id', 'sales_lead_id', 'order_id', 'clinic_id'];

    /**
     * Placeholder sender addresses that must never be evaluated — e.g. the fixed sender
     * used by the historical SugarCRM import, which never corresponds to a real contact
     * and would otherwise produce a MISMATCH on every field it's attached to.
     */
    private const IGNORED_ADDRESSES = ['import@sugarcrm.local'];

    protected $signature = 'emails:detect-mislinked-entities
                            {--fix : Remove every relation whose entity does not recognize the mail address (default: report only)}
                            {--id=* : Limit to these email IDs}';

    protected $description = "Find emails linked to 2+ entities where the mail's address isn't known to one of them, and optionally remove just that relation.";

    public function handle(ActivityRepository $activityRepository): int
    {
        $ids = array_map('intval', (array) $this->option('id'));

        $candidateIds = Email::query()
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
            ->get(['id', ...self::ENTITY_FIELDS])
            ->filter(fn (Email $email) => $this->linkedFieldCount($email) > 1)
            ->pluck('id');

        $this->components->info(sprintf(
            'Scanning %d email(s) linked to more than one entity%s',
            $candidateIds->count(),
            $this->option('fix') ? '' : ' (dry-run — pass --fix to correct)'
        ));

        $bar = $this->output->createProgressBar($candidateIds->count());
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%');
        $bar->start();

        $findings = collect();
        $ignored = 0;

        Email::query()
            ->whereIn('id', $candidateIds)
            ->with(['person', 'lead', 'clinic', 'salesLead.persons', 'order.salesLead.persons'])
            ->chunkById(self::CHUNK_SIZE, function (Collection $emails) use (&$findings, &$ignored, $bar) {
                foreach ($emails as $email) {
                    $address = $email->counterpartyEmail();

                    if ($address !== null && in_array(strtolower($address), self::IGNORED_ADDRESSES, true)) {
                        $ignored++;
                        $bar->advance();

                        continue;
                    }

                    foreach (self::ENTITY_FIELDS as $field) {
                        if (empty($email->{$field})) {
                            continue;
                        }

                        $findings->push($this->evaluate($email, $field, $address));
                    }

                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);

        if ($ignored > 0) {
            $this->components->info("Skipped {$ignored} email(s) with an ignored sender address (e.g. the SugarCRM import placeholder).");
        }

        if ($findings->isEmpty()) {
            $this->components->info('No candidates found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Email', 'Mail address', 'Field', 'Entity', 'Status'],
            $findings->map(fn (array $f) => [
                $f['email']->id,
                $f['address'] ?? '—',
                $f['field'],
                $f['label'],
                strtoupper($f['status']),
            ])->all()
        );

        $mismatches = $findings->where('status', 'mismatch');

        if ($mismatches->isEmpty()) {
            $this->components->info('No incorrect relations found.');

            return self::SUCCESS;
        }

        if (! $this->option('fix')) {
            $this->components->info(sprintf(
                'Dry run: %d incorrect relation(s) found across %d email(s). Re-run with --fix to remove them and log the correction.',
                $mismatches->count(),
                $mismatches->pluck('email.id')->unique()->count()
            ));

            return self::SUCCESS;
        }

        foreach ($mismatches as $finding) {
            $this->correct($finding, $activityRepository);
        }

        $this->components->info(sprintf(
            'Corrected %d relation(s) across %d email(s), logged as system activities.',
            $mismatches->count(),
            $mismatches->pluck('email.id')->unique()->count()
        ));

        return self::SUCCESS;
    }

    private function linkedFieldCount(Email $email): int
    {
        return collect(self::ENTITY_FIELDS)->filter(fn ($field) => ! empty($email->{$field}))->count();
    }

    /**
     * @return array{email: Email, field: string, address: ?string, label: string, status: string}
     */
    private function evaluate(Email $email, string $field, ?string $address): array
    {
        $entity = match ($field) {
            'person_id'     => $email->person,
            'lead_id'       => $email->lead,
            'clinic_id'     => $email->clinic,
            'sales_lead_id' => $email->salesLead,
            'order_id'      => $email->order,
        };

        if (! $entity) {
            return ['email' => $email, 'field' => $field, 'address' => $address, 'label' => '(niet gevonden)', 'status' => 'unresolvable'];
        }

        $label = $this->label($field, $entity);

        if ($address === null) {
            return ['email' => $email, 'field' => $field, 'address' => $address, 'label' => $label, 'status' => 'unresolvable'];
        }

        $known = match ($field) {
            'person_id', 'lead_id', 'clinic_id' => $entity->hasEmailAddress($address),
            'sales_lead_id'                     => $entity->persons->contains(fn (Person $person) => $person->hasEmailAddress($address)),
            'order_id'                          => (bool) $entity->salesLead?->persons->contains(fn (Person $person) => $person->hasEmailAddress($address)),
        };

        return ['email' => $email, 'field' => $field, 'address' => $address, 'label' => $label, 'status' => $known ? 'ok' : 'mismatch'];
    }

    private function label(string $field, $entity): string
    {
        return match ($field) {
            'order_id' => "#{$entity->id} {$entity->order_number}",
            default    => "#{$entity->id} {$entity->name}",
        };
    }

    /**
     * @param  array{email: Email, field: string, address: ?string, label: string, status: string}  $finding
     */
    private function correct(array $finding, ActivityRepository $activityRepository): void
    {
        $email = $finding['email'];
        $field = $finding['field'];
        $oldValue = $email->{$field};

        $email->forceFill([$field => null])->save();

        $activityRepository->createSystem([
            'title' => sprintf(
                'E-mail #%d: %s-koppeling verwijderd (correctie — mailadres %s niet bekend bij %s)',
                $email->id,
                $field,
                $finding['address'] ?? '(onbekend)',
                $finding['label']
            ),
            'additional' => [
                'field'            => $field,
                'old_value'        => $oldValue,
                'new_value'        => null,
                'email_id'         => $email->id,
                'email_subject'    => $email->subject,
                'checked_address'  => $finding['address'],
                'reason'           => 'address_not_known_to_entity',
                'source'           => 'emails:detect-mislinked-entities',
            ],
            'user_id' => null,
        ]);
    }
}
