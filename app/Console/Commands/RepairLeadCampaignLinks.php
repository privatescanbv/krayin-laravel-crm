<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Backfill lead_marketing_data.campaign_id so the lead UI can resolve marketing_campaigns.
 *
 * Gravity Forms often stored origin_form (and sometimes a Campaign external_id in description)
 * without a campaign_id row. Do not invent campaigns from gad_campaignid or UTM campaign names:
 * those are Google Ads / Mailchimp identifiers, not marketing_campaigns.external_id.
 */
class RepairLeadCampaignLinks extends Command
{
    /**
     * Gravity Forms origin_form labels → marketing_campaigns.external_id (CampaignSeeder).
     *
     * @var array<string, string>
     */
    private const ORIGIN_FORM_CAMPAIGN_EXTERNAL_IDS = [
        'Diagnose Formulier'            => '39153848-eea7-9f47-cc53-5eb952a33583',
        'Diagnose Formulier Nekhernia'  => '39153848-eea7-9f47-cc53-5eb952a33583',
        'Contact'                       => '81289196-5d51-817a-c8c5-5eb9419e4d9b',
        'Bel mij terug'                 => '373d13b0-7386-3fe6-c887-5eb94c022c1a',
        'Afspraak maken MRI-scan'       => '26221d52-feff-19b8-3680-64ae9e1224c1',
    ];

    protected $signature = 'leads:repair-campaign-links
                            {--dry-run : Show proposed changes without persisting them}
                            {--lead=* : Limit the repair to these lead ID(s)}';

    protected $description = 'Backfill lead campaign_id from description UUID or origin_form when the campaign exists.';

    /** @var Collection<int, string> */
    private Collection $knownCampaignIds;

    public function handle(): int
    {
        $this->knownCampaignIds = DB::table('marketing_campaigns')->pluck('external_id');

        $candidates = $this->collectCandidates();

        if ($candidates->isEmpty()) {
            $this->components->info('No leads missing a campaign link.');

            return Command::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $rows = [];
        $repaired = 0;

        foreach ($candidates as $candidate) {
            $resolved = $this->resolveExternalId(
                (string) ($candidate->description ?? ''),
                $candidate->origin_form !== null ? (string) $candidate->origin_form : null,
            );

            if ($resolved === null) {
                $rows[] = [$candidate->id, '-', '-', 'overgeslagen: geen herstelbare campaign'];

                continue;
            }

            [$externalId, $source] = $resolved;

            if ($dryRun) {
                $rows[] = [$candidate->id, $source, $externalId, 'zou campaign_id zetten'];

                continue;
            }

            $this->persistCampaignId(
                (int) $candidate->id,
                $externalId,
                $candidate->campaign_row_id !== null ? (int) $candidate->campaign_row_id : null,
            );

            $repaired++;
            $rows[] = [$candidate->id, $source, $externalId, 'campaign_id gezet'];
        }

        $this->table(['Lead', 'Bron', 'Campaign', 'Actie'], $rows);

        $this->components->info($dryRun
            ? sprintf('%d lead(s) zonder campaign-koppeling gevonden (dry-run)', $candidates->count())
            : sprintf('%d lead campaign-koppeling(en) hersteld', $repaired));

        return Command::SUCCESS;
    }

    /**
     * @return Collection<int, object{id: int, description: ?string, origin_form: ?string, campaign_row_id: ?int}>
     */
    private function collectCandidates(): Collection
    {
        $leadIds = array_values(array_filter(
            array_map('intval', (array) $this->option('lead')),
            static fn (int $id): bool => $id > 0,
        ));

        $query = DB::table('leads')
            ->leftJoin('lead_marketing_data as campaign', function ($join) {
                $join->on('campaign.lead_id', '=', 'leads.id')
                    ->where('campaign.key', '=', 'campaign_id');
            })
            ->leftJoin('marketing_campaigns as mc', 'mc.external_id', '=', 'campaign.value')
            ->leftJoin('lead_marketing_data as origin', function ($join) {
                $join->on('origin.lead_id', '=', 'leads.id')
                    ->where('origin.key', '=', 'origin_form');
            })
            ->whereNull('leads.deleted_at')
            ->whereNull('mc.id')
            ->select(
                'leads.id',
                'leads.description',
                'origin.value as origin_form',
                'campaign.id as campaign_row_id',
            );

        if ($leadIds !== []) {
            $query->whereIn('leads.id', $leadIds);
        } else {
            $query->where(function ($q) {
                $q->where('leads.description', 'like', '%Campaign external_id:%')
                    ->orWhereIn('origin.value', array_keys(self::ORIGIN_FORM_CAMPAIGN_EXTERNAL_IDS));
            });
        }

        return $query->get()->unique('id')->values();
    }

    /**
     * @return array{0: string, 1: string}|null [external_id, source]
     */
    private function resolveExternalId(string $description, ?string $originForm): ?array
    {
        if (preg_match('/Campaign external_id:\s*(\S+)/i', $description, $matches) === 1) {
            $fromDescription = trim($matches[1]);

            if ($fromDescription !== '' && $this->knownCampaignIds->contains($fromDescription)) {
                return [$fromDescription, 'description'];
            }
        }

        $originForm = trim((string) $originForm);
        $mapped = self::ORIGIN_FORM_CAMPAIGN_EXTERNAL_IDS[$originForm] ?? null;

        if ($mapped !== null && $this->knownCampaignIds->contains($mapped)) {
            return [$mapped, 'origin_form'];
        }

        return null;
    }

    private function persistCampaignId(int $leadId, string $externalId, ?int $existingRowId): void
    {
        $now = now();

        if ($existingRowId !== null) {
            DB::table('lead_marketing_data')->where('id', $existingRowId)->update([
                'value'      => $externalId,
                'updated_at' => $now,
            ]);

            return;
        }

        DB::table('lead_marketing_data')->insert([
            'lead_id'    => $leadId,
            'key'        => 'campaign_id',
            'value'      => $externalId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
