<?php

namespace App\Mail;

use App\Enums\Departments;
use Illuminate\Mail\Mailable;
use Webkul\Lead\Models\Lead;

class RevOpsNoLeadAlert extends Mailable
{
    public function __construct(public readonly Departments $department, public readonly ?Lead $lastLead)
    {
    }

    public function build(): self
    {
        return $this
            ->subject("[RevOps] Geen nieuwe leads voor {$this->department->value} – 24 uur geen activiteit")
            ->view('emails.revops.no-lead-alert');
    }
}
