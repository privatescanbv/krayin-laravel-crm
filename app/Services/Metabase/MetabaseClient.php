<?php

namespace App\Services\Metabase;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around one Metabase instance's HTTP API. Authenticates with an
 * API key ("x-api-key" header). One instance == one environment.
 */
class MetabaseClient
{
    private string $baseUrl;

    public function __construct(
        string $baseUrl,
        private readonly string $apiKey,
        public readonly string $label,
        private readonly int $timeout = 30,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /* ---- Dashboards -------------------------------------------------------- */

    public function getDashboard(int $id): array
    {
        return $this->get("/dashboard/{$id}");
    }

    public function createDashboard(array $payload): array
    {
        return $this->post('/dashboard', $payload);
    }

    public function updateDashboard(int $id, array $payload): array
    {
        return $this->put("/dashboard/{$id}", $payload);
    }

    /* ---- Cards ----------------------------------------------------------- */

    public function getCard(int $id): array
    {
        return $this->get("/card/{$id}");
    }

    public function listCards(): array
    {
        return $this->get('/card');
    }

    public function createCard(array $payload): array
    {
        return $this->post('/card', $payload);
    }

    public function updateCard(int $id, array $payload): array
    {
        return $this->put("/card/{$id}", $payload);
    }

    /* ---- Metadata ------------------------------------------------------- */

    public function listDatabases(): array
    {
        $response = $this->get('/database');

        // Newer Metabase wraps the list in {"data": [...], "total": N}.
        return $response['data'] ?? $response;
    }

    public function getDatabaseMetadata(int $id): array
    {
        return $this->get("/database/{$id}/metadata");
    }

    public function getTable(int $id): array
    {
        return $this->get("/table/{$id}");
    }

    public function getTableQueryMetadata(int $id): array
    {
        return $this->get("/table/{$id}/query_metadata");
    }

    public function getField(int $id): array
    {
        return $this->get("/field/{$id}");
    }

    /** Metabase version string, e.g. "v0.49.6" (empty when unavailable). */
    public function version(): string
    {
        $props = $this->get('/session/properties');

        return (string) ($props['version']['tag'] ?? '');
    }

    /* ---- HTTP ----------------------------------------------------------- */

    private function get(string $path): array
    {
        return $this->send('get', $path);
    }

    private function post(string $path, array $payload): array
    {
        return $this->send('post', $path, $payload);
    }

    private function put(string $path, array $payload): array
    {
        return $this->send('put', $path, $payload);
    }

    private function send(string $method, string $path, ?array $payload = null): array
    {
        try {
            $response = $this->request()->{$method}($path, $payload ?? []);
        } catch (ConnectionException $e) {
            throw MetabaseApiException::connection($this->label, $method, $path, $e);
        }

        if ($response->failed()) {
            throw MetabaseApiException::fromResponse($this->label, $method, $path, $response->status(), $response->body());
        }

        return $response->json() ?? [];
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl.'/api')
            ->withHeaders(['x-api-key' => $this->apiKey])
            ->timeout($this->timeout)
            ->acceptJson()
            ->asJson();
    }
}
