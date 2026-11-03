<?php

namespace App\Actions\Clinic;

use App\Enums\EmailTemplateCode;
use App\Enums\KeycloakRoles;
use App\Enums\RoleCode;
use App\Mail\ClinicForgotPasswordMail;
use App\Services\ClinicPortal\ClinicPortalPasswordResetClient;
use App\Services\Mail\CrmMailService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use Webkul\User\Models\User;

class SendClinicForgotPasswordMailAction
{
    public function __construct(
        private readonly CrmMailService $crmMailService,
        private readonly ClinicPortalPasswordResetClient $clinicPortal,
    ) {}

    /**
     * Active CRM users with a Keycloak account and a role that logs in to the clinic portal.
     *
     * @return Builder<User>
     */
    public static function clinicUsers(): Builder
    {
        $clinicRoleCodes = collect(RoleCode::cases())
            ->filter(fn (RoleCode $code) => $code->keycloakRole() === KeycloakRoles::Clinic)
            ->map(fn (RoleCode $code) => $code->value)
            ->all();

        return User::query()
            ->where('status', 1)
            ->whereNotNull('keycloak_user_id')
            ->where('keycloak_user_id', '!=', '')
            ->whereHas('role', fn (Builder $query) => $query->whereIn('code', $clinicRoleCodes));
    }

    /**
     * Mail a clinic-portal reset link to an active clinic user (CRM user with a clinic-portal role).
     *
     * Silently no-ops for unknown addresses; the caller always returns the same message (no enumeration).
     */
    public function execute(string $email): void
    {
        $user = self::clinicUsers()->where('email', $email)->first();

        if (! $user) {
            Log::info('Clinic forgot-password requested for unknown / non-clinic email', ['email' => $email]);

            return;
        }

        $resetUrl = $this->clinicPortal->createResetLink($user->email, $user->keycloak_user_id);

        if ($resetUrl === null) {
            return;
        }

        try {
            $rendered = $this->crmMailService->renderTemplate(EmailTemplateCode::CLINIC_FORGOT_PASSWORD, [
                'user'      => $user,
                'reset_url' => $resetUrl,
            ]);

            Mail::to($user->email)->send(new ClinicForgotPasswordMail($rendered['subject'], $rendered['html']));
        } catch (Throwable $e) {
            Log::error('Clinic forgot-password mail failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return;
        }

        Log::info('Clinic forgot-password mail sent', ['user_id' => $user->id]);
    }
}
