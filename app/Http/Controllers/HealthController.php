<?php

namespace App\Http\Controllers;

use App\Services\HealthChecker;
use Illuminate\Http\JsonResponse;

/**
 * Health API contract v1 (.mb-foundation/skills/health-check-api). Routes staan buiten de web-groep
 * (bootstrap/app.php): geen sessie of cookies. Toegang: zie HealthGuard.
 *
 * - live:   proces antwoordt; raakt geen database. Voor de Docker-healthcheck.
 * - ready:  vereiste afhankelijkheden (database). Alleen vanuit het interne netwerk.
 * - report: alle checks, met token. Voor de orchestrator.
 */
class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return $this->json(['schema_version' => '1', 'status' => 'pass']);
    }

    public function ready(HealthChecker $health): JsonResponse
    {
        $ready = $health->summary($health->collect(requiredOnly: true))['ready'];

        return $this->json(['schema_version' => '1', 'status' => $ready], $ready === 'pass' ? 200 : 503);
    }

    public function report(HealthChecker $health): JsonResponse
    {
        $report = $health->report();

        return $this->json($report, $health->summary($report['checks'])['report_http_status']);
    }

    private function json(array $body, int $status = 200): JsonResponse
    {
        return response()->json($body, $status, ['Cache-Control' => 'no-store']);
    }
}
