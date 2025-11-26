<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KeycloakWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {

        Log::info('Keycloak webhook received', [
            'event_type' => $request->input('type'),
            'payload'    => $request->all(),
            'headers'    => [
                'signature' => $request->header('X-Keycloak-Signature'),
                'kid'       => $request->header('X-Keycloak-Kid'),
            ],
        ]);

        return response()->json(['status' => 'ok']);
    }
}
