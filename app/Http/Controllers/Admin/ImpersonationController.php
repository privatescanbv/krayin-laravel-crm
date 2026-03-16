<?php

namespace App\Http\Controllers\Admin;

use App\Services\FormService;
use App\Services\ImpersonationService;
use App\Services\KeycloakTokenExchangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Contact\Models\Person;

class ImpersonationController extends Controller
{
    public function __construct(
        protected ImpersonationService $impersonationService,
        protected KeycloakTokenExchangeService $keycloakTokenExchange,
        private FormService $formService,
    ) {}

    /**
     * Start impersonating a patient: exchanges a token and redirects to the patient portal.
     */
    public function impersonate(Person $person): RedirectResponse
    {
        abort_if(! bouncer()->hasPermission('contacts.persons.impersonate'), 403);

        if (empty($person->keycloak_user_id)) {
            abort(422, 'Persoon heeft geen patiëntportaal account.');
        }

        $existing = session('impersonating');
        if ($existing && (int) ($existing['person_id'] ?? 0) !== (int) $person->id) {
            $this->stopCurrentImpersonation();
        }

        $token = $this->keycloakTokenExchange->impersonate($person->keycloak_user_id);

        session(['impersonating' => [
            'person_id'        => $person->id,
            'person_name'      => $person->name,
            'keycloak_user_id' => $person->keycloak_user_id,
            'started_at'       => now()->toISOString(),
        ]]);

        $this->impersonationService->logActivity($person, 'start', request());

        $portalUrl = rtrim(config('services.portal.patient.web_url'), '/')
            .'/auth/keycloak?token='.urlencode($token);

        return redirect()->away($portalUrl);
    }

    /**
     * Start impersonating a patient and redirect to a specific form URL in the patient portal.
     * Single-link flow: impersonate + redirect to GVL form.
     */
    public function impersonateAndOpenForm(Person $person, Request $request): RedirectResponse
    {
        abort_if(! bouncer()->hasPermission('contacts.persons.impersonate'), 403);

        if (empty($person->keycloak_user_id)) {
            abort(422, 'Persoon heeft geen patiëntportaal account.');
        }

        $existing = session('impersonating');
        if ($existing && (int) ($existing['person_id'] ?? 0) !== (int) $person->id) {
            $this->stopCurrentImpersonation();
        }

        $redirectInput = $request->query('redirect');
        if (empty($redirectInput)) {
            abort(400, 'Redirect parameter ontbreekt.');
        }

        $safePath = $this->extractAndValidateRedirectPath($redirectInput);
        if ($safePath === null) {
            abort(400, 'Ongeldige redirect URL. Alleen patiëntportaal URLs zijn toegestaan.');
        }

        $token = $this->keycloakTokenExchange->impersonate($person->keycloak_user_id);

        session(['impersonating' => [
            'person_id'        => $person->id,
            'person_name'      => $person->name,
            'keycloak_user_id' => $person->keycloak_user_id,
            'started_at'       => now()->toISOString(),
        ]]);

        $this->impersonationService->logActivity($person, 'start', request());

        $portalBase = rtrim(config('services.portal.patient.web_url'), '/');
        $portalUrl = $portalBase.'/auth/keycloak?token='.urlencode($token).'&redirect='.urlencode($safePath);

        return redirect()->away($portalUrl);
    }

    /**
     * Stop impersonation: log out the patient's Keycloak session and clear local state.
     */
    public function stop(): RedirectResponse
    {
        $this->stopCurrentImpersonation();

        return redirect()->route('admin.contacts.persons.index');
    }

    /**
     * Stop the current impersonation session: Keycloak logout, portal invalidation, log, clear session.
     */
    private function stopCurrentImpersonation(): void
    {
        $data = session('impersonating');
        if (! $data) {
            return;
        }

        $this->impersonationService->stopImpersonation($data['keycloak_user_id']);
        $person = Person::find($data['person_id']);
        if ($person) {
            $this->impersonationService->logActivity($person, 'stop', request());
            try {
                $this->formService->removeSessionForPerson((string) $data['person_id']);
            } catch (Throwable $e) {
                Log::error('Failed to invalidate patient portal session', [
                    'person_id' => $data['person_id'] ?? null,
                    'error'     => $e->getMessage(),
                ]);
            }
        } else {
            Log::error('Could not stop impersonation, person not found by ID '.($data['person_id'] ?? '?'));
        }

        session()->forget('impersonating');
    }

    /**
     * Extract and validate redirect path from gvlFormLink. Returns safe path or null.
     */
    private function extractAndValidateRedirectPath(string $redirectInput): ?string
    {
        $portalBase = rtrim(config('services.portal.patient.web_url'), '/');
        $portalHost = parse_url($portalBase, PHP_URL_HOST);

        // If it looks like a full URL
        if (preg_match('#^https?://#i', $redirectInput)) {
            $parsed = parse_url($redirectInput);
            $host = $parsed['host'] ?? null;
            $path = $parsed['path'] ?? '/';

            if ($host !== $portalHost) {
                return null;
            }

            return $path ?: '/';
        }

        // Relative path: must start with / and not contain protocol-like sequences
        if (str_starts_with($redirectInput, '/') && ! str_contains($redirectInput, '//')) {
            return $redirectInput;
        }

        return null;
    }
}
