<?php

namespace App\Console\Commands;

use Illuminate\Support\Collection;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;

class AnalyzePersonDuplicates extends AbstractAnalyzeDuplicates
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'person:analyze-duplicates {person_id?} {--all} {--csv} {--limit=50}';

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
        $limit = (int) $this->option('limit');

        if ($personId) {
            return $this->analyzeSingleEntity((int) $personId, $csvOutput, $limit);
        }

        if ($analyzeAll) {
            return $this->analyzeAllEntities($csvOutput, $limit, false);
        }

        $this->error('Please specify either a person ID or use --all flag');

        return 1;
    }

    protected function getEntityType(): string
    {
        return 'person';
    }

    protected function getEntityModel(): string
    {
        return Person::class;
    }

    protected function getRepositoryClass(): string
    {
        return PersonRepository::class;
    }

    protected function getRepository()
    {
        return $this->personRepository;
    }

    protected function getEntityName($entity): string
    {
        return $entity->name;
    }

    protected function getEntityStage($entity): string
    {
        return '-'; // Persons don't have stages
    }

    protected function getEntityOrganization($entity): string
    {
        return optional($entity->organization)->name ?? '';
    }

    protected function entityToArray($entity): array
    {
        return [
            'first_name'   => $entity->first_name,
            'last_name'    => $entity->last_name,
            'married_name' => $entity->married_name,
            'emails'       => $entity->emails,
            'phones'       => $entity->phones,
        ];
    }

    protected function findPotentialDuplicates($entity): Collection
    {
        return $this->personRepository->findPotentialDuplicates($entity);
    }
}
