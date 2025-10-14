<?php

namespace App\Models;

use App\Enums\WorkflowType;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Email\Models\Email;
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
        'lost_reason',
        'closed_at',
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
        'closed_at'     => 'date',
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
        return $this->hasMany(Activity::class, 'sales_lead_id');
    }

    /**
     * Get the emails associated with the sales lead.
     */
    public function emails()
    {
        return $this->hasMany(Email::class, 'sales_lead_id');
    }

    /**
     * Count open activities for this sales lead.
     */
    public function getOpenActivitiesCountAttribute(): int
    {
        return (int) $this->activities()->where('is_done', 0)->count();
    }

    /**
     * Persons related to this sales lead via underlying lead (many-to-many via lead_persons).
     * Uses sales lead's lead_id as the parent key on the pivot's lead_id.
     */
    public function persons()
    {
        // TODO replace with sales lead person.
        return $this->belongsToMany(Person::class, 'lead_persons', 'lead_id', 'person_id', 'lead_id', 'id')
            ->withPivot(['lead_id', 'person_id']);
    }

    /**
     * Legacy alias, mirrors persons().
     */
    public function person()
    {
        return $this->persons();
    }
}
