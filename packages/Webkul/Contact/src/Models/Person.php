<?php

namespace Webkul\Contact\Models;

use App\Enums\PersonGender;
use App\Enums\PersonSalutation;
use App\Models\Anamnesis;
use App\Models\PatientMessage;
use Exception;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Models\ActivityProxy;
use Webkul\Activity\Traits\LogsActivity;
use Webkul\Attribute\Traits\CustomAttribute;
use Webkul\Contact\Contracts\Person as PersonContract;
use Webkul\Contact\Database\Factories\PersonFactory;
use Webkul\Email\Models\Email;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\LeadProxy;
use Webkul\Tag\Models\TagProxy;
use Webkul\User\Models\UserProxy;
use App\Models\Address;

class Person extends Model implements PersonContract
{
    use CustomAttribute, HasFactory, LogsActivity;

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
        'unique_id',
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
    ];

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
        return $this->hasOne(Address::class);
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

        return implode(' ', array_filter($parts));
    }

    public function findDefaultEmailOrError(): string {
        return $this->findDefaultEmail() ?? throw new Exception('No default email found for person ID ' . $this->id);
    }

    /**
     * Find the default email address from the emails array
     */
    public function findDefaultEmail(): ?string
    {
        if (empty($this->emails)) {
            return null;
        }

        // First, try to find an email marked as default
        foreach ($this->emails as $email) {
            if (isset($email['is_default']) && ($email['is_default'] === true || $email['is_default'] === 'on' || $email['is_default'] === '1')) {
                return $email['value'] ?? null;
            }
        }

        // If no default is found, return the first email's value
        return $this->emails[0]['value'] ?? null;
    }

    /**
     * Find the default phone number from the phones array
     */
    public function findDefaultPhone(): ?string
    {
        if (empty($this->phones)) {
            return null;
        }

        // First, try to find a phone marked as default
        foreach ($this->phones as $phone) {
            if (isset($phone['is_default']) && ($phone['is_default'] === true || $phone['is_default'] === 'on' || $phone['is_default'] === '1')) {
                return $phone['value'] ?? null;
            }
        }

        // If no default is found, return the first phone's value
        return $this->phones[0]['value'] ?? null;
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
