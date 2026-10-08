<?php

namespace App\Console\Commands;

use App\Actions\Leads\RecordLeadContactAction;
use App\Actions\Leads\SnapshotLeadCustomerTypeAction;
use App\Services\CustomerHistory\CustomerHistoryService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Webkul\Lead\Models\Lead;

/**
 * Classifies existing leads as they stood on their creation day. Idempotent: the
 * snapshot is always measured at leads.created_at, so re-running changes nothing
 * unless the underlying history was corrected.
 */
class BackfillLeadCustomerType extends Command
{
    protected $signature = 'leads:backfill-customer-type
                            {--chunk=100 : Leads per batch (one transaction each)}
                            {--missing : Only leads without a customer type yet}
                            {--dry-run : Count the outcome per type without writing}';

    protected $description = 'Store the customer type snapshot (new / existing buyer / existing non-buyer) and, for lost leads, whether there was contact.';

    public function handle(CustomerHistoryService $customerHistory, SnapshotLeadCustomerTypeAction $snapshot, RecordLeadContactAction $contact): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = Lead::query()
            ->select(['id', 'created_at', 'lead_pipeline_stage_id'])
            ->when($this->option('missing'), fn ($q) => $q->whereNull('customer_type'));

        $bar = $this->output->createProgressBar($query->count());
        $tally = [];

        $query->chunkById(max(1, (int) $this->option('chunk')), function (Collection $leads) use ($dryRun, $customerHistory, $snapshot, $contact, $bar, &$tally) {
            // One commit per chunk: a commit per lead costs an fsync each and made the run ~10x slower.
            // Retried on deadlock, since queue workers update the same leads concurrently.
            DB::transaction(function () use ($leads, $dryRun, $customerHistory, $snapshot, $contact, $bar, &$tally) {
                foreach ($leads as $lead) {
                    if ($dryRun) {
                        $type = $customerHistory->forLead($lead)?->customerType->label() ?? 'Geen persoon gekoppeld';
                        $tally[$type] = ($tally[$type] ?? 0) + 1;
                    } else {
                        $snapshot->execute($lead);
                        $contact->execute($lead);
                    }

                    $bar->advance();
                }
            }, attempts: 5);
        });

        $bar->finish();
        $this->newLine(2);

        if ($dryRun) {
            ksort($tally);
            $this->table(['Klanttype', 'Leads'], collect($tally)->map(fn ($count, $type) => [$type, $count])->values());
            $this->components->info('Dry-run: niets opgeslagen.');
        } else {
            $this->components->info('Klanttype opgeslagen op alle leads.');
        }

        return self::SUCCESS;
    }
}
