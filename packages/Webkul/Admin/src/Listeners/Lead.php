<?php

namespace Webkul\Admin\Listeners;

use Webkul\Email\Repositories\EmailRepository;

class Lead
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(protected EmailRepository $emailRepository) {}

    /**
     * @param  \Webkul\Lead\Models\Lead  $lead
     * @return void
     */
    public function linkToEmail($lead)
    {
        if (! request('email_id')) {
            return;
        }

        $emailId = (int) request('email_id');

        $this->emailRepository->update([
            'lead_id' => $lead->id,
        ], $emailId);

        $this->emailRepository->moveToProcessedIfInbox($emailId);
    }
}
