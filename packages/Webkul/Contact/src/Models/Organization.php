<?php

namespace Webkul\Contact\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Webkul\Attribute\Traits\CustomAttribute;
use Webkul\Contact\Contracts\Organization as OrganizationContract;
use Webkul\Contact\Database\Factories\OrganizationFactory;
use Webkul\User\Models\UserProxy;

class Organization extends Model implements OrganizationContract
{
    use CustomAttribute, HasFactory, HasAuditTrail;



    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'user_id',
        'created_by',
        'updated_by',
    ];

    /**
     * Get persons.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function persons()
    {
        return $this->hasMany(PersonProxy::modelClass());
    }

    /**
     * Get the user that owns the lead.
     */
    public function user()
    {
        return $this->belongsTo(UserProxy::modelClass());
    }

    /**
     * Get the address that belongs to the organization.
     */
    public function address()
    {
        return $this->hasOne(\App\Models\Address::class, 'organization_id');
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return Factory
     */
    protected static function newFactory()
    {
        return OrganizationFactory::new();
    }
}
