<?php

namespace Webkul\User\Models;

use App\Enums\RoleCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Webkul\User\Contracts\Role as RoleContract;

class Role extends Model implements RoleContract
{
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'permission_type',
        'permissions',
        'code',
    ];

    protected $casts = [
        'permissions' => 'array',
        'code'        => RoleCode::class,
    ];

    /**
     * Get the users.
     */
    public function users()
    {
        return $this->hasMany(UserProxy::modelClass());
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return \Database\Factories\RoleFactory::new();
    }
}
