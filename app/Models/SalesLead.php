<?php

namespace App\Models;

use App\Enums\WorkflowType;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;

// Quote entity removed

class SalesLead extends Model
{
    use HasAuditTrail, HasFactory;

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
        'user_id',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'workflow_type' => WorkflowType::class,
        'created_by'    => 'integer',
        'updated_by'    => 'integer',
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

    // Quote relation removed

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

    /**
     * Get the emails associated with the sales lead.
     */
    public function emails()
    {
        return $this->hasMany(\Webkul\Email\Models\Email::class, 'sales_lead_id');
    }
}
