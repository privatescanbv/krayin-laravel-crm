<?php

namespace App\Services\Mail;

use Illuminate\Database\Eloquent\Collection;
use Webkul\Activity\Models\Activity;
use Webkul\Email\Models\Email;

/**
 * Read side of the manual link/unlink audit trail App\Observers\EmailObserver writes.
 * An empty result means every entity link on the email was set by the auto-link
 * algorithm (EmailObserver only logs changes made by an authenticated admin).
 */
class EmailLinkAuditLog
{
    /**
     * @return Collection<int, Activity>
     */
    public function forEmail(Email $email): Collection
    {
        return Activity::with('user')
            ->where('additional->email_id', $email->id)
            ->latest('id')
            ->get();
    }
}
