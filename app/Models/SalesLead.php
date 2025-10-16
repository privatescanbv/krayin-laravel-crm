<?php

namespace App\Models;

use App\Enums\WorkflowType;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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
     * Count unread emails for this sales lead.
     */
    public function getUnreadEmailsCountAttribute(): int
    {
        return (int) $this->emails()->where('is_read', 0)->count();
    }

    /**
     * Check if this sales lead has duplicates.
     */
    public function getHasDuplicatesAttribute(): bool
    {
        return false; // Placeholder - implement duplicate detection logic if needed
    }

    /**
     * Get the count of duplicates for this sales lead.
     */
    public function getDuplicatesCountAttribute(): int
    {
        return 0; // Placeholder - implement duplicate counting logic if needed
    }

    /**
     * Get the number of rotten days for this sales lead.
     */
    public function getRottenDaysAttribute(): int
    {
        return 0; // Placeholder - implement rotten days logic if needed
    }

    /**
     * Get the days until due date for this sales lead.
     */
    public function getDaysUntilDueDateAttribute(): ?int
    {
        return null; // Placeholder - implement due date logic if needed
    }

    /**
     * Get the MRI status for this sales lead.
     */
    public function getMriStatusAttribute(): ?string
    {
        return null; // Placeholder - implement MRI status logic if needed
    }

    /**
     * Get the MRI status label for this sales lead.
     */
    public function getMriStatusLabelAttribute(): ?string
    {
        return null; // Placeholder - implement MRI status label logic if needed
    }

    /**
     * Check if this sales lead has a diagnosis form.
     */
    public function getHasDiagnosisFormAttribute(): bool
    {
        return false; // Placeholder - implement diagnosis form logic if needed
    }

    /**
     * Get the lost reason label for this sales lead.
     */
    public function getLostReasonLabelAttribute(): ?string
    {
        if (! $this->lost_reason) {
            return null;
        }

        try {
            $lostReason = \App\Enums\LostReason::from($this->lost_reason);

            return $lostReason->label();
        } catch (\ValueError $e) {
            return $this->lost_reason;
        }
    }

    /**
     * Get the persons associated with the sales lead.
     * This accessor ensures we always get the correct data.
     */
    public function getPersonsAttribute()
    {
        try {
            return Person::whereIn('id',
                DB::table('saleslead_persons')->where('saleslead_id', $this->id)->pluck('person_id')
            )->get();
        } catch (Exception $e) {
            Log::warning('Could not load persons for sales lead', ['saleslead_id' => $this->id, 'error' => $e->getMessage()]);

            return collect();
        }

        // Load the relationship if not already loaded
        $this->load('persons');

        return $this->getRelation('persons');
    }

    /**
     * Persons related to this sales lead (many-to-many via saleslead_persons).
     */
    public function persons()
    {
        return $this->belongsToMany(Person::class, 'saleslead_persons', 'saleslead_id', 'person_id');
    }

    /**
     * Legacy alias to support filters like person.name used by RequestCriteria.
     * Returns the same relation as persons().
     */
    public function person()
    {
        return $this->persons();
    }

    /**
     * Attach persons to this sales lead.
     */
    public function attachPersons(array $personIds)
    {
        foreach ($personIds as $personId) {
            DB::table('saleslead_persons')->insertOrIgnore([
                'saleslead_id' => $this->id,
                'person_id'    => $personId,
            ]);
        }
    }

    /**
     * Sync persons to this sales lead (replace all existing).
     */
    public function syncPersons(array $personIds)
    {
        // Remove existing relationships
        DB::table('saleslead_persons')->where('saleslead_id', $this->id)->delete();

        // Add new relationships
        if (! empty($personIds)) {
            $this->attachPersons($personIds);
        }
    }

    /**
     * Get the count of persons associated with this sales lead.
     */
    public function getPersonsCountAttribute(): int
    {
        return (int) $this->persons()->count();
    }

    /**
     * Check if this sales lead has multiple persons.
     */
    public function hasMultiplePersons(): bool
    {
        return $this->persons()->count() > 1;
    }

    /**
     * Get the orders associated with this sales lead.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
