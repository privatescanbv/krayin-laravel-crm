<?php

namespace App\Actions\Leads;

use App\Services\CustomerHistory\CustomerHistoryService;
use Illuminate\Support\Facades\DB;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;

/**
 * Records on a lost lead whether anyone ever actually reached the person; cleared
 * again once the lead is no longer lost.
 */
class RecordLeadContactAction
{
    public function __construct(
        private readonly CustomerHistoryService $customerHistory,
    ) {}

    public function execute(Lead $lead): void
    {
        $isLost = (bool) Stage::query()->whereKey($lead->lead_pipeline_stage_id)->value('is_lost');

        // Query builder on purpose: no observers, no AI refresh, no updated_at bump.
        DB::table('leads')->where('id', $lead->id)->update([
            'had_contact' => $isLost ? $this->customerHistory->hadContact($lead) : null,
        ]);
    }
}
