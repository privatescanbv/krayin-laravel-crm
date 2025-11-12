<?php

namespace Webkul\Email\Mails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email as MimeEmail;

class Email extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new email instance.
     *
     * @return void
     */
    public function __construct(public $email) {}

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $from = $this->email->from;
        
        // Always use the authenticated user's name for the from name
        // This ensures personalization and avoids using old names from the database
        $user = auth()->guard('user')->user();
        $name = $user?->name ?? 'Privatescan medewerker';

        // Extract email address from from field
        $emailAddress = null;
        
        if (is_array($from)) {
            // Standard format: {"name": "...", "email": "..."}
            if (isset($from['email'])) {
                $emailAddress = $from['email'];
            } 
            // Legacy array format: {"email@example.com": "Name"}
            else {
                $emailAddress = array_key_first($from);
            }
        } else {
            // from is string (email address only)
            $emailAddress = $from;
        }

        // Use the email address from the database, but always use the current user's name
        $this->from($emailAddress ?? config('mail.from.address'), $name);

        // Signature is already added in EmailRepository::update() when email is saved
        // No need to add it again here to avoid duplicates
        $emailContent = $this->email->reply;

        $this->to($this->email->reply_to)
            ->replyTo($this->email->parent_id ? $this->email->parent->unique_id : $this->email->unique_id)
            ->cc($this->email->cc ?? [])
            ->bcc($this->email->bcc ?? [])
            ->subject($this->email->parent_id ? $this->email->parent->subject : $this->email->subject)
            ->html($emailContent);

        $this->withSymfonyMessage(function (MimeEmail $message) {
            $message->getHeaders()->addIdHeader('Message-ID', $this->email->message_id);

            $message->getHeaders()->addTextHeader('References', $this->email->parent_id
                ? implode(' ', $this->email->parent->reference_ids)
                : implode(' ', $this->email->reference_ids)
            );
        });

        foreach ($this->email->attachments as $attachment) {
            $this->attachFromStorage($attachment->path);
        }

        return $this;
    }
}
