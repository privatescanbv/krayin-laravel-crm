<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicDepartment extends Model
{
    use HasFactory;

    protected $table = 'clinic_departments';

    protected $fillable = ['clinic_id', 'name', 'description', 'email'];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
