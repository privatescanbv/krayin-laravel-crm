<?php

namespace Webkul\Contact\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Webkul\Activity\Models\ActivityProxy;
use Webkul\Activity\Traits\LogsActivity;
use Webkul\Attribute\Traits\CustomAttribute;
use Webkul\Contact\Contracts\Person as PersonContract;
use Webkul\Contact\Database\Factories\PersonFactory;
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
        'emails'          => 'array',
        'contact_numbers' => 'array',
        'date_of_birth'   => 'date',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'emails',
        'contact_numbers',
        'job_title',
        'user_id',
        'organization_id',
        'unique_id',
        'salutation',
        'first_name',
        'last_name',
        'lastname_prefix',
        'maiden_name',
        'maiden_name_prefix',
        'initials',
        'date_of_birth',
        'gender',
    ];

    /**
     * Get the user that owns the lead.
     */
    public function user()
    {
        return $this->belongsTo(UserProxy::modelClass());
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
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return PersonFactory::new();
    }

    /**
     * Get the full name attribute.
     *
     * @return string
     */
    public function getNameAttribute($value)
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
        
        return implode(' ', array_filter($parts));
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
}
