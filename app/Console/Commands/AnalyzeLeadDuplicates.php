<?php

namespace App\Console\Commands;

use App\Services\DuplicateReasonHelpers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Webkul\Lead\Models\Lead as LeadModel;
use Webkul\Lead\Repositories\LeadRepository;

class AnalyzeLeadDuplicates extends Command
{
    use DuplicateReasonHelpers;

    /**
     * The name and signature of the console command.
     *
     * Usage: sail artisan leads:analyze-duplicates {leadId} [--limit=50] [--no-filter]
     */
    protected $signature = 'leads:analyze-duplicates
                            {leadId : Lead ID to analyze}
                            {--limit=50 : Limit number of duplicates to print}
                            {--no-filter : Show all potential matches without time/status filtering}';

    /**
     * The console command description.
     */
    protected $description = 'Analyze duplicate detection for a specific lead with detailed breakdown';

    public function handle(LeadRepository $leadRepository)
    {
        $leadId = (int) $this->argument('leadId');
        $limit = (int) $this->option('limit');
        $noFilter = (bool) $this->option('no-filter');

        /** @var LeadModel|null $lead */
        $lead = LeadModel::with(['stage', 'pipeline', 'user'])->find($leadId);
        if (! $lead) {
            $this->error("Lead #{$leadId} not found");

            return 1;
        }

        // Extract lead signals
        [$leadEmails, $leadPhones] = [$this->extractValues($lead->emails), $this->extractValues($lead->phones)];

        $this->info('Analyzing duplicate signals for lead:');
        $this->table([
            'ID', 'Name', 'Stage', 'Created At',
        ], [[
            $lead->id,
            $lead->name,
            optional($lead->stage)->name ?? '-',
            optional($lead->created_at)?->toDateTimeString() ?? '-',
        ]]);

        $this->line('Signals:');
        $this->line('- Emails: '.(empty($leadEmails) ? '-' : implode(', ', $leadEmails)));
        $this->line('- Phones: '.(empty($leadPhones) ? '-' : implode(', ', $leadPhones)));
        $this->line('- Names: '.trim(implode(' ', array_filter([$lead->first_name, $lead->last_name]))).($lead->married_name ? (' | married: '.$lead->married_name) : ''));

        // Get duplicates via repository (direct computation)
        $duplicates = $leadRepository->findPotentialDuplicatesDirectly($lead);

        // Optionally remove filters (time/status) by recomputing naive matches against all leads
        if ($noFilter) {
            $duplicates = $this->computeNaiveDuplicates($lead, $leadEmails, $leadPhones);
        }

        $total = $duplicates->count();
        $this->info("\nFound {$total} potential duplicates".($noFilter ? ' (no filters)' : ''));

        // Build reason breakdown
        $rows = [];
        $counts = [
            'email' => 0,
            'phone' => 0,
            'name'  => 0,
        ];

        $unknowns = [];
        foreach ($duplicates as $dup) {
            $dupArr = [
                'first_name'   => $dup->first_name,
                'last_name'    => $dup->last_name,
                'married_name' => $dup->married_name,
                'emails'       => $dup->emails,
                'phones'       => $dup->phones,
            ];
            $leadArr = [
                'first_name'   => $lead->first_name,
                'last_name'    => $lead->last_name,
                'married_name' => $lead->married_name,
                'emails'       => $lead->emails,
                'phones'       => $lead->phones,
            ];
            $reasons = $this->computeReasons($leadArr, $dupArr, $leadEmails, $leadPhones);
            foreach ($reasons as $type => $values) {
                if (! empty($values)) {
                    $counts[$type]++;
                }
            }

            $rows[] = [
                $dup->id,
                trim(implode(' ', array_filter([$dup->first_name, $dup->last_name]))),
                optional($dup->stage)->name ?? '-',
                optional($dup->created_at)?->toDateTimeString() ?? '-',
                implode(', ', $reasons['email'] ?? []),
                implode(', ', $reasons['phone'] ?? []),
                $reasons['name_reason'] ?? '-',
            ];

            if (empty($reasons['email']) && empty($reasons['phone']) && empty($reasons['name_reason'])) {
                $unknowns[] = $dup;
            }
        }

        // Print breakdown
        $this->line('');
        $this->info('Reason breakdown:');
        $this->info("- Email matches: {$counts['email']}");
        $this->info("- Phone matches: {$counts['phone']}");
        $this->info("- Name matches: {$counts['name']}");

        // Print table of duplicates (limited)
        $this->line('');
        $this->info('Duplicates:');
        $headers = ['ID', 'Name', 'Stage', 'Created At', 'Matched Emails', 'Matched Phones', 'Name Reason'];
        $this->table($headers, array_slice($rows, 0, max(1, $limit)));

        if ($limit < $total) {
            $this->line("(showing {$limit} of {$total})");
        }

        // Print extra diagnostics for unknown reasons
        if (! empty($unknowns)) {
            $this->line('');
            $this->warn('Duplicates without explicit reasons (diagnostics):');
            $diagRows = [];
            foreach (array_slice($unknowns, 0, max(1, $limit)) as $u) {
                $diagRows[] = [
                    $u->id,
                    trim(implode(' ', array_filter([$u->first_name, $u->last_name]))),
                    $this->stringify($u->emails),
                    $this->stringify($u->phones),
                ];
            }
            $this->table(['ID', 'Name', 'Raw Emails', 'Raw Phones'], $diagRows);
        }

        return 0;
    }

    private function extractValues($field): array
    {
        if (is_string($field)) {
            $decoded = json_decode($field, true) ?: [];
        } elseif (is_array($field)) {
            $decoded = $field;
        } else {
            $decoded = [];
        }

        $values = [];
        foreach ($decoded as $item) {
            if (is_array($item) && ! empty($item['value'])) {
                $values[] = (string) $item['value'];
            } elseif (is_string($item)) {
                $values[] = $item;
            }
        }

        return array_values(array_unique($values));
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($digits, '31') && strlen($digits) >= 10) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }

    private function computeReasons(LeadModel $lead, $dup, array $leadEmails, array $leadPhones): array
    {
        $dupEmails = $this->extractValues($dup->emails);
        $dupPhones = $this->extractValues($dup->phones);

        $emailMatches = array_values(array_intersect(
            array_map('strtolower', $leadEmails),
            array_map('strtolower', $dupEmails)
        ));

        $leadPhonesNorm = array_map(fn ($p) => $this->normalizePhone($p), $leadPhones);
        $dupPhonesNorm = array_map(fn ($p) => $this->normalizePhone($p), $dupPhones);
        $phoneMatches = array_values(array_filter($leadPhonesNorm, fn ($p) => in_array($p, $dupPhonesNorm, true)));

        // Name reasons (exact, nickname-variant, married/last swap)
        $nameReason = null;
        $leadFull = strtolower(trim(($lead->first_name ?? '').' '.($lead->last_name ?? '')));
        $dupFull = strtolower(trim(($dup->first_name ?? '').' '.($dup->last_name ?? '')));
        if ($leadFull && $dupFull && $leadFull === $dupFull) {
            $nameReason = 'first+last exact';
        } elseif (! empty($lead->married_name)) {
            $marriedSwap1 = strtolower(trim(($lead->first_name ?? '').' '.($lead->married_name ?? '')));
            $marriedSwap2 = strtolower(trim(($lead->first_name ?? '').' '.($lead->last_name ?? '')));
            if ($dupFull === $marriedSwap1 || $dupFull === $marriedSwap2) {
                $nameReason = 'married/last swap';
            }
        } else {
            // nickname variations for first name
            $first = (string) ($lead->first_name ?? '');
            $variants = $this->getNameVariations($first);
            if (! empty($variants)) {
                foreach ($variants as $variant) {
                    $variantFull = strtolower(trim($variant.' '.($lead->last_name ?? '')));
                    if ($variantFull && $variantFull === $dupFull) {
                        $nameReason = 'nickname variant ('.$variant.')';
                        break;
                    }
                }
            }
        }

        return [
            'email'       => $emailMatches,
            'phone'       => $phoneMatches,
            'name_reason' => $nameReason,
        ];
    }

    private function stringify($value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_array($value)) {
            return json_encode($value);
        }

        return json_encode($value);
    }

    private function getNameVariations(string $name): array
    {
        $variations = [$name];
        $nicknameMap = [
            'John'        => ['Johnny', 'Jon', 'Jack'],
            'Johnny'      => ['John', 'Jon'],
            'Jon'         => ['John', 'Johnny'],
            'Jack'        => ['John', 'Jackson'],
            'William'     => ['Will', 'Bill', 'Billy'],
            'Will'        => ['William', 'Bill'],
            'Bill'        => ['William', 'Will', 'Billy'],
            'Billy'       => ['William', 'Bill'],
            'Robert'      => ['Bob', 'Rob', 'Bobby'],
            'Bob'         => ['Robert', 'Bobby'],
            'Rob'         => ['Robert', 'Bobby'],
            'Bobby'       => ['Robert', 'Bob'],
            'Richard'     => ['Rick', 'Dick', 'Rich'],
            'Rick'        => ['Richard', 'Rich'],
            'Rich'        => ['Richard', 'Rick'],
            'Michael'     => ['Mike', 'Mickey'],
            'Mike'        => ['Michael', 'Mickey'],
            'Mickey'      => ['Michael', 'Mike'],
            'David'       => ['Dave', 'Davey'],
            'Dave'        => ['David', 'Davey'],
            'Davey'       => ['David', 'Dave'],
            'Christopher' => ['Chris', 'Christie'],
            'Chris'       => ['Christopher', 'Christie'],
            'Christie'    => ['Christopher', 'Chris'],
            'Elizabeth'   => ['Liz', 'Beth', 'Betty', 'Lizzy'],
            'Liz'         => ['Elizabeth', 'Beth', 'Betty'],
            'Beth'        => ['Elizabeth', 'Liz', 'Betty'],
            'Betty'       => ['Elizabeth', 'Liz', 'Beth'],
            'Lizzy'       => ['Elizabeth', 'Liz'],
        ];
        if (isset($nicknameMap[$name])) {
            $variations = array_merge($variations, $nicknameMap[$name]);
        }

        return array_values(array_unique(array_filter($variations)));
    }

    private function computeNaiveDuplicates(LeadModel $lead, array $leadEmails, array $leadPhones)
    {
        // Load a superset of candidates to evaluate in PHP
        $query = LeadModel::with(['stage', 'pipeline', 'user'])
            ->where('id', '!=', $lead->id);

        // If DB supports JSON querying, optionally narrow by any email/phone value
        // For sqlite in tests, we keep it broad
        $candidates = $query->get();

        $results = collect();
        foreach ($candidates as $dup) {
            $reasons = $this->computeReasons($lead, $dup, $leadEmails, $leadPhones);
            if (! empty($reasons['email']) || ! empty($reasons['phone']) || $reasons['name_reason']) {
                $results->push($dup);
            }
        }

        return $results;
    }
}
