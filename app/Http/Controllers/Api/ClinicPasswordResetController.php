<?php

namespace App\Http\Controllers\Api;

use App\Actions\Clinic\SendClinicForgotPasswordMailAction;
use App\Http\Controllers\Controller;
use App\Services\ClinicPortal\ClinicPortalPasswordResetClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rules\Password;

/**
 * Clinic-portal forgot-password flow (X-API-KEY, called by the clinic portal). Same set-up as the patient flow,
 * but for CRM users with a clinic-portal role: UserObserver writes the new password to Keycloak.
 */
class ClinicPasswordResetController extends Controller
{
    /**
     * Trigger a clinic password-reset e-mail. Same answer for unknown addresses (no enumeration).
     */
    public function store(Request $request, SendClinicForgotPasswordMailAction $sendMail): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $sendMail->execute($validated['email']);

        return response()->json([
            'message' => 'Als dit e-mailadres bij ons bekend is, hebt u een e-mail ontvangen met instructies om uw wachtwoord te resetten.',
        ]);
    }

    /**
     * Set a new password after the clinic portal confirms the reset token.
     *
     * @response 204 scenario="Success"
     * @response 403 scenario="Reset token not valid"
     * @response 404 scenario="No clinic user for this Keycloak user id"
     */
    public function reset(Request $request, string $keycloakUserId, ClinicPortalPasswordResetClient $clinicPortal): Response
    {
        // Service-to-service only: api.key also accepts a Keycloak Bearer token.
        if (str_starts_with((string) $request->header('Authorization', ''), 'Bearer ')) {
            abort(403);
        }

        $validated = $request->validate([
            'email'                 => ['required', 'email'],
            'reset_token'           => ['required', 'string'],
            'password'              => ['required', 'string', Password::defaults(), 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        if (! $clinicPortal->verify($validated['email'], $validated['reset_token'], $keycloakUserId)) {
            abort(403);
        }

        $user = SendClinicForgotPasswordMailAction::clinicUsers()
            ->where('keycloak_user_id', $keycloakUserId)
            ->first();

        if (! $user) {
            abort(404);
        }

        $user->password = $validated['password'];
        $user->save();

        return response()->noContent();
    }
}
