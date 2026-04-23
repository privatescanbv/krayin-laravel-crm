<?php

namespace App\Http\Middleware;

use App\Contracts\Api\ApiHttpTrafficLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogApiHttpTraffic
{
    public function __construct(
        private readonly ApiHttpTrafficLogger $logger,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $started = hrtime(true);

        $requestId = $request->headers->get('X-Request-Id');
        if (! is_string($requestId) || trim($requestId) === '') {
            $requestId = (string) Str::uuid();
        }
        $request->attributes->set('api_http_request_id', $requestId);

        /** @var Response $response */
        $response = $next($request);

        $durationMs = (hrtime(true) - $started) / 1_000_000;

        if (config('api-http-log.enabled', true)) {
            $this->logger->log($request, $response, $durationMs);
        }

        if (! $response->headers->has('X-Request-Id')) {
            $response->headers->set('X-Request-Id', $requestId);
        }

        return $response;
    }
}
