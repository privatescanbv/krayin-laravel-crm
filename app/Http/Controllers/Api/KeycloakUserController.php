<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Webkul\Contact\Models\Person;

class KeycloakUserController extends Controller
{
    /**
     * Haal person id op op basis van Keycloak user id.
     *
     * Deze endpoint accepteert **geen** request body (geen JSON). Gebruik alleen de `keycloakUserId` in de URL.
     *
     * @group Keycloak
     *
     * @urlParam keycloakUserId string required De Keycloak user ID (UUID). Example: 11111111-2222-3333-4444-555555555555
     *
     * @response 200 {
     *  "success": true,
     *  "data": {
     *    "person_id": 123,
     *    "user_id": 456,
     *    "keycloak_user_id": "11111111-2222-3333-4444-555555555555",
     *    "is_active": true
     *  }
     * }
     *
     * @response 404 {
     *  "success": false,
     *  "message": "Geen persoon gevonden voor opgegeven Keycloak user id."
     * }
     */
    public function findPersonByKeycloakId(Request $request, string $keycloakUserId): JsonResponse
    {
        $person = Person::where('keycloak_user_id', $keycloakUserId)->first();

        if (is_null($person)) {
            Log::warning('No person found by keycloak user id.', [
                'keycloak_user_id' => $keycloakUserId,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Geen persoon gevonden voor opgegeven Keycloak user id.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'person_id'        => $person->id,
                'user_id'          => $person->user_id,
                'keycloak_user_id' => $person->keycloak_user_id,
                'is_active'        => (bool) $person->is_active,
            ],
        ]);
    }
}
