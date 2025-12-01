<?php

namespace App\Services;

use App\Models\SalesLead;
use App\Repositories\SalesLeadRepository;
use Webkul\Lead\Models\Lead as LeadModel;
use Webkul\Lead\Repositories\LeadRepository;

class LeadAndSalesService
{
    public function __construct(
        private LeadRepository $leadRepository,
        private SalesLeadRepository $salesRepository
    ) {}

    /**
     * Finds open sales and lead for a given person.
     *
     * Returns an array with keys:
     * - 'lead'  => LeadModel|null
     * - 'sales' => SalesLead|null
     */
    public function findOpenByPerson(int $personId): array
    {
        // 1. Find open sales (pipeline stage not won/lost) linked to this person
        $sales = SalesLead::whereHas('pipelineStage', function ($query) {
            $query->where('is_won', false)
                ->where('is_lost', false);
        })
            ->where(function ($query) use ($personId) {
                $query->where('contact_person_id', $personId)
                    ->orWhereHas('persons', function ($q) use ($personId) {
                        $q->where('person_id', $personId);
                    });
            })
            ->with('lead')
            ->first();

        if ($sales) {
            return [
                'lead'  => $sales->lead,
                'sales' => $sales,
            ];
        }

        // 2. If no open sales, find open lead (stage not won/lost) linked to this person
        $lead = LeadModel::whereHas('stage', function ($query) {
            $query->where('is_won', false)
                ->where('is_lost', false);
        })
            ->where(function ($query) use ($personId) {
                $query->where('contact_person_id', $personId)
                    ->orWhereHas('persons', function ($q) use ($personId) {
                        $q->where('person_id', $personId);
                    });
            })
            ->first();

        // 3. Return array; both values can be null
        return [
            'lead'  => $lead,
            'sales' => null,
        ];
    }
}
