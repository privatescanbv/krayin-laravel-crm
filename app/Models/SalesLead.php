<?php

namespace App\Models;

use App\Enums\WorkflowType;
use Illuminate\Database\Eloquent\Model;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;
use Webkul\Quote\Models\Quote;

class SalesLead extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'salesleads';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'pipeline_stage_id',
        'lead_id',
        'quote_id',
        'user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'workflow_type' => WorkflowType::class,
    ];

    /**
     * Get the pipeline stage associated with the workflow.
     */
    public function pipelineStage()
    {
        return $this->belongsTo(Stage::class, 'pipeline_stage_id');
    }

    /**
     * Get the lead associated with the workflow.
     */
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the order associated with the workflow.
     */
    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * Get the user associated with the workflow.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the activities associated with the workflow lead.
     */
    public function activities()
    {
        return $this->hasMany(\Webkul\Activity\Models\Activity::class, 'workflow_lead_id');
    }
}
