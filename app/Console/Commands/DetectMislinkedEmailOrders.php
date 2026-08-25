<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Email\Models\Email;

/**
 * Finds emails whose person_id and order_id are both set, but the linked person does not
 * actually appear on that order (order.salesLead.persons) — the class of mislink discussed
 * for email #64591 (Bob Karis / Margot Jansen-Laarakker's order), caused by a manual
 * mistake or a shared-email-address ambiguity in the auto-link algorithm
 * (see App\Services\Mail\EmailEntityLinker).
 *
 * Default is a dry-run report; pass --fix to actually clear order_id on every mismatch
 * found. Every correction is logged as a system activity tagged with additional->email_id
 * (findable via App\Services\Mail\EmailLinkAuditLog, the same audit trail shown in the
 * email's "Logboek" panel) so a later question about a missing order link has an answer.
 */
class DetectMislinkedEmailOrders extends Command
{
    private const CHUNK_SIZE = 500;

    protected $signature = 'emails:detect-mislinked-orders
                            {--fix : Remove order_id from every mismatch found (default: report only)}
                            {--id=* : Limit to these email IDs}';

    protected $description = 'Find emails linked to a person who does not appear on the linked order, and optionally unlink the order.';

    public function handle(ActivityRepository $activityRepository): int
    {
        $ids = array_map('intval', (array) $this->option('id'));

        $baseQuery = fn () => Email::query()
            ->whereNotNull('person_id')
            ->whereNotNull('order_id')
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids));

        $total = $baseQuery()->count();

        $this->components->info(sprintf(
            'Scanning %d email(s) with both a person and an order link%s',
            $total,
            $this->option('fix') ? '' : ' (dry-run — pass --fix to correct)'
        ));

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%');
        $bar->start();

        $mismatches = collect();
        $unresolvable = collect();

        $baseQuery()->with(['person', 'order.salesLead'])
            ->chunkById(self::CHUNK_SIZE, function (Collection $emails) use (&$mismatches, &$unresolvable, $bar) {
                foreach ($emails as $email) {
                    if (! $email->person || ! $email->order) {
                        $unresolvable->push($email);
                        $bar->advance();

                        continue;
                    }

                    $belongsToOrder = Order::query()
                        ->whereKey($email->order_id)
                        ->forPerson($email->person)
                        ->exists();

                    if (! $belongsToOrder) {
                        $mismatches->push($email);
                    }

                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);

        if ($unresolvable->isNotEmpty()) {
            $this->components->warn(sprintf(
                'Skipped %d email(s) — the linked person or order no longer exists: %s',
                $unresolvable->count(),
                $unresolvable->pluck('id')->implode(', ')
            ));
        }

        if ($mismatches->isEmpty()) {
            $this->components->info('No mismatches found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Email', 'Person ID', 'Person', 'Order ID', 'Order #'],
            $mismatches->map(fn (Email $email) => [
                $email->id,
                $email->person_id,
                $email->person->name,
                $email->order_id,
                $email->order->order_number,
            ])->all()
        );

        if (! $this->option('fix')) {
            $this->components->info(sprintf(
                'Dry run: %d mismatch(es) found. Re-run with --fix to remove the order link and log the correction.',
                $mismatches->count()
            ));

            return self::SUCCESS;
        }

        foreach ($mismatches as $email) {
            $this->correct($email, $activityRepository);
        }

        $this->components->info("Corrected {$mismatches->count()} email(s) and logged each as a system activity.");

        return self::SUCCESS;
    }

    private function correct(Email $email, ActivityRepository $activityRepository): void
    {
        $oldOrderId = $email->order_id;

        $email->forceFill(['order_id' => null])->save();

        $activityRepository->createSystem([
            'title' => sprintf(
                'E-mail #%d: order-koppeling verwijderd (correctie — %s stond niet op order #%d)',
                $email->id,
                $email->person->name,
                $oldOrderId
            ),
            'additional' => [
                'field'         => 'order_id',
                'old_value'     => $oldOrderId,
                'new_value'     => null,
                'email_id'      => $email->id,
                'email_subject' => $email->subject,
                'reason'        => 'person_not_on_order',
                'source'        => 'emails:detect-mislinked-orders',
            ],
            'user_id' => null,
        ]);
    }
}
