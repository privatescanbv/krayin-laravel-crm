<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperClinicDepartment
 */
class ClinicDepartment extends Model
{
    use HasFactory;

    protected $table = 'clinic_departments';

    protected $fillable = ['clinic_id', 'name', 'description', 'email', 'order_confirmation_note'];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
