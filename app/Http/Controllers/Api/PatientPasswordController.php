<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Keycloak\KeycloakService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Webkul\User\Models\User;

class PatientPasswordController extends Controller
{
    public function __construct(private readonly KeycloakService $keycloakService) {}

    /**
     * Update the password for a patient.
     *
     * @group Patient Password
     *
     * @urlParam id string required The Keycloak user ID of the patient. Example: 3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d
     *
     * @bodyParam current_password string required The current password. Example: OudWachtwoord1!
     * @bodyParam password string required The new password (min 8 characters). Example: NieuwWachtwoord1!
     * @bodyParam password_confirmation string required Confirmation of the new password. Example: NieuwWachtwoord1!
     *
     * @response 204 scenario="Success"
     * @response 404 scenario="Patient not found" {"message":"Not Found"}
     * @response 422 scenario="Validation error" {"message":"The given data was invalid.","errors":{"current_password":["Het huidige wachtwoord is onjuist."]}}
     */
    public function update(Request $request, string $keycloakUserId): Response
    {
        [$person, $user] = $this->keycloakService->resolvePersonOrUser($keycloakUserId);

        if (is_null($person)) {
            abort(404);
        }

        $validated = $request->validate([
            'current_password'      => 'required|string',
            'password'              => 'required|string|min:7|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $currentDecrypted = $person->getDecryptedPassword();

        if ($currentDecrypted !== null && $validated['current_password'] !== $currentDecrypted) {
            abort(response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => [
                    'current_password' => ['Het huidige wachtwoord is onjuist.'],
                ],
            ], 422));
        }

        $person->password = $validated['password'];
        $person->save();

        $linkedUser = User::where('keycloak_user_id', $keycloakUserId)->first();

        if ($linkedUser) {
            $linkedUser->password = $validated['password'];
            $linkedUser->save();
        }

        return response()->noContent();
    }
}
