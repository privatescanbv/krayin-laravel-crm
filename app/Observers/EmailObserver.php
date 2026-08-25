<?php

namespace App\Observers;

use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Email\Models\Email;

/**
 * Logs a system activity whenever a human changes an email's entity links (order_id,
 * sales_lead_id, lead_id, person_id, clinic_id) — e.g. via "Koppel handmatig" or an
 * AI-suggestie klik — so it can be shown afterwards who linked what and when.
 *
 * Auto-link algorithm changes (ingest sync, LLM auto-trigger, repair command) are
 * deliberately NOT logged here to keep this low-volume: they all run without an
 * authenticated admin guard, so absence of a log entry for a field IS the signal that
 * it was set automatically. Mirrors the field-change logging pattern in OrderObserver.
 *
 * These activities deliberately do NOT get order_id/person_id/... set on them: that
 * would attach them to that entity's own activity timeline (Order/Lead/Person view),
 * where they'd show up as full-size, everyday-looking activities. They're pure email
 * audit trail — findable only via additional->email_id — and rendered small/collapsed
 * in the email's own "Audit" panel (see email-action-panel.blade.php).
 */
class EmailObserver
{
    /**
     * Entity-link foreign keys managed by App\Services\Mail\EmailEntityLinker, and the
     * label used in the audit title.
     */
    private const LINK_FIELDS = [
        'order_id'      => 'Order',
        'sales_lead_id' => 'Sales lead',
        'lead_id'       => 'Lead',
        'person_id'     => 'Contact',
        'clinic_id'     => 'Kliniek',
    ];

    public function __construct(private readonly ActivityRepository $activityRepository) {}

    public function created(Email $email): void
    {
        $this->logManualLinkChanges($email, atCreation: true);
    }

    public function updated(Email $email): void
    {
        $this->logManualLinkChanges($email, atCreation: false);
    }

    private function logManualLinkChanges(Email $email, bool $atCreation): void
    {
        if (! auth()->guard('user')->check()) {
            return;
        }

        $userId = auth()->guard('user')->id();

        foreach (self::LINK_FIELDS as $field => $label) {
            $new = $email->getAttribute($field);
            $old = $atCreation ? null : $email->getOriginal($field);

            if ($atCreation ? empty($new) : ! $email->wasChanged($field)) {
                continue;
            }

            if ($old == $new) {
                continue;
            }

            $this->activityRepository->createSystem([
                'title'      => sprintf(
                    'E-mail #%d %s aan %s #%d',
                    $email->id,
                    $new ? 'gekoppeld' : 'ontkoppeld',
                    $label,
                    $new ?? $old
                ),
                'additional' => [
                    'field'         => $field,
                    'old_value'     => $old,
                    'new_value'     => $new,
                    'email_id'      => $email->id,
                    'email_subject' => $email->subject,
                ],
                'user_id' => $userId,
            ]);
        }
    }
}
