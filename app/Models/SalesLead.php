<?php

namespace App\Models;

use App\Enums\LostReason;
use App\Enums\WorkflowType;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use ValueError;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Email\Models\Email;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;
use Webkul\Lead\Models\StageProxy;

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
        'contact_person_id',
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
     * Get the pipeline stage that owns the lead.
     */
    public function stage()
    {
        return $this->belongsTo(StageProxy::modelClass(), 'pipeline_stage_id');
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
     * Get the contact person for this sales lead.
     */
    public function contactPerson()
    {
        return $this->belongsTo(Person::class, 'contact_person_id');
    }

    public function hasContactPerson(): bool
    {
        return $this->contact_person_id !== null && $this->contactPerson()->exists();
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
            $lostReason = LostReason::from($this->lost_reason);

            return $lostReason->label();
        } catch (ValueError $e) {
            return $this->lost_reason;
        }
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

    public function defaultEmailContactPerson(): ?string
    {
        $person = $this->getContactPersonOrFirstPerson();

        return $person?->findDefaultEmail();
    }

    public function getContactPersonOrFirstPerson(): Person
    {
        if ($this->hasContactPerson()) {
            return $this->contactPerson;
        }

        return $this->persons()->first();
    }

    /**
     * Attach persons to this sales lead.
     */
    public function attachPersons(array $personIds): void
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
    public function syncPersons(array $personIds): void
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

    /**
     * Copy persons and contact person from a lead to this sales lead.
     */
    public function copyFromLead(Lead $lead): void
    {
        // Copy persons
        if ($lead->persons()->count() > 0) {
            $personIds = $lead->persons()->pluck('persons.id')->toArray();
            $this->syncPersons($personIds);
        }

        // Copy contact person
        if ($lead->contact_person_id) {
            $this->update(['contact_person_id' => $lead->contact_person_id]);
        }
    }

    public function getDepartment(): Department
    {
        return $this->lead->department;
    }
}
