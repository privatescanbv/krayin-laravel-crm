<?php

namespace App\Jobs;

use App\Enums\EmailTemplateCode;
use App\Models\PatientNotification;
use App\Services\Mail\CrmMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Webkul\Contact\Models\Person;

class SendPatientPortalEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public int $timeout = 120;

    public function __construct(
        private readonly int $personId,
        private readonly string $portalUrl,
    ) {}

    public function handle(CrmMailService $crmMailService): void
    {
        $person = Person::find($this->personId);

        if (! $person) {
            return;
        }

        $hasPending = PatientNotification::query()
            ->forPatient($this->personId)
            ->forMailNotification()
            ->exists();

        if (! $hasPending) {
            $person->forceFill(['patient_portal_notify_scheduled_at' => null])->save();

            return;
        }

        try {
            DB::transaction(function () use ($person, $crmMailService) {
                $sent = $crmMailService->sendToPersonTemplate(
                    $person,
                    EmailTemplateCode::PATIENT_PORTAL_NOTIFICATION_NEW_CONTENT,
                    [
                        'lastname'    => (string) ($person->last_name ?? ''),
                        'portal_url'  => $this->portalUrl,
                        'portal_link' => $this->portalUrl,
                        'person'      => $person,
                    ],
                );

                if (! $sent) {
                    return;
                }

                PatientNotification::forPatient($this->personId)
                    ->forMailNotification()
                    ->update(['last_notified_by_email_at' => now()]);

                $person->forceFill([
                    'patient_portal_notify_scheduled_at'  => null,
                    'patient_portal_last_notify_email_at' => now(),
                ])->save();
            });
        } catch (Throwable $e) {
            if (str_starts_with($e->getMessage(), 'Email sending blocked')) {
                Log::warning('Patient portal email blocked by allowlist, skipping', [
                    'person_id' => $this->personId,
                ]);

                return;
            }
            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Patient portal email permanently failed after retries', [
            'person_id' => $this->personId,
            'error'     => $exception->getMessage(),
        ]);
    }
}
