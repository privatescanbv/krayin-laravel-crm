<?php

namespace App\Http\Controllers\Admin;

use App\Services\FormService;
use App\Services\ImpersonationService;
use App\Services\KeycloakTokenExchangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
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

        if (session('impersonating')) {
            abort(422, 'U simuleert al een sessie. Stop de huidige simulatie eerst.');
        }

        $token = $this->keycloakTokenExchange->impersonate($person->keycloak_user_id);

        session(['impersonating' => [
            'person_id'        => $person->id,
            'person_name'      => $person->name,
            'keycloak_user_id' => $person->keycloak_user_id,
            'started_at'       => now()->toISOString(),
        ]]);

        $this->impersonationService->logActivity($person, 'start', request()->ip());

        $portalUrl = rtrim(config('services.portal.patient.web_url'), '/')
            .'/auth/keycloak?token='.urlencode($token);

        return redirect()->away($portalUrl);
    }

    /**
     * Stop impersonation: log out the patient's Keycloak session and clear local state.
     */
    public function stop(): RedirectResponse
    {
        $data = session('impersonating');

        if ($data) {
            $this->impersonationService->stopImpersonation($data['keycloak_user_id']);
            $personId = $data['person_id'];
            $person = Person::find($data['person_id']);
            if ($person) {
                $this->impersonationService->logActivity($person, 'stop', request()->ip());
                $this->resetSessionPortal($personId);
            } else {
                Log::error('Could not stop impersonation,person not found by ID '.$personId);
            }

            session()->forget('impersonating');
        }

        return redirect()->route('admin.contacts.persons.index');
    }

    private function resetSessionPortal($crmPersonId)
    {

        // Portal session invalidation
        try {
            //            $portalUrl = rtrim(config('services.portal.patient.web_url'), '/')
            //                . '/api/sessions/invalidate';

            //            Log::info('Calling '.$portalUrl, ['crm_person_id' => $crmPersonId]);

            $this->formService->removeSessionForPerson($crmPersonId);

            //            $response = Http::timeout(5)
            //                ->withHeaders([
            //                    'X-API-Key' =>  config('services.portal.patient.api_token'),
            //                    'Accept'    => 'application/json',
            //                ])
            //                ->post($portalUrl, [
            //                    'crm_person_id' => $crmPersonId,
            //                ]);
            //            Log::info('Response '.$portalUrl, ['status' => $response->status()]);
        } catch (Throwable $e) {
            Log::errorat('Failed to invalidate patient portal session', [
                'user_id' => $data['keycloak_user_id'] ?? null,
                'error'   => $e->getMessage(),
            ]);
        }

        session()->forget('impersonating');
    }
}
