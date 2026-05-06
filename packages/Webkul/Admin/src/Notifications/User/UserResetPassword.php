<?php

namespace Webkul\Admin\Notifications\User;

use App\Enums\EmailTemplateCode;
use App\Services\Mail\CrmMailService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class UserResetPassword extends ResetPassword
{
    /**
     * Build the mail representation of the notification.
     *
     * Renders the DB-backed `crm-forgot-password` email template (managed via
     * Settings → Email Templates) instead of the hard-coded Blade view, so
     * admins can adjust the wording without a code change.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        if (static::$toMailCallback) {
            return call_user_func(static::$toMailCallback, $notifiable, $this->token);
        }

        $resetUrl = route('admin.reset_password.create', $this->token);

        $rendered = app(CrmMailService::class)->renderTemplate(
            EmailTemplateCode::CRM_FORGOT_PASSWORD,
            [
                'user'      => $notifiable,
                'reset_url' => $resetUrl,
            ],
        );

        return (new MailMessage)
            ->subject($rendered['subject'])
            ->view('adminc.emails.raw-html', [
                'html' => $rendered['html'],
            ]);
    }
}
