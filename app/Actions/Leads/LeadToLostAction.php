<?php

namespace App\Actions\Leads;

use App\Http\Controllers\Admin\AnamnesisController;
use Webkul\Lead\Models\Lead;

/**
 * - remove uncompleted GVL forms
 */
class LeadToLostAction
{
    public function __construct(
        private readonly AnamnesisController $anamnesisController
    ) {}

    public function execute(Lead $lead): void
    {
        logger()->info('Running clean up action for lead '.$lead->id);
        $lead->persons()->each(function ($person) use ($lead) {
            $this->anamnesisController->cleanUpForLead($person->id, $lead->id);
        });
    }
}
