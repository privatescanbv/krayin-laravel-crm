<?php

namespace App\Http\Controllers\Web;

use App\Actions\Patient\SendForgotPasswordMailAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Webkul\Contact\Models\Person;

/**
 * Patient forgot-password flow (web entry points).
 *
 * The reset flow is owned by the patient portal; the CRM handles mail dispatch and
 * the final password write to Keycloak. Web routes here are thin redirect shims so
 * that old Keycloak login.ftl links still work without a Keycloak reconfiguration.
 *
 * Flow:
 *  1. GET  /patient/forgot-password        → redirect to portal /forgot-password
 *  2. Portal form submits to CRM API       → POST /api/patient/forgot-password
 *  3. CRM calls portal API for signed URL, sends mail via {@see SendForgotPasswordMailAction}
 *  4. Patient clicks portal link           → portal verifies its own token, shows reset form
 *  5. Portal submits to CRM API            → POST /api/patient/{id}/password/reset
 *  6. CRM resets password on Person model; PersonObserver propagates to Keycloak.
 *
 * The web store() and reset() methods below remain registered (signed middleware still
 * guards reset()) to handle any in-flight links from before the portal migration.
 */
class PatientForgotPasswordController extends Controller
{
    public function __construct(
        private readonly SendForgotPasswordMailAction $sendForgotPasswordMail,
    ) {}

    public function create(): RedirectResponse
    {
        return redirect()->away(
            rtrim((string) config('services.portal.patient.web_url'), '/').'/forgot-password'
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $this->sendForgotPasswordMail->execute($validated['email']);

        return redirect()
            ->route('patient.forgot-password.create')
            ->with('success', 'Als dit e-mailadres bij ons bekend is, hebt u een e-mail ontvangen met instructies om uw wachtwoord te resetten.');
    }

    public function showResetForm(Request $request): RedirectResponse
    {
        $portalBase = rtrim((string) config('services.portal.patient.web_url'), '/');
        $query = $request->getQueryString();

        return redirect()->away($portalBase.'/reset-password'.($query ? '?'.$query : ''));
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
