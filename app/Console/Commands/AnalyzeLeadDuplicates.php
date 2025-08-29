<?php

namespace App\Console\Commands;

use Illuminate\Support\Collection;
use Webkul\Lead\Models\Lead as LeadModel;
use Webkul\Lead\Repositories\LeadRepository;

class AnalyzeLeadDuplicates extends AbstractAnalyzeDuplicates
{
    /**
     * The name and signature of the console command.
     *
     * Usage: sail artisan leads:analyze-duplicates {leadId} [--limit=50] [--no-filter]
     */
    protected $signature = 'leads:analyze-duplicates
                            {leadId? : Lead ID to analyze}
                            {--all : Analyze all leads for duplicates}
                            {--limit=50 : Limit number of duplicates to print}
                            {--no-filter : Show all potential matches without time/status filtering}';

    /**
     * The console command description.
     */
    protected $description = 'Analyze duplicate detection for leads with detailed breakdown';

    public function __construct(
        protected LeadRepository $leadRepository
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $leadId = $this->argument('leadId');
        $analyzeAll = $this->option('all');
        $limit = (int) $this->option('limit');
        $noFilter = (bool) $this->option('no-filter');

        if ($analyzeAll) {
            return $this->analyzeAllEntities(false, $limit, $noFilter);
        }

        if ($leadId) {
            return $this->analyzeSingleEntity((int) $leadId, false, $limit, $noFilter);
        }

        $this->error('Please specify either a lead ID or use --all flag');

        return 1;
    }

    protected function getEntityType(): string
    {
        return 'lead';
    }

    protected function getEntityModel(): string
    {
        return LeadModel::class;
    }

    protected function getRepositoryClass(): string
    {
        return LeadRepository::class;
    }

    protected function getRepository()
    {
        return $this->leadRepository;
    }

    protected function getEntityName($entity): string
    {
        return $entity->name;
    }

    protected function getEntityStage($entity): string
    {
        return optional($entity->stage)->name ?? '-';
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
        return $this->leadRepository->findPotentialDuplicatesDirectly($entity);
    }
}
