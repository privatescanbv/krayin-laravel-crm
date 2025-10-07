<?php

namespace Webkul\Activity\Models;

use App\Models\CallStatus;
use Illuminate\Database\Eloquent\Model;
use Webkul\Activity\Contracts\Activity as ActivityContract;
use App\Enums\ActivityType;
use App\Enums\ActivityStatus;
use Webkul\Contact\Models\PersonProxy;
use Webkul\Email\Models\EmailProxy;
use Webkul\Lead\Models\LeadProxy;
use Webkul\Product\Models\ProductProxy;
use Webkul\User\Models\GroupProxy;
use Webkul\User\Models\UserProxy;
use Webkul\Warehouse\Models\WarehouseProxy;

class Activity extends Model implements ActivityContract
{
    /**
     * Define table name of property
     *
     * @var string
     */
    protected $table = 'activities';

    /**
     * Define relationships that should be touched on save
     *
     * @var array
     */
    protected $with = ['user'];

    /**
     * Cast attributes to date time
     *
     * @var array
     */
    protected $casts = [
        'schedule_from' => 'datetime',
        'schedule_to'   => 'datetime',
        'assigned_at'   => 'datetime',
        'additional'    => 'array',
        'is_done'      => 'boolean',
        'type'         => ActivityType::class,
        'status'       => ActivityStatus::class,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'type',
        'location',
        'comment',
        'additional',
        'schedule_from',
        'schedule_to',
        'is_done',
        'status',
        'user_id',
        'assigned_at',
        'group_id',
        'lead_id',
        'workflow_lead_id',
        'clinic_id',
        'external_id',
    ];

    /**
     * Get the user that owns the activity.
     */
    public function user()
    {
        return $this->belongsTo(UserProxy::modelClass());
    }



    /**
     * Get the file associated with the activity.
     */
    public function files()
    {
        return $this->hasMany(FileProxy::modelClass(), 'activity_id');
    }

    /**
     * Get the lead that owns the activity.
     */
    public function lead()
    {
        return $this->belongsTo(LeadProxy::modelClass());
    }

    /**
     * Get the workflow lead (sales lead) that owns the activity.
     */
    public function workflowLead()
    {
        return $this->belongsTo(\App\Models\SalesLead::class, 'workflow_lead_id');
    }

    /**
     * Get the clinic that owns the activity.
     */
    public function clinic()
    {
        return $this->belongsTo(\App\Models\Clinic::class);
    }

    /**
     * Get the emails associated with the activity.
     */
    public function emails()
    {
        return $this->hasMany(EmailProxy::modelClass(), 'activity_id');
    }

    /**
     * The Person that belong to the activity.
     */
    public function persons()
    {
        return $this->belongsToMany(PersonProxy::modelClass(), 'person_activities');
    }

    /**
     * The leads that belong to the activity.
     */
    public function products()
    {
        return $this->belongsToMany(ProductProxy::modelClass(), 'product_activities');
    }

    /**
     * The Warehouse that belong to the activity.
     */
    public function warehouses()
    {
        return $this->belongsToMany(WarehouseProxy::modelClass(), 'warehouse_activities');
    }

    /**
     * Call statuses linked to this activity.
     */
    public function callStatuses()
    {
        return $this->hasMany(CallStatus::class, 'activity_id');
    }

    /**
     * Get the group that is assigned to this activity.
     */
    public function group()
    {
        return $this->belongsTo(GroupProxy::modelClass(), 'group_id');
    }

    public function getSugarLinkAttribute() :?string
    {
        // no support
//        if ($this->external_id) {
//            $baseUrl = config('services.sugarcrm.base_url');
//            $record = $this->external_id;
//            $activityType = $this->mapSugarActivityType();
//            return "{$baseUrl}action=DetailView&module={$activityType}&record={$record}";
//            //&offset=3&stamp=1758266889055967400{$record}
//        }
        return null;
    }

    private function mapSugarActivityType(): ?string
    {
        return match ($this->type) {
            ActivityType::CALL => 'Calls',
            ActivityType::MEETING => 'Meetings',
            ActivityType::TASK => 'Tasks',
            default => null,
        };
    }
}
