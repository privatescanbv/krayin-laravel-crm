<?php

namespace Webkul\User\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Webkul\User\Contracts\User as UserContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Database\Factories\WebkulUserFactory;

class User extends Authenticatable implements UserContract
{
    use HasApiTokens, Notifiable, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'image',
        'password',
        'api_token',
        'role_id',
        'status',
        'external_id',
        'keycloak_user_id',
        'view_permission',
        'signature',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'api_token',
        'remember_token',
    ];


    /**
     * Perform a model update operation.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return bool
     */
    protected function performUpdate($query)
    {
        // Remove temporary attributes that shouldn't be saved to database
        if (isset($this->attributes['_plaintext_password'])) {
            unset($this->attributes['_plaintext_password']);
        }

        return parent::performUpdate($query);
    }

    /**
     * Perform a model insert operation.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return bool
     */
    protected function performInsert($query)
    {
        // Remove temporary attributes that shouldn't be saved to database
        if (isset($this->attributes['_plaintext_password'])) {
            unset($this->attributes['_plaintext_password']);
        }

        return parent::performInsert($query);
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'name',
    ];

    /**
     * Get image url for the product image.
     */
    public function image_url()
    {
        if (! $this->image) {
            return;
        }

        return Storage::url($this->image);
    }

    /**
     * Get image url for the product image.
     */
    public function getImageUrlAttribute()
    {
        return $this->image_url();
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $array = parent::toArray();

        $array['image_url'] = $this->image_url;

        return $array;
    }

    /**
     * Set the password attribute - hash it automatically.
     * The plaintext password is stored temporarily for UserObserver.
     */
    public function setPasswordAttribute($value): void
    {
        // Store plaintext password temporarily if it's not already hashed
        if (! empty($value) && ! str_starts_with($value, '$2y$') && ! str_starts_with($value, '$2a$') && ! str_starts_with($value, '$2b$')) {
            // Store in a temporary attribute that UserObserver can access
            $this->attributes['_plaintext_password'] = $value;
            // Hash the password for storage
            $this->attributes['password'] = bcrypt($value);
        } else {
            // Already hashed or empty, store as-is
            $this->attributes['password'] = $value;
        }
    }

    /**
     * Get the plaintext password if available (for UserObserver).
     */
    public function getPlaintextPassword(): ?string
    {
        return $this->attributes['_plaintext_password'] ?? null;
    }

    /**
     * Get the user's full name.
     */
    public function getNameAttribute(): string
    {
        if ($this->first_name && $this->last_name) {
            return trim("{$this->first_name} {$this->last_name}");
        }

        if ($this->first_name) {
            return $this->first_name;
        }

        if ($this->last_name) {
            return $this->last_name;
        }

        return $this->email ?? 'Unknown User';
    }

    /**
     * Set the user's name by splitting into first and last name.
     */
    public function setNameAttribute($value): void
    {
        if (!empty($value)) {
            $nameParts = explode(' ', $value, 2);
            $this->attributes['first_name'] = $nameParts[0] ?? '';
            $this->attributes['last_name'] = $nameParts[1] ?? '';
        }
    }

    /**
     * Get the role that owns the user.
     */
    public function role()
    {
        return $this->belongsTo(RoleProxy::modelClass());
    }

    /**
     * The groups that belong to the user.
     */
    public function groups()
    {
        return $this->belongsToMany(GroupProxy::modelClass(), 'user_groups');
    }

    /**
     * Checks if user has permission to perform certain action.
     *
     * @param  string  $permission
     * @return bool
     */
    public function hasPermission($permission)
    {
        // Grant all permissions when role is global admin
        if ($this->role && $this->role->permission_type === 'all') {
            return true;
        }

        // For custom permission type, ensure permissions is a non-empty array
        if ($this->role->permission_type === 'custom' && ! $this->role->permissions) {
            return false;
        }

        $permissions = $this->role->permissions;

        if (! is_array($permissions)) {
            return false;
        }

        return in_array($permission, $permissions);
    }

    /**
     * Check if user is a global admin
     *
     * @return bool
     */
    public function isGlobalAdmin(): bool
    {
        if (!$this->role) {
            return false;
        }

        return $this->role->permission_type === 'all';
    }

    /**
     * User settings key-value pairs.
     */
    public function defaultValues()
    {
        return $this->hasMany(UserDefaultValue::class);
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return WebkulUserFactory::new();
    }
}
