<?php

namespace App\Models;

use App\Actions\Leads\SnapshotLeadCustomerTypeAction;
use Exception;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

/**
 * @mixin IdeHelperLeadPerson
 */
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
                        'name'       => 'Anamnese voor '.Person::findOrFail($pivot->person_id)->name,
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

            self::snapshotCustomerType($pivot);
        });

        static::deleted(fn (self $pivot) => self::snapshotCustomerType($pivot));
    }

    /**
     * Which persons are on the lead decides its customer type, so re-derive it whenever
     * that set changes. A failure here must never block linking a person.
     */
    private static function snapshotCustomerType(self $pivot): void
    {
        try {
            $lead = Lead::find($pivot->lead_id);

            if ($lead) {
                app(SnapshotLeadCustomerTypeAction::class)->execute($lead);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
