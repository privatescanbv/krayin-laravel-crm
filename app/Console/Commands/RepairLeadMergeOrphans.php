<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Webkul\Activity\Traits\LogsActivity;
use Webkul\Lead\Repositories\LeadRepository;

/**
 * Reattach the rows that {@see LeadRepository::mergeLeads()} left behind.
 *
 * Until this was fixed, merging duplicate leads only re-pointed emails: activities (including
 * notes, calls and tasks), linked persons, anamnesis, tags, marketing data and custom attribute
 * values all stayed on the duplicate. Because the duplicate is only soft deleted, none of the
 * ON DELETE constraints fired - the rows still exist, they just hang off an invisible lead.
 *
 * The merge writes two audit activities on the primary lead, and those are the only trail back to
 * the duplicate. This command reconstructs duplicate => primary from them and replays the
 * transfer with the very same {@see LeadRepository::transferLeadRelations()} the merge now uses.
 *
 * One caveat: activities are NOT recoverable for merges done before the fix. Deleting a lead runs
 * {@see LogsActivity} `deleting`, which hard deletes `$model->activities()`.
 * Because the old merge left them on the duplicate, they were removed for good the moment it was
 * archived. Everything else (persons, anamnesis, tags, marketing data, attribute values) survived
 * and is what this command puts back.
 */
class RepairLeadMergeOrphans extends Command
{
    /**
     * Where the merge audit activities came from: title (anchored on type as well, so a hand
     * written note cannot produce a false hit) and the pattern holding the duplicate lead id.
     *
     * @see LeadRepository::addSystemActivity()
     * @see LeadRepository::addMergeNote()
     */
    private const MERGE_MARKERS = [
        ['type' => 'system', 'title' => 'System: Duplicate Lead Removed', 'pattern' => '/\(ID: (\d+)\)/'],
        ['type' => 'note', 'title' => 'Lead Merged', 'pattern' => '/^Lead #(\d+) /'],
    ];

    /**
     * Tables counted per merge pair, as table => column holding the lead id.
     *
     * @var array<string, string>
     */
    private const ORPHAN_TABLES = [
        'activities'          => 'lead_id',
        'emails'              => 'lead_id',
        'anamnesis'           => 'lead_id',
        'lead_persons'        => 'lead_id',
        'lead_tags'           => 'lead_id',
        'lead_marketing_data' => 'lead_id',
    ];

    protected $signature = 'leads:repair-merge-orphans
                            {--dry-run : Show proposed changes without persisting them}
                            {--lead=* : Limit the repair to these duplicate lead ID(s)}
                            {--since= : Only repair merges performed on or after this date}';

    protected $description = 'Reattach activities, persons, anamnesis and other rows left behind on previously merged duplicate leads.';

    public function handle(LeadRepository $leadRepository): int
    {
        $merges = $this->collectMerges();

        if ($merges->isEmpty()) {
            $this->components->info('No merged duplicate leads found.');

            return Command::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->components->info(sprintf(
            'Found %d merged duplicate lead(s)%s',
            $merges->count(),
            $dryRun ? ' (dry-run)' : ''
        ));

        $rows = [];
        $repaired = 0;
        $failed = 0;

        foreach ($merges as $duplicateId => $merge) {
            $primaryId = $merge['primary_id'];

            if (! DB::table('leads')->where('id', $primaryId)->whereNull('deleted_at')->exists()) {
                $rows[] = [$duplicateId, $primaryId, $merge['merged_at'], 'overgeslagen: doellead bestaat niet meer', ''];

                continue;
            }

            $counts = $this->countOrphans($duplicateId);
            $total = array_sum($counts);
            $salesLeads = DB::table('salesleads')->where('lead_id', $duplicateId)->count();
            $warning = $salesLeads > 0 ? sprintf('LET OP: %d saleslead(s), handmatig', $salesLeads) : '';

            if ($total === 0) {
                $rows[] = [$duplicateId, $primaryId, $merge['merged_at'], 'niets te herstellen', $warning];

                continue;
            }

            $summary = collect($counts)->filter()->map(fn ($count, $table) => "$table: $count")->implode(', ');

            if ($dryRun) {
                $rows[] = [$duplicateId, $primaryId, $merge['merged_at'], $summary, $warning];

                continue;
            }

            try {
                DB::transaction(fn () => $leadRepository->transferLeadRelations($primaryId, $duplicateId));

                $repaired++;
                $rows[] = [$duplicateId, $primaryId, $merge['merged_at'], "hersteld ($summary)", $warning];

                Log::info('Repaired lead merge orphans', [
                    'duplicate_lead_id' => $duplicateId,
                    'primary_lead_id'   => $primaryId,
                    'counts'            => $counts,
                ]);
            } catch (Throwable $e) {
                // One bad pair must not block the rest of the backlog.
                $failed++;
                $rows[] = [$duplicateId, $primaryId, $merge['merged_at'], 'MISLUKT: '.$e->getMessage(), $warning];

                Log::error('Failed to repair lead merge orphans', [
                    'duplicate_lead_id' => $duplicateId,
                    'primary_lead_id'   => $primaryId,
                    'error'             => $e->getMessage(),
                ]);
            }
        }

        $this->table(['Duplicaat', 'Primair', 'Samengevoegd op', 'Weesrijen', 'Waarschuwing'], $rows);

        if (! $dryRun) {
            $this->components->info(sprintf('%d lead(s) hersteld, %d mislukt.', $repaired, $failed));
        }

        return Command::SUCCESS;
    }

    /**
     * Rebuild duplicate lead id => ['primary_id', 'merged_at'] from the merge audit activities.
     * When a duplicate shows up more than once the earliest activity wins: that is the merge that
     * actually swallowed it.
     *
     * @return Collection<int, array{primary_id: int, merged_at: string}>
     */
    private function collectMerges(): Collection
    {
        $onlyLeads = array_map('intval', (array) $this->option('lead'));
        $since = $this->option('since') ? Carbon::parse($this->option('since')) : null;

        $merges = collect();

        foreach (self::MERGE_MARKERS as $marker) {
            $query = DB::table('activities')
                ->select('lead_id', 'comment', 'created_at')
                ->where('type', $marker['type'])
                ->where('title', $marker['title'])
                ->whereNotNull('lead_id');

            if ($since) {
                $query->where('created_at', '>=', $since);
            }

            foreach ($query->orderBy('created_at')->get() as $activity) {
                if (! preg_match($marker['pattern'], (string) $activity->comment, $matches)) {
                    continue;
                }

                $duplicateId = (int) $matches[1];

                if ($onlyLeads && ! in_array($duplicateId, $onlyLeads, true)) {
                    continue;
                }

                // A soft deleted lead is not proof of a merge (plain deletes exist too), but a lead
                // that is not deleted at all was clearly restored afterwards - leave it alone.
                $deletedAt = DB::table('leads')->where('id', $duplicateId)->value('deleted_at');

                if ($deletedAt === null) {
                    continue;
                }

                if (! $merges->has($duplicateId) || $activity->created_at < $merges[$duplicateId]['merged_at']) {
                    $merges[$duplicateId] = [
                        'primary_id' => (int) $activity->lead_id,
                        'merged_at'  => (string) $activity->created_at,
                    ];
                }
            }
        }

        return $merges;
    }

    /**
     * @return array<string, int>
     */
    private function countOrphans(int $duplicateId): array
    {
        $counts = [];

        foreach (self::ORPHAN_TABLES as $table => $column) {
            $counts[$table] = DB::table($table)->where($column, $duplicateId)->count();
        }

        $counts['attribute_values'] = DB::table('attribute_values')
            ->where('entity_type', 'leads')
            ->where('entity_id', $duplicateId)
            ->count();

        return $counts;
    }
}
