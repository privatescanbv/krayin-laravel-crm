<?php

namespace App\Enums;

enum PipelineDefaultKeys: int
{
    case PIPELINE_PRIVATESCAN_ID = 1;
    case PIPELINE_HERNIA_ID = 2;
    case PIPELINE_PRIVATESCAN_WORKFLOW_ID = 3;
    case PIPELINE_HERNIA_WORKFLOW_ID = 4;
    case PIPELINE_TECHNICAL_ID = 5;
    case PIPELINE_TECHNICAL_STAGE_ID = 22;

}
