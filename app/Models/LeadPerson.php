<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Webkul\Contact\Models\Person;

class LeadPerson extends Pivot
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'lead_persons';

    protected static function booted(): void
    {
        static::created(function (self $pivot) {
            try {
                Anamnesis::firstOrCreate(
                    [
                        'lead_id'   => $pivot->lead_id,
                        'person_id' => $pivot->person_id,
                    ],
                    [
                        'id'         => Str::uuid(),
                        'name'       => 'Anamnesis voor '.Person::findOrFail($pivot->person_id)->name,
                        'created_by' => auth()->id() ?? $pivot->lead?->user_id ?? 1,
                        'updated_by' => auth()->id() ?? $pivot->lead?->user_id ?? 1,
                    ]
                );
            } catch (Exception $e) {
                Log::error('Failed to create anamnesis for lead-person combination', [
                    'lead_id'   => $pivot->lead_id,
                    'person_id' => $pivot->person_id,
                    'error'     => $e->getMessage(),
                ]);
            }
        });
    }
}
