<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-point sales whose lead was soft-deleted, so the sales view can open again.
 *
 * Deleting a lead used to leave salesleads.lead_id hanging off the invisible row. The delete
 * guard now prevents that; this command repairs the leftovers.
 */
class RepairOrphanedSalesLeads extends Command
{
    protected $signature = 'leads:repair-orphaned-sales
                            {--dry-run : Show proposed changes without persisting them}';

    protected $description = 'Re-point sales leads whose linked lead was deleted, and mark them lost when all orders are lost.';

    public function handle(): int
    {
        $orphans = DB::table('salesleads as sl')
            ->leftJoin('leads as l', 'l.id', '=', 'sl.lead_id')
            ->where(function ($query) {
                $query->whereNull('l.id')
                    ->orWhereNotNull('l.deleted_at');
            })
            ->select('sl.id', 'sl.name', 'sl.lead_id', 'sl.pipeline_stage_id', 'sl.closed_at')
            ->get();

        if ($orphans->isEmpty()) {
            $this->components->info('No sales linked to a deleted lead.');

            return Command::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $rows = [];
        $repaired = 0;

        foreach ($orphans as $orphan) {
            $liveLeadId = $this->findLiveLeadIdForSales((int) $orphan->id);

            if ($liveLeadId === null) {
                $rows[] = [$orphan->id, $orphan->lead_id, '-', 'overgeslagen: geen actieve lead voor dezelfde persoon'];

                continue;
            }

            $lostStageId = $this->lostStageIdFor((int) $orphan->pipeline_stage_id);
            $markLost = $lostStageId && $this->allOrdersAreLost((int) $orphan->id);
            $action = $markLost
                ? "lead_id → {$liveLeadId}, stage → lost ({$lostStageId})"
                : "lead_id → {$liveLeadId}";

            if ($dryRun) {
                $rows[] = [$orphan->id, $orphan->lead_id, $liveLeadId, $action];

                continue;
            }

            $update = ['lead_id' => $liveLeadId, 'updated_at' => now()];

            if ($markLost) {
                $update['pipeline_stage_id'] = $lostStageId;
                if ($orphan->closed_at === null) {
                    $orderClosedAt = DB::table('orders')
                        ->where('sales_lead_id', $orphan->id)
                        ->max('closed_at');
                    $update['closed_at'] = $orderClosedAt ?: now()->toDateString();
                }
            }

            DB::table('salesleads')->where('id', $orphan->id)->update($update);

            $repaired++;
            $rows[] = [$orphan->id, $orphan->lead_id, $liveLeadId, $action];
        }

        $this->table(['Sales', 'Oude lead', 'Nieuwe lead', 'Actie'], $rows);

        $this->components->info($dryRun
            ? sprintf('%d wees-sales gevonden (dry-run)', $orphans->count())
            : sprintf('%d wees-sales hersteld', $repaired));

        return Command::SUCCESS;
    }

    private function findLiveLeadIdForSales(int $salesLeadId): ?int
    {
        $personIds = DB::table('saleslead_persons')
            ->where('saleslead_id', $salesLeadId)
            ->pluck('person_id');

        if ($personIds->isEmpty()) {
            return null;
        }

        $liveLeadId = DB::table('lead_persons as lp')
            ->join('leads as l', 'l.id', '=', 'lp.lead_id')
            ->whereIn('lp.person_id', $personIds)
            ->whereNull('l.deleted_at')
            ->orderByDesc('l.updated_at')
            ->value('l.id');

        return $liveLeadId !== null ? (int) $liveLeadId : null;
    }

    private function lostStageIdFor(int $pipelineStageId): ?int
    {
        $pipelineId = DB::table('lead_pipeline_stages')
            ->where('id', $pipelineStageId)
            ->value('lead_pipeline_id');

        if (! $pipelineId) {
            return null;
        }

        $lostStageId = DB::table('lead_pipeline_stages')
            ->where('lead_pipeline_id', $pipelineId)
            ->where('is_lost', 1)
            ->orderBy('sort_order')
            ->value('id');

        return $lostStageId !== null ? (int) $lostStageId : null;
    }

    private function allOrdersAreLost(int $salesLeadId): bool
    {
        $orders = DB::table('orders as o')
            ->leftJoin('lead_pipeline_stages as s', 's.id', '=', 'o.pipeline_stage_id')
            ->where('o.sales_lead_id', $salesLeadId)
            ->get(['o.id', 's.is_lost']);

        if ($orders->isEmpty()) {
            return false;
        }

        return $orders->every(fn ($order) => (int) $order->is_lost === 1);
    }
}
