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
        'name',
        'first_name',
        'last_name',
        'email',
        'image',
        'password',
        'api_token',
        'role_id',
        'status',
        'external_id',
        'view_permission',
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
