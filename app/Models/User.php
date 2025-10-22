<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Webkul\User\Models\Role;

class User extends Authenticatable
{
    use HasAuditTrail, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'created_by',
        'updated_by',
        'signature',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'created_by'        => 'integer',
        'updated_by'        => 'integer',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'name',
    ];

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
        if (! empty($value)) {
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
        return $this->belongsTo(Role::class);
    }

    /**
     * Checks if user has permission to perform certain action.
     *
     * @param  string  $permission
     * @return bool
     */
    public function hasPermission($permission)
    {
        if (! $this->role) {
            return false;
        }

        // If permission_type is 'all', user has all permissions
        if ($this->role->permission_type == 'all') {
            return true;
        }

        if ($this->role->permission_type == 'custom' && ! $this->role->permissions) {
            return false;
        }

        $permissions = $this->role->permissions;
        if (! is_array($permissions)) {
            return false;
        }

        return in_array($permission, $permissions);
    }
}
