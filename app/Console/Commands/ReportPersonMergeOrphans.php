<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Repositories\PersonRepository;

/**
 * Report rows left behind on previously merged (soft-deleted) duplicate persons.
 *
 * Report-only: does not repair. Reconstructs duplicate => primary from the merge audit
 * activities written by {@see PersonRepository::mergePersons()}.
 */
class ReportPersonMergeOrphans extends Command
{
    /**
     * Where the merge audit activities came from. Includes the legacy "Person Merge" title so
     * merges done before the title change remain discoverable.
     *
     * @var list<array{type: string, title: string, pattern: string}>
     */
    private const MERGE_MARKERS = [
        ['type' => 'system', 'title' => 'System: Duplicate Person Removed', 'pattern' => '/\(ID: (\d+)\)/'],
        ['type' => 'note', 'title' => 'Person Merge', 'pattern' => '/\(ID: (\d+)\)/'],
        ['type' => 'note', 'title' => 'Person Merged', 'pattern' => '/^Person #(\d+) /'],
    ];

    /**
     * Tables counted per merge pair, as table => column holding the person id.
     *
     * @var array<string, string>
     */
    private const ORPHAN_TABLES = [
        'activities'                 => 'person_id',
        'emails'                     => 'person_id',
        'anamnesis'                  => 'person_id',
        'patient_messages'           => 'person_id',
        'patient_notifications'      => 'patient_id',
        'lead_persons'               => 'person_id',
        'saleslead_persons'          => 'person_id',
        'person_tags'                => 'person_id',
        'person_preferences'         => 'person_id',
        'activity_portal_persons'    => 'person_id',
        'order_person_confirmations' => 'person_id',
        'order_items'                => 'person_id',
        'afb_person_documents'       => 'person_id',
        'inkoop_persons'             => 'crm_id',
    ];

    protected $signature = 'persons:report-merge-orphans
                            {--person=* : Limit the report to these duplicate person ID(s)}';

    protected $description = 'Report activities, notifications and other rows left behind on previously merged duplicate persons (report-only, no writes).';

    public function handle(): int
    {
        $merges = $this->collectMerges();

        if ($merges->isEmpty()) {
            $this->components->info('No merged duplicate persons found.');

            return Command::SUCCESS;
        }

        $this->components->info(sprintf('Found %d merged duplicate person(s) (report-only)', $merges->count()));

        $rows = [];

        foreach ($merges as $duplicateId => $merge) {
            $primaryId = $merge['primary_id'];

            if (! DB::table('persons')->where('id', $primaryId)->whereNull('deleted_at')->exists()) {
                $rows[] = [$duplicateId, $primaryId, $merge['merged_at'], 'overgeslagen: doelpersoon bestaat niet meer', ''];

                continue;
            }

            $counts = $this->countOrphans($duplicateId, $primaryId);
            $total = array_sum($counts);

            $keycloakLeft = DB::table('persons')->where('id', $duplicateId)->value('keycloak_user_id');
            $warning = ! empty($keycloakLeft) ? 'LET OP: keycloak_user_id nog op duplicaat' : '';

            if ($total === 0 && empty($warning)) {
                $rows[] = [$duplicateId, $primaryId, $merge['merged_at'], 'geen weesrijen', ''];

                continue;
            }

            $summary = collect($counts)->filter()->map(fn ($count, $table) => "$table: $count")->implode(', ');
            if ($summary === '' && $warning !== '') {
                $summary = 'geen weesrijen';
            }

            $rows[] = [$duplicateId, $primaryId, $merge['merged_at'], $summary, $warning];
        }

        $this->table(['Duplicaat', 'Primair', 'Samengevoegd op', 'Weesrijen', 'Waarschuwing'], $rows);

        return Command::SUCCESS;
    }

    /**
     * Rebuild duplicate person id => ['primary_id', 'merged_at'] from the merge audit activities.
     * When a duplicate shows up more than once the earliest activity wins.
     *
     * @return Collection<int, array{primary_id: int, merged_at: string}>
     */
    private function collectMerges(): Collection
    {
        $onlyPersons = array_map('intval', (array) $this->option('person'));

        $merges = collect();

        foreach (self::MERGE_MARKERS as $marker) {
            $query = DB::table('activities')
                ->select('person_id', 'comment', 'created_at')
                ->where('type', $marker['type'])
                ->where('title', $marker['title'])
                ->whereNotNull('person_id');

            foreach ($query->orderBy('created_at')->get() as $activity) {
                if (! preg_match($marker['pattern'], (string) $activity->comment, $matches)) {
                    continue;
                }

                $duplicateId = (int) $matches[1];

                if ($onlyPersons && ! in_array($duplicateId, $onlyPersons, true)) {
                    continue;
                }

                $deletedAt = DB::table('persons')->where('id', $duplicateId)->value('deleted_at');

                if ($deletedAt === null) {
                    continue;
                }

                if (! $merges->has($duplicateId) || $activity->created_at < $merges[$duplicateId]['merged_at']) {
                    $merges[$duplicateId] = [
                        'primary_id' => (int) $activity->person_id,
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
    private function countOrphans(int $duplicateId, int $primaryId): array
    {
        $counts = [];

        foreach (self::ORPHAN_TABLES as $table => $column) {
            if ($table === 'activities') {
                $counts[$table] = $this->countActivityOrphans($duplicateId, $primaryId);

                continue;
            }

            $value = $column === 'crm_id' ? (string) $duplicateId : $duplicateId;

            $counts[$table] = DB::table($table)->where($column, $value)->count();
        }

        $counts['attribute_values'] = DB::table('attribute_values')
            ->where('entity_type', 'persons')
            ->where('entity_id', $duplicateId)
            ->count();

        $counts['leads_contact_person'] = DB::table('leads')
            ->where('contact_person_id', $duplicateId)
            ->count();

        $counts['salesleads_contact_person'] = DB::table('salesleads')
            ->where('contact_person_id', $duplicateId)
            ->count();

        return $counts;
    }

    /**
     * Activities left on the duplicate that match title+status of one on the primary were skipped
     * on purpose during merge. Those are not orphans.
     */
    private function countActivityOrphans(int $duplicateId, int $primaryId): int
    {
        $existing = DB::table('activities')
            ->where('person_id', $primaryId)
            ->get(['title', 'status'])
            ->map(fn ($row) => $row->title.'|'.$row->status)
            ->all();

        return DB::table('activities')
            ->where('person_id', $duplicateId)
            ->get(['title', 'status'])
            ->reject(fn ($row) => in_array($row->title.'|'.$row->status, $existing, true))
            ->count();
    }
}
