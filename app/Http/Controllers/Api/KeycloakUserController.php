<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\Contact\Models\Person;

class KeycloakUserController extends Controller
{
    /**
     * Haal person id op op basis van Keycloak user id.
     *
     * GET /api/keycloak/persons/{keycloakUserId}
     */
    public function findPersonByKeycloakId(Request $request, string $keycloakUserId): JsonResponse
    {
        $person = Person::where('keycloak_user_id', $keycloakUserId)->first();

        if (! $person) {
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
