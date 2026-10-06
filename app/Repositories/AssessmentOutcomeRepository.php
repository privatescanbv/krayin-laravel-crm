<?php

namespace App\Repositories;

use App\Models\AssessmentOutcome;
use Webkul\Core\Eloquent\Repository;

class AssessmentOutcomeRepository extends Repository
{
    /**
     * Searchable fields.
     */
    protected $fieldSearchable = [
        'code',
        'label',
    ];

    public function model(): string
    {
        return AssessmentOutcome::class;
    }
}
