<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TwoFactorCodeMail extends Mailable
{
    /**
     * @param  string  $renderedHtml  Fully rendered template HTML from CrmMailService::renderTemplate()
     */
    public function __construct(
        public readonly string $subjectLine,
        public readonly string $renderedHtml,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'adminc.emails.raw-html',
            with: ['html' => $this->renderedHtml],
        );
    }
}
