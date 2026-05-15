<?php

namespace App\Console\Commands;

use App\Enums\EmailTemplateCode;
use App\Models\PatientNotification;
use App\Services\Mail\CrmMailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Webkul\Contact\Models\Person;

/**
 * Sends email notifications to patient if there is new content.
 * Only in a window of 2 hours, max 1 email. (can be configured)
 */
class SendPatientPortalNotificationEmails extends Command
{
    protected $signature = 'patient:send-notification-email';

    protected $description = 'Send digest notification emails when due (patient portal new content).';

    public function __construct(
        private readonly CrmMailService $crmMailService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $portalUrl = (string) config('services.portal.patient.web_url', '');

        $persons = Person::query()
            ->where('is_active', true)
            ->whereNotNull('keycloak_user_id')
            ->whereNotNull('patient_portal_notify_scheduled_at')
            ->where('patient_portal_notify_scheduled_at', '<=', now())
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('patient_notifications')
                    ->whereColumn('patient_notifications.patient_id', 'persons.id')
                    ->whereNull('patient_notifications.dismissed_at')
                    ->whereNull('patient_notifications.last_notified_by_email_at');
            })
            ->get();

        if ($persons->isEmpty()) {
            $this->info('No patient portal digest emails due.');

            return Command::SUCCESS;
        }

        $failed = 0;

        foreach ($persons as $person) {
            try {
                $this->sendForPerson($person, $portalUrl);
            } catch (Throwable $e) {
                if (str_contains($e->getMessage(), 'Email sending blocked')) {
                    Log::warning('Patient portal digest skipped: recipient blocked by allowlist', [
                        'patient_id'    => $person->id,
                        'patient_email' => $person->findDefaultEmail(),
                    ]);

                    continue;
                }

                $failed++;
                Log::error('Patient portal digest: failed for person', [
                    'patient_id'    => $person->id,
                    'patient_email' => $person->findDefaultEmail(),
                    'exception'     => $e,
                ]);

                if ($e instanceof \RuntimeException && str_contains($e->getMessage(), 'Email template')) {
                    return Command::FAILURE;
                }
            }
        }

        if ($failed > 0) {
            Log::warning("Patient portal digest: {$failed}/{$persons->count()} failed.");
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function sendForPerson(Person $person, string $portalUrl): void
    {
        $pendingExists = PatientNotification::query()
            ->forPatient($person->id)
            ->forMailNotification()
            ->exists();

        if (! $pendingExists) {
            $person->forceFill(['patient_portal_notify_scheduled_at' => null])->save();

            return;
        }

        $recipientEmail = $person->findDefaultEmail();

        if (! $recipientEmail) {
            Log::warning('Patient portal digest skipped: no default email', [
                'patient_id' => $person->id,
            ]);

            return;
        }
        DB::transaction(function () use ($person, $portalUrl) {
            if (! $this->crmMailService->sendToPersonTemplate(
                $person,
                EmailTemplateCode::PATIENT_PORTAL_NOTIFICATION_NEW_CONTENT,
                [
                    'lastname'    => (string) ($person->last_name ?? ''),
                    'portal_url'  => $portalUrl,
                    'portal_link' => $portalUrl,
                    'person'      => $person,
                ], isNotify: true
            )) {
                return;
            }

            PatientNotification::forPatient($person->id)
                ->forMailNotification()
                ->update([
                    'last_notified_by_email_at' => now(),
                ]);

            $person->forceFill([
                'patient_portal_last_notify_email_at' => now(),
                'patient_portal_notify_scheduled_at'  => null,
            ])->save();
        });
    }
}
