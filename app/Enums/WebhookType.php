<?php

namespace App\Enums;

enum WebhookType: string
{
    case LEAD_PIPELINE_STAGE_CHANGE = 'lead_pipeline_change';
    case LEAD_ACTIVITY_IS_DONE = 'lead_activity_is_done';
    case SALES_LEAD_PIPELINE_STAGE_CHANGE = 'sales_lead_pipeline_change';

    public function label(): string
    {
        return match ($this) {
            self::LEAD_PIPELINE_STAGE_CHANGE       => 'lead pipeline stage change',
            self::LEAD_ACTIVITY_IS_DONE            => 'Activity is done',
            self::SALES_LEAD_PIPELINE_STAGE_CHANGE => 'sales lead pipeline stage change',
        };
    }
}
