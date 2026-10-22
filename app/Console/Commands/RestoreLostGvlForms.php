<?php

namespace App\Console\Commands;

use App\Enums\FormStatus;
use App\Models\Anamnesis;
use App\Models\AnamnesisGvlForm;
use App\Services\Anamnesis\GvlAuditLogger;
use App\Services\FormService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Throwable;
use Webkul\Activity\Models\Activity;

/**
 * Recreate anamnesis_gvl_forms rows that were lost when a lead merge deleted the anamnesis they
 * hung off (cascade). Before the merge screen asked which anamnesis to keep, the newest one won,
 * even when the other one held the (completed) GVL.
 *
 * The only trail left is the audit activity {@see GvlAuditLogger} wrote: it holds the gvl_form_id,
 * type, person and the anamnesis the form belonged to. Merging moves that activity to the
 * surviving lead, so the form is reattached to that lead's anamnesis for the same person.
 */
class RestoreLostGvlForms extends Command
{
    protected $signature = 'gvl:restore-lost-forms
                            {--dry-run : Show what would be restored without persisting it}
                            {--lead=* : Limit to these (surviving) lead ID(s)}';

    protected $description = 'Reattach GVL forms whose anamnesis was deleted during a lead merge, using the GVL audit activities.';

    public function handle(FormService $formService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $rows = [];

        foreach ($this->auditTrailPerForm() as $gvlFormId => $activities) {
            $latest = $activities->first();
            $info = $latest->additional;

            if (AnamnesisGvlForm::where('gvl_form_id', $gvlFormId)->exists()) {
                continue; // still there
            }

            if (Anamnesis::whereKey($info['anamnesis_id'] ?? null)->exists()) {
                continue; // anamnesis survived, so the form was removed on purpose
            }

            $target = Anamnesis::query()
                ->where('person_id', $info['person_id'] ?? null)
                ->when($latest->order_id, fn ($q) => $q->where('order_id', $latest->order_id))
                ->when(! $latest->order_id && $latest->sales_lead_id, fn ($q) => $q->where('sales_id', $latest->sales_lead_id))
                ->when(! $latest->order_id && ! $latest->sales_lead_id, fn ($q) => $q->where('lead_id', $latest->lead_id))
                ->first();

            if ($target === null) {
                $rows[] = [$gvlFormId, $latest->lead_id, '-', '-', 'OVERGESLAGEN: geen anamnese voor deze persoon gevonden'];

                continue;
            }

            $completed = $activities->first(fn (Activity $activity) => str_contains($activity->title, ' afgerond:'));
            $status = $completed ? FormStatus::Completed : $this->fetchStatus($formService, $gvlFormId);

            if (! $dryRun) {
                AnamnesisGvlForm::create([
                    'anamnesis_id'    => $target->id,
                    'gvl_form_id'     => $gvlFormId,
                    'gvl_form_type'   => $info['gvl_form_type'] ?? null,
                    'gvl_form_status' => $status,
                    'completed_at'    => $completed?->created_at,
                ]);
            }

            $rows[] = [$gvlFormId, $latest->lead_id, $target->id, $status?->value ?? '-', $dryRun ? 'zou herstellen' : 'hersteld'];
        }

        if ($rows === []) {
            $this->components->info('Geen verloren GVL-formulieren gevonden.');

            return Command::SUCCESS;
        }

        $this->table(['GVL form', 'Lead', 'Anamnese', 'Status', 'Resultaat'], $rows);

        return Command::SUCCESS;
    }

    /**
     * GVL audit activities grouped per gvl_form_id, newest first.
     *
     * @return Collection<string, Collection<int, Activity>>
     */
    private function auditTrailPerForm(): Collection
    {
        return Activity::query()
            ->where('type', 'system')
            ->whereNotNull('additional->gvl_form_id')
            ->when($this->option('lead'), fn ($q, $leadIds) => $q->whereIn('lead_id', $leadIds))
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (Activity $activity) => (string) $activity->additional['gvl_form_id']);
    }

    private function fetchStatus(FormService $formService, string $gvlFormId): ?FormStatus
    {
        try {
            return $formService->getFormStatusAsString((int) $gvlFormId);
        } catch (Throwable) {
            return null;
        }
    }
}
