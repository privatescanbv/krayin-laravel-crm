<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PostcodeApiService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AddressLookupController extends Controller
{
    public function __construct(private readonly PostcodeApiService $postcodeApiService) {}

    public function addressLookup(Request $request)
    {
        $postcode = trim($request->query('postcode', ''));
        $huisnummer = trim($request->query('huisnummer', ''));

        // Validatie
        if (empty($postcode)) {
            return response()->json([
                'success' => false,
                'message' => 'Postcode is verplicht.',
            ], 400);
        }

        if (empty($huisnummer)) {
            return response()->json([
                'success' => false,
                'message' => 'Huisnummer is verplicht.',
            ], 400);
        }

        // Postcode validatie (Nederlandse postcode format: 1234 AB)
        $postcodeRegex = '/^[1-9][0-9]{3}\s?[A-Z]{2}$/i';
        if (! preg_match($postcodeRegex, $postcode)) {
            return response()->json([
                'success' => false,
                'message' => 'Voer een geldige Nederlandse postcode in (bijvoorbeeld: 1234 AB).',
            ], 400);
        }

        // Huisnummer validatie (moet een nummer zijn)
        if (! is_numeric($huisnummer)) {
            return response()->json([
                'success' => false,
                'message' => 'Voer een geldig huisnummer in (alleen cijfers).',
            ], 400);
        }

        try {
            $result = $this->postcodeApiService->lookup($postcode, (int) $huisnummer);

            if (! $result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Adres niet gevonden voor deze postcode en huisnummer.',
                ], 404);
            }

            // Controleer of we de benodigde velden hebben
            if (empty($result['street'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Straatnaam niet gevonden in de API response.',
                ], 404);
            }
            // Map de API response naar onze velden
            // De API gebruikt waarschijnlijk andere veldnamen
            $street = $result['street'] ?? $result['street_name'] ?? $result['road'] ?? '';
            $city = $result['city'] ?? $result['town'] ?? $result['municipality'] ?? '';
            $state = $result['province'] ?? $result['state'] ?? $result['region'] ?? '';

            return response()->json([
                'success' => true,
                'street'  => $street,
                'city'    => $city,
                'state'   => $state,
                'message' => 'Adres succesvol opgehaald.',
                'debug'   => $result, // Tijdelijk voor debugging
            ]);

        } catch (Exception $e) {
            Log::error('Postcode API error: '.$e->getMessage(), [
                'postcode'   => $postcode,
                'huisnummer' => $huisnummer,
                'exception'  => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Er is een fout opgetreden bij het opzoeken van het adres. Probeer het later opnieuw.',
            ], 500);
        }
    }
}
