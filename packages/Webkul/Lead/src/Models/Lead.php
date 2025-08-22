<?php

namespace Webkul\Lead\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Models\ActivityProxy;
use Webkul\Activity\Traits\LogsActivity;
use Webkul\Attribute\Traits\CustomAttribute;
use Webkul\Contact\Models\PersonProxy;
use Webkul\Email\Models\EmailProxy;
use Webkul\Lead\Contracts\Lead as LeadContract;
use Webkul\Quote\Models\QuoteProxy;
use Webkul\Tag\Models\TagProxy;
use Webkul\User\Models\UserProxy;
use Database\Factories\LeadFactory;
use App\Models\Address;

class Lead extends Model implements LeadContract
{
    use CustomAttribute, LogsActivity, HasFactory;

    protected $casts = [
        'closed_at'           => 'datetime',
        'expected_close_date' => 'date',
    ];

    /**
     * The attributes that are appended.
     *
     * @var array
     */
    protected $appends = [
        'rotten_days',
        'name',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'external_id',
        'description',
        'lead_value',
        'status',
        'lost_reason',
        'expected_close_date',
        'closed_at',
        'user_id',
        'lead_source_id',
        'lead_type_id',
        'lead_pipeline_id',
        'lead_pipeline_stage_id',
        'lead_channel_id',
        'department_id',
        'created_by',
        'updated_by',
    ];

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return LeadFactory::new();
    }

    /**
     * Get the user that owns the lead.
     */
    public function user()
    {
        return $this->belongsTo(UserProxy::modelClass());
    }

    /**
     * Get the user who created the lead.
     */
    public function createdBy()
    {
        return $this->belongsTo(UserProxy::modelClass(), 'created_by');
    }

    /**
     * Get the user who last updated the lead.
     */
    public function updatedBy()
    {
        return $this->belongsTo(UserProxy::modelClass(), 'updated_by');
    }

    /**
     * Get the persons associated with the lead.
     */
    public function persons()
    {
        return $this->belongsToMany(\Webkul\Contact\Models\Person::class, 'lead_persons', 'lead_id', 'person_id')
                    ->withTimestamps();
    }

    /**
     * Get the type that owns the lead.
     */
    public function type()
    {
        return $this->belongsTo(TypeProxy::modelClass(), 'lead_type_id');
    }

    /**
     * Get the source that owns the lead.
     */
    public function source()
    {
        return $this->belongsTo(SourceProxy::modelClass(), 'lead_source_id');
    }

    /**
     * Get the pipeline that owns the lead.
     */
    public function pipeline()
    {
        return $this->belongsTo(PipelineProxy::modelClass(), 'lead_pipeline_id');
    }

    /**
     * Get the pipeline stage that owns the lead.
     */
    public function stage()
    {
        return $this->belongsTo(StageProxy::modelClass(), 'lead_pipeline_stage_id');
    }

    /**
     * Get the activities.
     */
    public function activities()
    {
        return $this->belongsToMany(ActivityProxy::modelClass(), 'lead_activities');
    }

    /**
     * Get the products.
     */
    public function products()
    {
        return $this->hasMany(ProductProxy::modelClass());
    }

    /**
     * Get the emails.
     */
    public function emails()
    {
        return $this->hasMany(EmailProxy::modelClass());
    }

    /**
     * The quotes that belong to the lead.
     */
    public function quotes()
    {
        return $this->belongsToMany(QuoteProxy::modelClass(), 'lead_quotes');
    }

    /**
     * The tags that belong to the lead.
     */
    public function tags()
    {
        return $this->belongsToMany(TagProxy::modelClass(), 'lead_tags');
    }

    /**
     * Get the address that belongs to the lead.
     */
    public function address()
    {
        return $this->hasOne(Address::class);
    }

    /**
     * Get the channel that owns the lead.
     */
    public function channel()
    {
        return $this->belongsTo(\Webkul\Lead\Models\Channel::class, 'lead_channel_id');
    }

    /**
     * Get the department that owns the lead.
     */
    public function department()
    {
        return $this->belongsTo(\App\Models\Department::class, 'department_id');
    }

    /**
     * Returns the rotten days
     */
    public function getRottenDaysAttribute()
    {
        if (! $this->stage) {
            return 0;
        }

        if (in_array($this->stage->code, ['won', 'lost'])) {
            return 0;
        }

        if (! $this->created_at) {
            return 0;
        }

        $rottenDate = $this->created_at->addDays($this->pipeline->rotten_days);

        return $rottenDate->diffInDays(Carbon::now(), false);
    }

    /**
     * Find the default email address from associated persons
     */
    public function findDefaultEmail(): ?string
    {
        foreach ($this->persons as $person) {
            $email = $person->findDefaultEmail();
            if ($email) {
                return $email;
            }
        }
        
        return null;
    }

    /**
     * Find the default phone number from associated persons
     */
    public function findDefaultPhone(): ?string
    {
        foreach ($this->persons as $person) {
            $phone = $person->findDefaultPhone();
            if ($phone) {
                return $phone;
            }
        }
        
        return null;
    }

    public function getOpenActivitiesCountAttribute():int
    {
        return $this->activities()->where('is_done', 0)->count();
    }

    /**
     * Get the count of unread emails for this lead.
     */
    public function getUnreadEmailsCountAttribute(): int
    {
        return $this->emails()->where('is_read', 0)->count();
    }

    /**
     * Get the remaining days until due date (expected_close_date).
     */
    public function getDaysUntilDueDateAttribute(): ?int
    {
        if (!$this->expected_close_date) {
            return null;
        }

        $today = Carbon::today();
        $dueDate = Carbon::parse($this->expected_close_date);

        return $today->diffInDays($dueDate, false);
    }

    /**
     * Get the anamnesis that belongs to the lead.
     */
    public function anamnesis()
    {
        return $this->hasOne(\App\Models\Anamnesis::class, 'lead_id');
    }

    /**
     * Check if this lead has potential duplicates.
     */
    public function hasPotentialDuplicates(): bool
    {
        try {
            return app('Webkul\Lead\Repositories\LeadRepository')->hasPotentialDuplicates($this);
        } catch (\Exception $e) {
            Log::error('Error checking for potential duplicates: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get potential duplicate leads.
     */
    public function getPotentialDuplicates()
    {
        try {
            return app('Webkul\Lead\Repositories\LeadRepository')->findPotentialDuplicates($this);
        } catch (\Exception $e) {
            Log::error('Error getting potential duplicates: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Get the count of potential duplicates.
     */
    public function getPotentialDuplicatesCount(): int
    {
        try {
            return $this->getPotentialDuplicates()->count();
        } catch (\Exception $e) {
            Log::error('Error counting potential duplicates: ' . $e->getMessage());
            return 0;
        }
    }

    public function getNameAttribute($value): string
    {
        // If lead has persons, use the first person's name
        if ($this->persons && $this->persons->count() > 0) {
            return $this->persons->first()->name;
        }
        
        // Otherwise use the lead title
        return $this->title;
    }
}
