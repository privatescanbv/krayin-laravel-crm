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
        $defaultName = auth()->guard('user')->user()->name ?? config('mail.from.name', 'Privatescan medewerker');
        $fromEmail = null;
        $fromName = $this->email->name ?? $defaultName;

        if (is_array($from)) {
            if (isset($from['email'])) {
                $fromEmail = $from['email'];
                $fromName = $from['name'] ?? $fromName;
            } elseif (! empty($from) && array_is_list($from)) {
                $fromEmail = $from[0] ?? null;
                $fromName = $from[1] ?? $fromName;
            } else {
                $emailKey = array_key_first($from);
                if ($emailKey) {
                    $fromEmail = $emailKey;
                    $fromName = $from[$emailKey] ?? $fromName;
                }
            }
        } elseif (is_string($from) && $from !== '') {
            $fromEmail = $from;
        }

        if (! $fromEmail) {
            $fromEmail = config('mail.graph.mailbox') ?: config('mail.from.address');
        }

        $this->from($fromEmail, $fromName ?: $defaultName);

        // Add user signature to email content if not already present
        $emailContent = $this->email->reply;
        $user = auth()->guard('user')->user();
        if ($user && $user->signature && strpos($emailContent, $user->signature) === false) {
            $emailContent = $emailContent . "\n\n" . $user->signature;
        }

        $this->to($this->email->reply_to)
            ->cc($this->email->cc ?? [])
            ->bcc($this->email->bcc ?? [])
            ->subject($this->email->parent_id ? $this->email->parent->subject : $this->email->subject)
            ->html($emailContent);

        $this->withSymfonyMessage(function (MimeEmail $message) {
            $headers = $message->getHeaders();

            $headers->remove('Message-ID');
            $headers->addIdHeader('Message-ID', $this->email->message_id);

            $parentMessageId = $this->email->parent?->message_id;
            if ($parentMessageId) {
                $headers->remove('In-Reply-To');
                $headers->addIdHeader('In-Reply-To', $parentMessageId);
            }

            $references = $this->email->parent_id
                ? $this->email->parent->reference_ids
                : $this->email->reference_ids;

            if (is_array($references) && ! empty($references)) {
                $headers->remove('References');
                $headers->addTextHeader('References', implode(' ', array_filter($references)));
            }
        });

        foreach ($this->email->attachments as $attachment) {
            $this->attachFromStorage($attachment->path);
        }

        return $this;
    }
}
