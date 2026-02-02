<?php

namespace App\Console\Commands;

use App\Models\PatientNotification;
use App\Services\Mail\CrmMailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Webkul\Contact\Models\Person;

class SendPatientPortalNotificationEmails extends Command
{
    protected $signature = 'patient:send-notification-email';

    protected $description = 'Send notification emails to patients when something is ready in the patient portal.';

    public function __construct(
        private readonly CrmMailService $crmMailService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $patientIds = PatientNotification::forMailNotification()
            ->select('patient_id')
            ->distinct()
            ->pluck('patient_id');

        if ($patientIds->isEmpty()) {
            $this->info('No patient notifications pending for email.');

            return Command::SUCCESS;
        }

        $portalUrl = (string) config('services.portal.patient.web_url', '');

        foreach ($patientIds as $patientId) {
            $person = Person::query()->find($patientId);

            if (! $person) {
                Log::warning('Patient notification email skipped: person not found', [
                    'patient_id' => $patientId,
                ]);

                continue;
            }

            $recipientEmail = $person->findDefaultEmail();

            if (! $recipientEmail) {
                Log::warning('Patient notification email skipped: no default email', [
                    'patient_id' => $patientId,
                ]);

                continue;
            }

            try {
                DB::transaction(function () use ($person, $patientId, $portalUrl) {
                    $this->crmMailService->sendToPersonTemplate($person, 'patient-portal-notification', [
                        'lastname'   => (string) ($person->last_name ?? ''),
                        'portal_url' => $portalUrl,
                        'person'     => $person,
                    ]);

                    PatientNotification::forPatient($patientId)
                        ->forMailNotification()
                        ->update([
                            'last_notified_by_email_at' => now(),
                        ]);
                });
            } catch (Throwable $e) {
                Log::error('Failed sending patient portal notification email', [
                    'patient_id' => $patientId,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return Command::SUCCESS;
    }
}
