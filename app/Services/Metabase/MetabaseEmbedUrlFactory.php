<?php

namespace App\Services\Metabase;

/**
 * Builds a Metabase static-embed URL (signed HS256 JWT) for a dashboard.
 */
class MetabaseEmbedUrlFactory
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function forDashboard(int $dashboardId, array $params = [], ?int $expiresAt = null): string
    {
        $siteUrl = rtrim((string) config('services.metabase.embed.site_url'), '/');
        $secret = (string) config('services.metabase.embed.secret');
        $ttl = (int) config('services.metabase.embed.ttl', 600);

        if ($siteUrl === '') {
            throw new MetabaseEmbedException('METABASE_EMBED_URL is not configured.');
        }

        if ($secret === '') {
            throw new MetabaseEmbedException('METABASE_EMBEDDING_SECRET_KEY is not configured.');
        }

        if ($dashboardId < 1) {
            throw new MetabaseEmbedException('Metabase dashboard id must be a positive integer.');
        }

        $filtered = [];

        foreach ($params as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $filtered[(string) $name] = $value;
        }

        $payload = [
            'resource' => ['dashboard' => $dashboardId],
            'params'   => $filtered === [] ? new \stdClass : $filtered,
            'exp'      => $expiresAt ?? (time() + max($ttl, 60)),
        ];

        $token = $this->encode($payload, $secret);

        return $siteUrl.'/embed/dashboard/'.$token.'#bordered=false&titled=true';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encode(array $payload, string $secret): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $body = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $signature = $this->base64UrlEncode(hash_hmac('sha256', $header.'.'.$body, $secret, true));

        return $header.'.'.$body.'.'.$signature;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
