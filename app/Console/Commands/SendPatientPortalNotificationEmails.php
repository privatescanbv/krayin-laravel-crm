<?php

namespace App\Console\Commands;

use App\Jobs\SendPatientPortalEmailJob;
use Illuminate\Console\Command;
use Webkul\Contact\Models\Person;

/**
 * Sends email notifications to patient if there is new content.
 * Only in a window of 2 hours, max 1 email. (can be configured)
 */
class SendPatientPortalNotificationEmails extends Command
{
    protected $signature = 'patient:send-notification-email';

    protected $description = 'Send digest notification emails when due (patient portal new content).';

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

        foreach ($persons as $person) {
            SendPatientPortalEmailJob::dispatch($person->id, $portalUrl);
        }

        $this->info("Dispatched {$persons->count()} patient portal email job(s).");

        return Command::SUCCESS;
    }
}
