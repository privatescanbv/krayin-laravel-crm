<?php

namespace App\Http\Controllers\Api;

use App\Actions\Clinic\SendClinicForgotPasswordMailAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Webkul\User\Models\User;

/**
 * Profile of the logged-in clinic-portal user (a CRM user with a clinic-portal role, not a patient).
 * A Keycloak bearer token must belong to {keycloakUserId} (patient.self middleware); X-API-KEY callers are service-to-service.
 * The e-mail address is the Keycloak login name and cannot be changed here.
 */
class ClinicProfileController extends Controller
{
    /**
     * Get the profile of a clinic user.
     *
     * @group Clinic profile
     *
     * @urlParam keycloakUserId string required The Keycloak user ID of the clinic user. Example: 3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d
     *
     * @response 200 scenario="Success" {"first_name":"Piet","last_name":"Jansen","name":"Piet Jansen","email":"piet@kliniek.nl"}
     * @response 404 scenario="No clinic user for this Keycloak user id" {"message":"Not Found"}
     */
    public function show(string $keycloakUserId): JsonResponse
    {
        $user = $this->clinicUser($keycloakUserId);

        return response()->json([
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'name'       => trim($user->first_name.' '.$user->last_name),
            'email'      => $user->email,
        ]);
    }

    /**
     * Update the name of a clinic user.
     *
     * @group Clinic profile
     *
     * @urlParam keycloakUserId string required The Keycloak user ID of the clinic user.
     *
     * @bodyParam first_name string required Example: Piet
     * @bodyParam last_name string required Example: Jansen
     *
     * @response 204 scenario="Success"
     * @response 404 scenario="No clinic user for this Keycloak user id"
     * @response 422 scenario="Validation error"
     */
    public function update(Request $request, string $keycloakUserId): Response
    {
        $user = $this->clinicUser($keycloakUserId);

        $user->update($request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['required', 'string', 'max:255'],
        ]));

        return response()->noContent();
    }

    /**
     * Change the password of a clinic user (UserObserver writes it to Keycloak).
     *
     * With a bearer token the portal has already verified the current password against Keycloak;
     * an X-API-KEY caller must send it.
     *
     * @group Clinic profile
     *
     * @urlParam keycloakUserId string required The Keycloak user ID of the clinic user.
     *
     * @bodyParam current_password string The current password (required without a bearer token). Example: OudWachtwoord1!
     * @bodyParam password string required Example: NieuwWachtwoord1!
     * @bodyParam password_confirmation string required Example: NieuwWachtwoord1!
     *
     * @response 204 scenario="Success"
     * @response 404 scenario="No clinic user for this Keycloak user id"
     * @response 422 scenario="Validation error" {"message":"The given data was invalid.","errors":{"current_password":["Het huidige wachtwoord is onjuist."]}}
     */
    public function updatePassword(Request $request, string $keycloakUserId): Response
    {
        $user = $this->clinicUser($keycloakUserId);

        $usingBearerToken = str_starts_with((string) $request->header('Authorization', ''), 'Bearer ');

        $validated = $request->validate([
            'current_password'      => [$usingBearerToken ? 'nullable' : 'required', 'string'],
            'password'              => ['required', 'string', Password::defaults(), 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        if (! $usingBearerToken && ! Hash::check($validated['current_password'], (string) $user->password)) {
            abort(response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => ['current_password' => ['Het huidige wachtwoord is onjuist.']],
            ], 422));
        }

        $user->password = $validated['password'];
        $user->save();

        return response()->noContent();
    }

    private function clinicUser(string $keycloakUserId): User
    {
        return SendClinicForgotPasswordMailAction::clinicUsers()
            ->where('keycloak_user_id', $keycloakUserId)
            ->firstOrFail();
    }
}
