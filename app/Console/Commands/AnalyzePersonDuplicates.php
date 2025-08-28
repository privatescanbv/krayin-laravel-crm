<?php

namespace App\Console\Commands;

use App\Services\DuplicateReasonHelpers;
use Illuminate\Console\Command;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;

class AnalyzePersonDuplicates extends Command
{
    use DuplicateReasonHelpers;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'person:analyze-duplicates {person_id?} {--all} {--csv}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze and report potential duplicate persons';

    /**
     * Create a new command instance.
     */
    public function __construct(
        protected PersonRepository $personRepository
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $personId = $this->argument('person_id');
        $analyzeAll = $this->option('all');
        $csvOutput = $this->option('csv');

        if ($personId) {
            return $this->analyzeSinglePerson($personId, $csvOutput);
        }

        if ($analyzeAll) {
            return $this->analyzeAllPersons($csvOutput);
        }

        $this->error('Please specify either a person ID or use --all flag');
        return 1;
    }

    /**
     * Analyze a single person for duplicates.
     */
    private function analyzeSinglePerson(int $personId, bool $csvOutput = false): int
    {
        try {
            $person = $this->personRepository->findOrFail($personId);
        } catch (\Exception $e) {
            $this->error("Person with ID {$personId} not found.");
            return 1;
        }

        $duplicates = $this->personRepository->findPotentialDuplicates($person);

        if ($duplicates->isEmpty()) {
            $this->info("No duplicates found for person {$person->id} ({$person->name})");
            return 0;
        }

        if ($csvOutput) {
            $this->outputCsv(collect([$person]), $duplicates);
        } else {
            $this->outputTable(collect([$person]), $duplicates);
        }

        return 0;
    }

    /**
     * Analyze all persons for duplicates.
     */
    private function analyzeAllPersons(bool $csvOutput = false): int
    {
        $this->info('Analyzing all persons for duplicates...');

        $personsWithDuplicates = collect();
        $allDuplicates = collect();

        $persons = Person::with(['organization'])->get();
        $processedCount = 0;

        foreach ($persons as $person) {
            $duplicates = $this->personRepository->findPotentialDuplicates($person);

            if ($duplicates->isNotEmpty()) {
                $personsWithDuplicates->push($person);
                $allDuplicates = $allDuplicates->merge($duplicates);
            }

            $processedCount++;
            if ($processedCount % 100 === 0) {
                $this->info("Processed {$processedCount} persons...");
            }
        }

        if ($personsWithDuplicates->isEmpty()) {
            $this->info('No duplicate persons found.');
            return 0;
        }

        $this->info("Found {$personsWithDuplicates->count()} persons with potential duplicates.");

        if ($csvOutput) {
            $this->outputCsv($personsWithDuplicates, $allDuplicates);
        } else {
            $this->outputTable($personsWithDuplicates, $allDuplicates);
        }

        return 0;
    }

    /**
     * Output results as a table.
     */
    private function outputTable($persons, $allDuplicates): void
    {
        $tableData = [];

        foreach ($persons as $person) {
            [$personEmails, $personPhones] = [$this->extractValues($person->emails), $this->extractValues($person->phones)];

            $duplicates = $this->personRepository->findPotentialDuplicates($person);

            foreach ($duplicates as $dup) {
                $reasons = $this->computeReasons($person, $dup, $personEmails, $personPhones);

                $tableData[] = [
                    $person->id,
                    $person->name,
                    $dup->id,
                    $dup->name,
                    implode(', ', $reasons['email']),
                    implode(', ', $reasons['phone']),
                    $reasons['name_reason'] ?: '-',
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
        ], $tableData);
    }

    /**
     * Output results as CSV.
     */
    private function outputCsv($persons, $allDuplicates): void
    {
        $this->line('primary_id,primary_name,primary_organization,duplicate_id,duplicate_name,duplicate_organization,email_matches,phone_matches,name_reason');

        foreach ($persons as $person) {
            [$personEmails, $personPhones] = [$this->extractValues($person->emails), $this->extractValues($person->phones)];

            $duplicates = $this->personRepository->findPotentialDuplicates($person);

            foreach ($duplicates as $dup) {
                $reasons = $this->computeReasons($person, $dup, $personEmails, $personPhones);

                $this->line(sprintf(
                    '%d,"%s","%s",%d,"%s","%s","%s","%s","%s"',
                    $person->id,
                    str_replace('"', '""', $person->name),
                    str_replace('"', '""', $person->organization->name ?? ''),
                    $dup->id,
                    str_replace('"', '""', $dup->name),
                    str_replace('"', '""', $dup->organization->name ?? ''),
                    implode(', ', $reasons['email']),
                    implode(', ', $reasons['phone']),
                    $reasons['name_reason'] ?: ''
                ));
            }
        }
    }
}