<?php

namespace App\Contracts\Api;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface ApiHttpTrafficLogger
{
    /**
     * @param  float  $durationMs  Wall time in milliseconds (monotonic preferred from middleware).
     */
    public function log(Request $request, Response $response, float $durationMs): void;
}
