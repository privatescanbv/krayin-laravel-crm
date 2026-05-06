<?php

namespace App\Http\Controllers\Web;

use App\Enums\EmailTemplateCode;
use App\Http\Controllers\Controller;
use App\Services\Mail\CrmMailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Webkul\Contact\Models\Person;

/**
 * Patient forgot-password flow.
 *
 * The Keycloak login page (login.ftl) links to this controller's "request" form. The CRM
 * is the source of truth for sending the reset mail (DB-backed template + Microsoft Graph
 * mailer) and for updating the patient's password in Keycloak via the Admin API.
 *
 * Flow:
 *  1. Patient submits email on /patient/forgot-password
 *  2. We resolve the Person (silently no-op if not found, to avoid email enumeration)
 *  3. We send a temporarySignedRoute reset link via {@see CrmMailService::sendToPersonTemplate()}
 *  4. Patient clicks the link → /patient/reset-password (signed middleware verifies HMAC)
 *  5. Patient submits new password → we store it on the Person model; the PersonObserver
 *     propagates the change to Keycloak via {@see PersonKeycloakService::update()}.
 */
class PatientForgotPasswordController extends Controller
{
    public function __construct(
        private readonly CrmMailService $crmMailService,
    ) {}

    public function create(): View
    {
        return view('auth.patient-forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = $validated['email'];
        $person = $this->findPersonByEmail($email);

        if ($person && ! empty($person->keycloak_user_id)) {
            $resetUrl = URL::temporarySignedRoute(
                'patient.reset-password',
                now()->addHour(),
                ['email' => $email],
            );

            $sent = $this->crmMailService->sendToPersonTemplate(
                person: $person,
                templateIdentifier: EmailTemplateCode::PATIENT_FORGOT_PASSWORD,
                variables: [
                    'person'    => $person,
                    'reset_url' => $resetUrl,
                ],
                isNotify: false,
            );

            Log::info('Patient forgot-password mail dispatched', [
                'person_id' => $person->id,
                'sent'      => $sent,
            ]);
        } else {
            Log::info('Patient forgot-password requested for unknown / unlinked email', [
                'email'             => $email,
                'person_found'      => (bool) $person,
                'has_keycloak_link' => $person ? ! empty($person->keycloak_user_id) : false,
            ]);
        }

        return redirect()
            ->route('patient.forgot-password.create')
            ->with('success', 'Als dit e-mailadres bij ons bekend is, hebt u een e-mail ontvangen met instructies om uw wachtwoord te resetten.');
    }

    public function showResetForm(Request $request): View
    {
        return view('auth.patient-reset-password', [
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $email = (string) $request->query('email', '');

        if ($email === '') {
            return redirect()
                ->route('patient.forgot-password.create')
                ->withErrors(['password' => 'De resetlink is ongeldig. Vraag een nieuwe link aan.']);
        }

        $person = $this->findPersonByEmail($email);

        if (! $person || empty($person->keycloak_user_id)) {
            // Fail closed: signed link was valid but no portal account exists for this email.
            Log::warning('Patient reset-password: no portal-linked person found for signed link', [
                'email' => $email,
            ]);

            return redirect()
                ->route('patient.forgot-password.create')
                ->withErrors(['password' => 'Er kon geen account worden gevonden voor dit e-mailadres.']);
        }

        // Setting `password` triggers PersonObserver → PersonKeycloakService::update(),
        // which propagates the password change to Keycloak via the Admin API.
        $person->password = $validated['password'];
        $person->save();

        Log::info('Patient password reset via forgot-password flow', [
            'person_id' => $person->id,
        ]);

        $portalUrl = config('services.portal.patient.web_url');

        return redirect()
            ->away($portalUrl)
            ->with('success', 'Uw wachtwoord is gewijzigd. U kunt nu inloggen met uw nieuwe wachtwoord.');
    }

    /**
     * Look up a person by email address.
     *
     * Persons store their email addresses as a JSON array (`emails` column) of
     * `{value, label}` objects, so we use a LIKE match — same pattern that
     * AbstractEmailProcessor uses for inbound email matching.
     */
    private function findPersonByEmail(string $email): ?Person
    {
        return Person::where('emails', 'like', '%'.$email.'%')->first();
    }
}
