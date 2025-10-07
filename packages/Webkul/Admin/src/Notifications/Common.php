<?php

namespace Webkul\Admin\Notifications;

use Illuminate\Mail\Mailable;

class Common extends Mailable
{
    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(public $data) {}

    /**
     * Build the mail representation of the notification.
     */
    public function build()
    {
        // Set from address if provided in data
        if (isset($this->data['from'])) {
            if (is_array($this->data['from'])) {
                $email = array_key_first($this->data['from']);
                $name = $this->data['from'][$email];
                $this->from($email, $name);
            } else {
                $this->from($this->data['from']);
            }
        }
        // Otherwise, the Transport will set the from address dynamically

        $message = $this
            ->to($this->data['to'])
            ->subject($this->data['subject'])
            ->view('admin::emails.common.index', [
                'body' => $this->data['body'],
            ]);

        if (isset($this->data['attachments'])) {
            foreach ($this->data['attachments'] as $attachment) {
                $message->attachData($attachment['content'], $attachment['name'], [
                    'mime' => $attachment['mime'],
                ]);
            }
        }

        return $message;
    }
}
