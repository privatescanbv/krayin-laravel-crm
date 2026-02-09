<?php

namespace Webkul\Admin\Notifications\User;

use App\Enums\EmailTemplateCode;
use App\Services\Mail\EmailTemplateRenderingService;
use Illuminate\Mail\Mailable;
use Webkul\User\Models\User;

class Create extends Mailable
{
    public function __construct(public User $user) {}

    /**
     * Build the mail representation of the notification.
     */
    public function build()
    {
        $renderingService = app(EmailTemplateRenderingService::class);
        $rendered = $renderingService->renderForEntities(
            EmailTemplateCode::CREATE_USER,
            ['user' => $this->user]
        );

        return $this
            ->to($this->user->email)
            ->subject($rendered['subject'])
            ->html($rendered['html']);
    }
}
