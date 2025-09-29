<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Validation\ValidationException;

class Shift extends BaseModel
{
    use HasAuditTrail, HasFactory;

    protected $table = 'shifts';

    protected $fillable = [
        'clinic_id',
        'resource_id',
        'starts_at',
        'ends_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'clinic_id'  => 'integer',
        'resource_id' => 'integer',
        'starts_at'   => 'datetime',
        'ends_at'     => 'datetime',
        'created_by'  => 'integer',
        'updated_by'  => 'integer',
    ];

    protected static function booted()
    {
        static::saving(function (Shift $shift) {
            if (! $shift->resource_id) {
                throw ValidationException::withMessages([
                    'resource_id' => 'Resource is verplicht voor een dienst (shift).',
                ]);
            }

            if (! $shift->clinic_id && $shift->resource_id) {
                $shift->clinic_id = optional(Resource::find($shift->resource_id))->clinic_id;
            }

            if (! $shift->clinic_id) {
                throw ValidationException::withMessages([
                    'clinic_id' => 'Clinic is verplicht voor een dienst (shift).',
                ]);
            }

            if ($shift->resource_id) {
                $resource = Resource::find($shift->resource_id);
                if ($resource && $resource->clinic_id && (int) $resource->clinic_id !== (int) $shift->clinic_id) {
                    throw ValidationException::withMessages([
                        'resource_id' => 'Geselecteerde resource hoort niet bij de gekozen kliniek.',
                    ]);
                }
            }
        });
    }

    public function resource()
    {
        return $this->belongsTo(Resource::class);
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }
}
