<?php

namespace Webkul\Contact\Models;

use App\Casts\EncryptedString;
use App\Enums\PersonGender;
use App\Enums\PersonSalutation;
use App\Models\Address;
use App\Models\Anamnesis;
use App\Models\PatientMessage;
use App\Traits\HasDefaultContactInfo;
use Exception;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Webkul\Activity\Models\ActivityProxy;
use Webkul\Activity\Traits\LogsActivity;
use Webkul\Attribute\Traits\CustomAttribute;
use Webkul\Contact\Contracts\Person as PersonContract;
use Webkul\Contact\Database\Factories\PersonFactory;
use App\Models\SalesLead;
use Webkul\Lead\Models\Lead;
use Webkul\Tag\Models\TagProxy;
use Webkul\User\Models\UserProxy;

class Person extends Model implements PersonContract
{
    use CustomAttribute, HasDefaultContactInfo, HasFactory, LogsActivity, SoftDeletes;

    /**
     * Default attribute values.
     *
     * New persons should be active by default unless explicitly deactivated.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Table name.
     *
     * @var string
     */
    protected $table = 'persons';

    /**
     * Eager loading.
     *
     * @var string
     */
    protected $with = 'organization';

    /**
     * The attributes that are castable.
     *
     * @var array
     */
    protected $casts = [
        'emails'        => 'array',
        'phones'        => 'array',
        'date_of_birth' => 'date',
        'gender'        => PersonGender::class,
        'salutation'    => PersonSalutation::class,
        'is_active'     => 'boolean',
        'national_identification_number' => EncryptedString::class,
    ];

    /**
     * Attributes that should be hidden from array/json casts.
     *
     * @var array
     */
    protected $hidden = [
        'password',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'emails',
        'phones',
        'job_title',
        'user_id',
        'organization_id',
        'external_id',
        'salutation',
        'first_name',
        'last_name',
        'lastname_prefix',
        'married_name',
        'married_name_prefix',
        'initials',
        'date_of_birth',
        'gender',
        'created_by',
        'updated_by',
        'keycloak_user_id',
        'is_active',
        'password',
        'national_identification_number',
        'address_id',
    ];

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

        if ($value instanceof \BackedEnum) {
            $this->attributes['gender'] = $value->value;
            return;
        }

        $this->attributes['gender'] = $value;
    }

    /**
     * Encrypt and store the portal password, keeping plaintext temporarily for observers.
     */
    public function setPasswordAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['password'] = null;
            unset($this->attributes['_plaintext_password']);

            return;
        }

        $this->attributes['_plaintext_password'] = $value;
        $this->attributes['password'] = Crypt::encryptString($value);
    }

    /**
     * Provide the plaintext password to observers when available.
     */
    public function getPlaintextPassword(): ?string
    {
        if (isset($this->attributes['_plaintext_password'])) {
            return $this->attributes['_plaintext_password'];
        }

        return $this->getDecryptedPassword();
    }

    /**
     * Decrypt the stored portal password (if any).
     */
    public function getDecryptedPassword(): ?string
    {
        if (empty($this->attributes['password'])) {
            return null;
        }

        try {
            return Crypt::decryptString($this->attributes['password']);
        } catch (Exception $e) {
            Log::warning('Failed to decrypt person portal password', [
                'person_id' => $this->id,
                'error'     => $e->getMessage(),
            ]);

            return null;
        }
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

        if ($value instanceof \BackedEnum) {
            $this->attributes['salutation'] = $value->value;
            return;
        }

        $this->attributes['salutation'] = $value;
    }

    /**
     * Get the user that owns the lead.
     */
    public function user()
    {
        return $this->belongsTo(UserProxy::modelClass());
    }

    /**
     * Get the user who created the person.
     */
    public function createdBy()
    {
        return $this->belongsTo(UserProxy::modelClass(), 'created_by');
    }

    /**
     * Get the user who last updated the person.
     */
    public function updatedBy()
    {
        return $this->belongsTo(UserProxy::modelClass(), 'updated_by');
    }

    /**
     * Get the organization that owns the person.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function organization()
    {
        return $this->belongsTo(OrganizationProxy::modelClass());
    }

    /**
     * Get the activities.
     */
    public function activities()
    {
        return $this->belongsToMany(ActivityProxy::modelClass(), 'person_activities');
    }

    /**
     * Get all leads gekoppeld aan deze persoon (repository-based).
     */
    public function getLeadsAttribute()
    {
        try {
            return Lead::whereIn('id',
                DB::table('lead_persons')->where('person_id', $this->id)->pluck('lead_id')
            )->get();
        } catch (Exception $e) {
            Log::warning('Could not load leads for person', ['person_id' => $this->id, 'error' => $e->getMessage()]);
            return collect();
        }
    }

    /**
     * Get all sales leads gekoppeld aan deze persoon.
     */
    public function getSalesLeadsAttribute()
    {
        try {
            return SalesLead::whereIn('id',
                DB::table('saleslead_persons')->where('person_id', $this->id)->pluck('saleslead_id')
            )->get();
        } catch (Exception $e) {
            Log::warning('Could not load sales leads for person', ['person_id' => $this->id, 'error' => $e->getMessage()]);
            return collect();
        }
    }

    /**
     * The tags that belong to the person.
     */
    public function tags()
    {
        return $this->belongsToMany(TagProxy::modelClass(), 'person_tags');
    }

    /**
     * Get the address that belongs to the person.
     */
    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    /**
     * Get all anamnesis records for this person.
     */
    public function anamnesis()
    {
        return $this->hasMany(Anamnesis::class, 'person_id');
    }

    /**
     * Get the patient messages for the person.
     */
    public function patientMessages()
    {
        return $this->hasMany(PatientMessage::class, 'person_id');
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return Factory
     */
    protected static function newFactory()
    {
        return PersonFactory::new();
    }

    /**
     * Ensure temporary attributes are removed before update.
     */
    protected function performUpdate($query)
    {
        if (isset($this->attributes['_plaintext_password'])) {
            unset($this->attributes['_plaintext_password']);
        }

        return parent::performUpdate($query);
    }

    /**
     * Ensure temporary attributes are removed before insert.
     */
    protected function performInsert($query)
    {
        if (isset($this->attributes['_plaintext_password'])) {
            unset($this->attributes['_plaintext_password']);
        }

        return parent::performInsert($query);
    }

    /**
     * Get the full name attribute.
     */
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

        if (!$this->is_active) {
            $parts[] = '[Inactief]';
        }
        return implode(' ', array_filter($parts));
    }

    /**
     * Calculate and return the age of the person based on date_of_birth
     */
    public function getAgeAttribute(): ?int
    {
        if (!$this->date_of_birth) {
            return null;
        }

        return $this->date_of_birth->age;
    }

    public function getSugarLinkAttribute() :?string
    {
        if ($this->external_id) {
            $baseUrl = config('services.sugarcrm.base_url');
            $record = $this->external_id;
            return "{$baseUrl}index.php?module=Contacts&offset=1&stamp=1758266828019787100&return_module=Contacts&action=DetailView&record={$record}";
        }
        return null;
    }
}
