<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PostcodeApiService
{
    public function lookup(string $postcode, int $number): ?array
    {
        $apiToken = config('services.postcodeapi.token');
        $apiUrl = config('services.postcodeapi.url');
        if (is_null(config('services.postcodeapi.token'))) {
            Log::error('Could not execute address lookup, missing env key POSTCODEAPI_TOKEN');

            return response()->json(['error' => 'Internal Server Error'], 500);
        }
        if (str_contains($apiUrl, 'sandbox')) {
            // for testing purpose, sandbox doesn't support all values
            $postcode = '6545CA';
            $number = 29;
        }
        $response = Http::withHeaders([
            'X-Api-Key' => config('services.postcodeapi.token'),
        ])->get("{$apiUrl}{$postcode}/{$number}");

        if ($response->successful()) {
            return $response->json();
        }
        Log::error("Could not execute address lookup, http response {$response->status()}, body: ".$response->body());

        return null;
    }
}
