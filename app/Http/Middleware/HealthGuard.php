<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Toegang tot /health/ready en /health/report, vóór er een check draait.
 *   health.guard:probe  rechtstreeks intern verkeer (geen proxy-headers, bron in HEALTH_PROBE_CIDRS)
 *   health.guard:report dedicated bearer-token (HEALTH_REPORT_TOKEN)
 *
 * Verkeer via Traefik krijgt altijd X-Forwarded-For en is dus nooit 'intern': die header kan een
 * client niet weghalen, in tegenstelling tot een vervalst IP-adres. Rate limit in de file-cache,
 * zodat limiteren niet van de database afhangt die we bewaken.
 */
class HealthGuard
{
    public function handle(Request $request, Closure $next, string $mode)
    {
        $limiter = new RateLimiter(Cache::store('file'));
        $key = "health:$mode";

        if ($limiter->tooManyAttempts($key, config('health.max_requests_per_minute'))) {
            return $this->deny(429);
        }
        $limiter->hit($key, 60);

        if ($request->query->count() > 0 || $request->getContent() !== '') {
            return $this->deny(400);
        }

        return $this->allowed($request, $mode) ? $next($request) : $this->deny($mode === 'report' ? 401 : 403);
    }

    private function allowed(Request $request, string $mode): bool
    {
        if ($mode === 'report') {
            $token = config('health.token');
            $given = $request->bearerToken();

            return is_string($token) && strlen($token) >= 32 && is_string($given) && hash_equals($token, $given);
        }

        // probe_allow_proxied: alleen aan op een omgeving waar Traefik /health/ready via een eigen
        // ipallowlist-router doorlaat. De bron-IP-check gebeurt dan in Traefik; hier blijft REMOTE_ADDR
        // (Traefik/intern netwerk) verplicht.
        $proxied = $request->headers->has('X-Forwarded-For')
            || $request->headers->has('Forwarded')
            || $request->headers->has('X-Real-Ip');

        return (! $proxied || config('health.probe_allow_proxied'))
            && IpUtils::checkIp((string) $request->server->get('REMOTE_ADDR'), config('health.probe_cidrs'));
    }

    private function deny(int $status)
    {
        $headers = ['Cache-Control' => 'no-store'] + ($status === 401 ? ['WWW-Authenticate' => 'Bearer'] : []);

        return response()->json(['schema_version' => '1', 'status' => 'fail'], $status, $headers);
    }
}
