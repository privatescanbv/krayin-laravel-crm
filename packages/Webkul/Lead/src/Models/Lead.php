<?php

namespace Webkul\Lead\Models;

use App\Casts\EncryptedString;
use App\Enums\LostReason;
use App\Enums\MRIStatus;
use App\Enums\PersonGender;
use App\Enums\PersonSalutation;
use App\Models\Anamnesis;
use App\Models\Department;
use App\Traits\HasDefaultContactInfo;
use BackedEnum;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use Webkul\Activity\Models\ActivityProxy;
use Webkul\Activity\Traits\LogsActivity;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Email\Models\EmailProxy;
use Webkul\Lead\Contracts\Lead as LeadContract;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Tag\Models\TagProxy;
use Webkul\User\Models\UserProxy;
use Database\Factories\LeadFactory;
use App\Models\Address;
use App\Services\LeadStatusTransitionValidator;

class Lead extends Model implements LeadContract
{
    use HasDefaultContactInfo, HasFactory, LogsActivity, SoftDeletes;

    protected $casts = [
        'closed_at'           => 'datetime',
        'date_of_birth'       => 'date',
        'emails'              => 'array',
        'phones'              => 'array',
        'gender'              => PersonGender::class,
        'salutation'          => PersonSalutation::class,
        'mri_status'          => MRIStatus::class,
        'lost_reason'         => LostReason::class,
        'national_identification_number' => EncryptedString::class,
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
        'external_id',
        'description',
        'status', // Used for AI import, Krayin
        'lost_reason',
        'closed_at',
        'user_id',
        'lead_source_id',
        'lead_type_id',
        'lead_pipeline_id',
        'lead_pipeline_stage_id',
        'salutation',
        'first_name',
        'last_name',
        'lastname_prefix',
        'married_name',
        'married_name_prefix',
        'initials',
        'date_of_birth',
        'gender',
        'emails',
        'phones',
        'lead_channel_id',
        'department_id',
        'organization_id',
        'contact_person_id',
        'created_by',
        'updated_by',
        'mri_status',
        'diagnosis_form_id',
        'diagnoseform_pdf_url',
        'national_identification_number',
        'address_id',
    ];

    // No special handling for persons_count required anymore

    /**
     * Capitalize first character of first name.
     */
    public function setFirstNameAttribute($value): void
    {
        $this->attributes['first_name'] = $value !== null ? Str::ucfirst($value) : null;
    }

    /**
     * Capitalize first character of last name.
     */
    public function setLastNameAttribute($value): void
    {
        $this->attributes['last_name'] = $value !== null ? Str::ucfirst($value) : null;
    }

    /**
     * Lowercase lastname prefix.
     */
    public function setLastnamePrefixAttribute($value): void
    {
        $this->attributes['lastname_prefix'] = $value !== null ? Str::lower($value) : null;
    }

    /**
     * Capitalize first character of married name.
     */
    public function setMarriedNameAttribute($value): void
    {
        $this->attributes['married_name'] = $value !== null ? Str::ucfirst($value) : null;
    }

    /**
     * Lowercase married name prefix.
     */
    public function setMarriedNamePrefixAttribute($value): void
    {
        $this->attributes['married_name_prefix'] = $value !== null ? Str::lower($value) : null;
    }

    /**
     * Normalize gender assignment to allow empty strings and enums.
     */
    public function setGenderAttribute($value): void
    {
        if ($value === '' || $value === null) {
            $this->attributes['gender'] = null;
            return;
        }

        if ($value instanceof BackedEnum) {
            $this->attributes['gender'] = $value->value;
            return;
        }

        $this->attributes['gender'] = $value;
    }

    /**
     * Normalize salutation assignment to allow empty strings and enums.
     */
    public function setSalutationAttribute($value): void
    {
        if ($value === '' || $value === null) {
            $this->attributes['salutation'] = null;
            return;
        }

        if ($value instanceof BackedEnum) {
            $this->attributes['salutation'] = $value->value;
            return;
        }

        $this->attributes['salutation'] = $value;
    }

    /**
     * Normalize MRI status assignment to allow empty strings and enums.
     */
    public function setMriStatusAttribute($value): void
    {
        if ($value === '' || $value === null) {
            $this->attributes['mri_status'] = null;
            return;
        }

        if ($value instanceof BackedEnum) {
            $this->attributes['mri_status'] = $value->value;
            return;
        }

        $this->attributes['mri_status'] = $value;
    }

    /**
     * Normalize lost reason assignment to allow empty strings and enums.
     */
    public function setLostReasonAttribute($value): void
    {
        if ($value === '' || $value === null) {
            $this->attributes['lost_reason'] = null;
            return;
        }

        if ($value instanceof BackedEnum) {
            $this->attributes['lost_reason'] = $value->value;
            return;
        }

        $this->attributes['lost_reason'] = $value;
    }

    public function getLostReasonLabelAttribute(): string
    {
        return $this->lost_reason?->label() ?? '';
    }

    public function getMRIStatusLabelAttribute(): string {
        return $this->mri_status?->label() ?? '-';
    }

    public function getHasDiagnosisFormAttribute(): bool
    {
        return $this->diagnosis_form_id !== null;
    }

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
     * Get the persons associated with the lead (repository-based).
     */
    public function getPersonsAttribute()
    {
        try {
            return Person::whereIn('id',
                DB::table('lead_persons')->where('lead_id', $this->id)->pluck('person_id')
            )->get();
        } catch (Exception $e) {
            Log::warning('Could not load persons for lead', ['lead_id' => $this->id, 'error' => $e->getMessage()]);
            return collect();
        }
    }

    /**
     * Get the anamnesis records associated with the lead.
     */
    public function getAnamnesisAttribute()
    {
        try {
            return Anamnesis::where('lead_id', $this->id)->get();
        } catch (Exception $e) {
            Log::warning('Could not load anamnesis for lead', ['lead_id' => $this->id, 'error' => $e->getMessage()]);
            return collect();
        }
    }

    public function getAgeAttribute(): ?int
    {
        if (!$this->date_of_birth) {
            return null;
        }

        return $this->date_of_birth->age;
    }

    public function getDiagnoseDownloadUrlAttribute(): ?string
    {
        if ($this->diagnosis_form_id) {
            return route('admin.leads.diagnosis-form.download', $this->id);
        }

        return null;
    }
    /**
     * Attach persons to this lead.
     */
    public function attachPersons(array $personIds)
    {
        foreach ($personIds as $personId) {
            DB::table('lead_persons')->insertOrIgnore([
                'lead_id' => $this->id,
                'person_id' => $personId,
            ]);
        }

        $this->createMissingAnamnesis($personIds);
    }

    /**
     * Sync persons to this lead (replace all existing).
     */
    public function syncPersons(array $personIds)
    {
        // Remove existing relationships
        DB::table('lead_persons')->where('lead_id', $this->id)->delete();

        // Add new relationships
        if (!empty($personIds)) {
            $this->attachPersons($personIds);
        }
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
        return $this->hasMany(ActivityProxy::modelClass());
    }


    /**
     * Get the emails.
     */
    public function emails()
    {
        return $this->hasMany(EmailProxy::modelClass());
    }

    /**
     * The tags that belong to the lead.
     */
    public function tags()
    {
        return $this->belongsToMany(TagProxy::modelClass(), 'lead_tags')
            ->withPivot(['lead_id', 'tag_id']);
    }

    /**
     * Persons related to this lead (many-to-many via lead_persons).
     */
    public function persons()
    {
        return $this->belongsToMany(Person::class, 'lead_persons', 'lead_id', 'person_id')
            ->withPivot(['lead_id', 'person_id']);
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
     * Get the address that belongs to the lead.
     */
    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    /**
     * Get the channel that owns the lead.
     */
    public function channel()
    {
        return $this->belongsTo(Channel::class, 'lead_channel_id');
    }

    /**
     * Get the department that owns the lead.
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Get the organization that owns the lead.
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function hasOrganization(): bool {
        return !is_null($this->organization_id);
    }

    /**
     * Get the contact person for this lead.
     */
    public function contactPerson()
    {
        return $this->belongsTo(Person::class, 'contact_person_id');
    }

    public function hasContactPerson(): bool {
        return !is_null($this->contact_person_id);
    }


    /**
     * Returns the rotten days
     */
    public function getRottenDaysAttribute()
    {
        if (! $this->stage) {
            return 0;
        }

        if ($this->stage->is_won || $this->stage->is_lost) {
            return 0;
        }

        if (! $this->created_at) {
            return 0;
        }

        $rottenDate = $this->created_at->addDays($this->pipeline->rotten_days);

        return $rottenDate->diffInDays(Carbon::now(), false);
    }

    public function getContactPersonOrFirstPerson(): ?Person    {
        if ($this->hasContactPerson()) {
            return $this->contactPerson()->first();
        }
        return $this->persons()->first();
    }

    /**
     * Get all persons (contact person and linked persons) as a single collection.
     * Duplicates are removed based on person ID.
     *
     * @return Collection
     */
    public function getContactAndPersons(): Collection
    {
        $allPersons = collect();

        // Add contact person if exists
        if ($this->hasContactPerson() && $this->contactPerson) {
            $allPersons->push($this->contactPerson);
        }

        // Add linked persons
        $linkedPersons = $this->persons()->get();
        $allPersons = $allPersons->merge($linkedPersons)->unique('id');

        return $allPersons;
    }

    public function getOpenActivitiesCountAttribute(): int
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
     * Compute unread emails including emails linked via activities.
     */
    public function getUnreadEmailsCountNestedAttribute(): int
    {
        try {
            $direct = (int) $this->emails()->where('is_read', 0)->count();

            $activityEmailIds = app(DB::class)
                ::table('emails')
                ->where('lead_id', $this->id)
                ->where('is_read', 0)
                ->count();

            return $direct + (int) $activityEmailIds;
        } catch (Throwable $e) {
            return (int) $this->unread_emails_count;
        }
    }



    public function getNameAttribute($value): string
    {
        $parts = [];

        if ($this->first_name) {
            $parts[] = trim($this->first_name);
        }

        if ($this->lastname_prefix) {
            $parts[] = trim($this->lastname_prefix);
        }

        if ($this->last_name) {
            $parts[] = trim($this->last_name);
        }
        if(!empty($this->married_name)) {
            $marriedNameParts = [];
            if ($this->married_name_prefix) {
                $marriedNameParts[] = trim($this->married_name_prefix);
            }
            if ($this->married_name) {
                $marriedNameParts[] = trim($this->married_name);
            }
            $parts[] = '/ '.implode(' ', array_filter($marriedNameParts));
        }

        return implode(' ', array_filter($parts));
    }

    /**
     * Create missing anamnesis records for this lead and the given person IDs.
     * Uses database-level unique constraint protection to prevent duplicates.
     */
    private function createMissingAnamnesis(array $personIds): void
    {
        foreach ($personIds as $personId) {
            try {
                // Use firstOrCreate to prevent race conditions and duplicates
                Anamnesis::firstOrCreate(
                    [
                        'lead_id' => $this->id,
                        'person_id' => $personId,
                    ],
                    [
                        'id' => Str::uuid(),
                        'name' => 'Anamnesis voor ' . $this->name,
                        'created_by' => auth()->id() ?? $this->user_id ?? 1,
                        'updated_by' => auth()->id() ?? $this->user_id ?? 1,
                    ]
                );
            } catch (Exception $e) {
                // Log error but continue with other person IDs
                Log::error('Failed to create anamnesis for lead-person combination', [
                    'lead_id' => $this->id,
                    'person_id' => $personId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function findAnamnesisByPersonId(int $personId): Anamnesis
    {
        return Anamnesis::where('lead_id', $this->id)
            ->where('person_id', $personId)->firstOrFail();
    }

    /**
     * Get the count of persons associated with this lead.
     */
    public function getPersonsCountAttribute(): int
    {
        return (int) $this->persons()->count();
    }

    /**
     * Check if this lead has multiple persons.
     */
    public function hasMultiplePersons(): bool
    {
        return $this->persons()->count() > 1;
    }

    /**
     * Check if person fields may be edited (i.e., no persons are linked).
     */
    public function mayEditPersonFields(): bool
    {
        return $this->persons()->count() === 0;
    }

    public function hasPotentialDuplicates() : bool
    {
        return  $this->getPotentialDuplicatesCount() > 0;
    }

    public function getPotentialDuplicatesCount() : int
    {
        // Only check for duplicates if the lead has basic required data
        if (!$this->id || !$this->name) {
            return 0;
        }

        return app(LeadRepository::class)->findNumberPotentialDuplicates($this);
    }

    /**
     * Get the default group_id based on department relationship.
     * Uses the Department mapping function.
     *
     * @return int The group ID
     * @throws Exception if department mapping fails
     */
    public function getDefaultGroupId(): int
    {
        return Department::getGroupIdForLead($this);
    }

    /**
     * Override the update method to validate status transitions.
     *
     * @param array $attributes
     * @param array $options
     * @return bool
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(array $attributes = [], array $options = [])
    {
        // Check if lead_pipeline_stage_id is being updated
        if (isset($attributes['lead_pipeline_stage_id']) &&
            $attributes['lead_pipeline_stage_id'] != $this->lead_pipeline_stage_id) {

            // Validate the status transition
            LeadStatusTransitionValidator::validateTransition($this, $attributes['lead_pipeline_stage_id']);
        }

        return parent::update($attributes, $options);
    }

    /**
     * Update the lead's stage with validation.
     *
     * @param int $newStageId
     * @return bool
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateStage(int $newStageId): bool
    {
        // Validate the status transition
        LeadStatusTransitionValidator::validateTransition($this, $newStageId);

        return $this->update(['lead_pipeline_stage_id' => $newStageId]);
    }

    public function getSugarLinkAttribute() :?string
    {
        if ($this->external_id) {
            $baseUrl = config('services.sugarcrm.base_url');
            $record = $this->external_id;
            return "{$baseUrl}index.php?module=Leads&offset=1&stamp=1758188884015851000&return_module=Leads&action=DetailView&record={$record}";
        }
        return null;
    }
}
