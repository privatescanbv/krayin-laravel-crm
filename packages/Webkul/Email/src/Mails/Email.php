<?php

namespace Webkul\Email\Mails;

use App\Services\Mail\HtmlImageInliner;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
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
        $user = auth()->guard('user')->user();
        $name = $user?->name ?? 'Privatescan medewerker';

        // Extract email address from from field
        $emailAddress = null;

        if (is_array($from)) {
            if (isset($from['email'])) {
                $emailAddress = $from['email'];
            } else {
                $emailAddress = array_key_first($from);
            }
        } else {
            $emailAddress = $from;
        }

        $this->from($emailAddress ?? config('mail.from.address'), $name);

        $isReply = (bool) $this->email->parent_id;
        $subject = $this->buildSubjectLine();

        $this->to($this->email->reply_to)
            ->replyTo($emailAddress ?? config('mail.from.address'), $name)
            ->cc($this->email->cc ?? [])
            ->bcc($this->email->bcc ?? [])
            ->subject($subject)
            ->html(app(HtmlImageInliner::class)->inline($this->email->reply ?? ''));

        $bareId = fn ($id) => trim($id, '<>');
        $inReplyTo = null;
        $references = null;

        $this->withSymfonyMessage(function (MimeEmail $message) use ($bareId, $isReply, &$inReplyTo, &$references) {
            $message->getHeaders()->addIdHeader('Message-ID', $bareId($this->email->message_id));

            if ($isReply) {
                $inReplyTo = $bareId($this->email->parent->message_id);
                $message->getHeaders()->addIdHeader('In-Reply-To', $inReplyTo);

                $refIds = $this->email->parent->reference_ids ?? [];
                if (! in_array($this->email->parent->message_id, $refIds)
                    && ! in_array($inReplyTo, $refIds)) {
                    $refIds[] = $inReplyTo;
                }

                $references = implode(' ', array_map(
                    fn ($id) => '<'.$bareId($id).'>',
                    $refIds
                ));
                $message->getHeaders()->addTextHeader('References', $references);
            }
        });

        foreach ($this->email->attachments as $attachment) {
            $this->attachFromStorage($attachment->path);
        }

        Log::info('Outgoing mail threading debug', [
            'email_id'       => $this->email->id,
            'is_reply'       => $isReply,
            'subject'        => $subject,
            'message_id'     => $this->email->message_id,
            'in_reply_to'    => $inReplyTo,
            'references'     => $references,
            'from'           => $emailAddress ?? config('mail.from.address'),
            'to'             => $this->email->reply_to,
        ]);

        return $this;
    }

    private function buildSubjectLine(): string
    {
        if (! $this->email->parent_id) {
            return $this->email->subject;
        }

        $parentSubject = $this->email->parent->subject;

        if (preg_match('/^Re:\s/i', $parentSubject)) {
            return $parentSubject;
        }

        return 'Re: '.$parentSubject;
    }
}
