<?php

namespace App\Console\Commands;

use App\Services\DuplicateReasonHelpers;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

abstract class AbstractAnalyzeDuplicates extends Command
{
    use DuplicateReasonHelpers;

    /**
     * Get the entity type name for display purposes.
     */
    abstract protected function getEntityType(): string;

    /**
     * Get the entity model class.
     */
    abstract protected function getEntityModel(): string;

    /**
     * Get the repository class for finding duplicates.
     */
    abstract protected function getRepositoryClass(): string;

    /**
     * Get the repository instance.
     */
    abstract protected function getRepository();

    /**
     * Get the entity name for display.
     */
    abstract protected function getEntityName($entity): string;

    /**
     * Get the entity stage/status for display.
     */
    abstract protected function getEntityStage($entity): string;

    /**
     * Get the entity organization for display.
     */
    abstract protected function getEntityOrganization($entity): string;

    /**
     * Convert entity to array format for reason computation.
     */
    abstract protected function entityToArray($entity): array;

    /**
     * Find potential duplicates for an entity.
     */
    abstract protected function findPotentialDuplicates($entity): Collection;

    /**
     * Analyze a single entity for duplicates.
     */
    protected function analyzeSingleEntity(int $entityId, bool $csvOutput = false, int $limit = 50, bool $noFilter = false): int
    {
        $modelClass = $this->getEntityModel();
        $entity = $modelClass::find($entityId);

        if (! $entity) {
            $this->error("{$this->getEntityType()} #{$entityId} not found");

            return 1;
        }

        // Extract entity signals
        [$entityEmails, $entityPhones] = [$this->extractValues($entity->emails), $this->extractValues($entity->phones)];

        $this->info("Analyzing duplicate signals for {$this->getEntityType()}:");
        $this->table([
            'ID', 'Name', 'Stage', 'Created At',
        ], [[
            $entity->id,
            $this->getEntityName($entity),
            $this->getEntityStage($entity),
            optional($entity->created_at)?->toDateTimeString() ?? '-',
        ]]);

        $this->line('Signals:');
        $this->line('- Emails: '.(empty($entityEmails) ? '-' : implode(', ', $entityEmails)));
        $this->line('- Phones: '.(empty($entityPhones) ? '-' : implode(', ', $entityPhones)));
        $this->line('- Names: '.trim(implode(' ', array_filter([$entity->first_name, $entity->last_name]))).($entity->married_name ? (' | married: '.$entity->married_name) : ''));

        // Get duplicates
        $duplicates = $this->findPotentialDuplicates($entity);

        // Optionally remove filters by recomputing naive matches
        if ($noFilter) {
            $duplicates = $this->computeNaiveDuplicates($entity, $entityEmails, $entityPhones);
        }

        $total = $duplicates->count();
        $this->info("\nFound {$total} potential duplicates".($noFilter ? ' (no filters)' : ''));

        if ($csvOutput) {
            $this->outputCsv(collect([$entity]), $duplicates);
        } else {
            $this->outputDetailedTable($entity, $duplicates, $entityEmails, $entityPhones, $limit, $total);
        }

        return 0;
    }

    /**
     * Analyze all entities for duplicates.
     */
    protected function analyzeAllEntities(bool $csvOutput = false, int $limit = 50, bool $noFilter = false): int
    {
        $this->info("Analyzing all {$this->getEntityType()}s for duplicates...");

        $modelClass = $this->getEntityModel();
        $entitiesWithDuplicates = collect();
        $allDuplicates = collect();
        $summaryRows = [];
        $totalDuplicateCount = 0;

        $entities = $modelClass::all();
        $processedCount = 0;

        foreach ($entities as $entity) {
            $duplicates = $this->findPotentialDuplicates($entity);

            if ($duplicates->isNotEmpty()) {
                $entitiesWithDuplicates->push($entity);
                $allDuplicates = $allDuplicates->merge($duplicates);

                $dupCount = $duplicates->count();
                $totalDuplicateCount += $dupCount;
                $summaryRows[] = [
                    $entity->id,
                    $this->getEntityName($entity),
                    $dupCount,
                ];
            }

            $processedCount++;
            if ($processedCount % 100 === 0) {
                $this->info("Processed {$processedCount} {$this->getEntityType()}s...");
            }
        }

        if ($entitiesWithDuplicates->isEmpty()) {
            $this->info("No duplicate {$this->getEntityType()}s found.");

            return 0;
        }

        $this->info("Found {$entitiesWithDuplicates->count()} {$this->getEntityType()}s with potential duplicates.");

        if ($csvOutput) {
            $this->outputCsv($entitiesWithDuplicates, $allDuplicates);
        } else {
            $this->outputSummaryTable($entitiesWithDuplicates, $allDuplicates, $summaryRows, $totalDuplicateCount, $limit);
        }

        return 0;
    }

    /**
     * Output detailed table for single entity analysis.
     */
    protected function outputDetailedTable(Model $entity, Collection $duplicates, array $entityEmails, array $entityPhones, int $limit, int $total): void
    {
        // Build reason breakdown
        $rows = [];
        $counts = [
            'email' => 0,
            'phone' => 0,
            'name'  => 0,
        ];

        $unknowns = [];
        $entityArr = $this->entityToArray($entity);

        foreach ($duplicates as $dup) {
            $dupArr = $this->entityToArray($dup);
            $reasons = $this->computeReasons($entityArr, $dupArr, $entityEmails, $entityPhones);

            foreach ($reasons as $type => $values) {
                if (! empty($values)) {
                    $counts[$type]++;
                }
            }

            $rows[] = [
                $dup->id,
                $this->getEntityName($dup),
                $this->getEntityStage($dup),
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
                    $this->getEntityName($u),
                    $this->stringify($u->emails),
                    $this->stringify($u->phones),
                ];
            }
            $this->table(['ID', 'Name', 'Raw Emails', 'Raw Phones'], $diagRows);
        }
    }

    /**
     * Output summary table for all entities analysis.
     */
    protected function outputSummaryTable(Collection $entities, Collection $allDuplicates, array $summaryRows, int $totalDuplicateCount, int $limit = 50): void
    {
        $tableData = [];

        foreach ($entities as $entity) {
            [$entityEmails, $entityPhones] = [$this->extractValues($entity->emails), $this->extractValues($entity->phones)];

            $duplicates = $this->findPotentialDuplicates($entity);
            $entityArr = $this->entityToArray($entity);

            foreach ($duplicates as $dup) {
                $dupArr = $this->entityToArray($dup);
                $reasons = $this->computeReasons($entityArr, $dupArr, $entityEmails, $entityPhones);

                $tableData[] = [
                    $entity->id,
                    $this->getEntityName($entity),
                    $dup->id,
                    $this->getEntityName($dup),
                    implode(', ', $reasons['email'] ?? []),
                    implode(', ', $reasons['phone'] ?? []),
                    $reasons['name_reason'] ?? '-',
                ];
            }
        }

        $this->table([
            'Primary ID',
            'Primary Name',
            'Duplicate ID',
            'Duplicate Name',
            'Email Matches',
            'Phone Matches',
            'Name Reason',
        ], array_slice($tableData, 0, $limit));

        // Summary block: per entity duplicate count and total duplicates found
        $this->line('');
        $this->info('Summary: Duplicates per '.$this->getEntityType());
        $this->table([
            $this->getEntityType().' ID',
            'Name',
            'Duplicates Found',
        ], $summaryRows);

        $this->line('');
        $this->info('Total duplicates found: '.$totalDuplicateCount);
    }

    /**
     * Output results as CSV.
     */
    protected function outputCsv(Collection $entities, Collection $allDuplicates): void
    {
        $this->line('primary_id,primary_name,primary_organization,duplicate_id,duplicate_name,duplicate_organization,email_matches,phone_matches,name_reason');

        foreach ($entities as $entity) {
            [$entityEmails, $entityPhones] = [$this->extractValues($entity->emails), $this->extractValues($entity->phones)];

            $duplicates = $this->findPotentialDuplicates($entity);
            $entityArr = $this->entityToArray($entity);

            foreach ($duplicates as $dup) {
                $dupArr = $this->entityToArray($dup);
                $reasons = $this->computeReasons($entityArr, $dupArr, $entityEmails, $entityPhones);

                $this->line(sprintf(
                    '%d,"%s","%s",%d,"%s","%s","%s","%s","%s"',
                    $entity->id,
                    str_replace('"', '""', $this->getEntityName($entity)),
                    str_replace('"', '""', $this->getEntityOrganization($entity)),
                    $dup->id,
                    str_replace('"', '""', $this->getEntityName($dup)),
                    str_replace('"', '""', $this->getEntityOrganization($dup)),
                    implode(', ', $reasons['email'] ?? []),
                    implode(', ', $reasons['phone'] ?? []),
                    $reasons['name_reason'] ?? ''
                ));
            }
        }
    }

    /**
     * Compute naive duplicates without filters.
     */
    protected function computeNaiveDuplicates(Model $entity, array $entityEmails, array $entityPhones): Collection
    {
        $modelClass = $this->getEntityModel();
        $candidates = $modelClass::where('id', '!=', $entity->id)->get();

        $results = collect();
        $entityArr = $this->entityToArray($entity);

        foreach ($candidates as $dup) {
            $dupArr = $this->entityToArray($dup);
            $reasons = $this->computeReasons($entityArr, $dupArr, $entityEmails, $entityPhones);

            if (! empty($reasons['email']) || ! empty($reasons['phone']) || $reasons['name_reason']) {
                $results->push($dup);
            }
        }

        return $results;
    }

    /**
     * Convert value to string for display.
     */
    protected function stringify($value): string
    {
        return \App\Helpers\ValueNormalizer::toString($value);
    }
}
