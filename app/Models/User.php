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
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'created_by',
        'updated_by',
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
