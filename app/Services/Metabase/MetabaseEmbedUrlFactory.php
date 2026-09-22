<?php

namespace App\Services\Metabase;

use stdClass;

/**
 * Builds a Metabase guest-embed JWT (HS256), matching the token Metabase's
 * embed.js / <metabase-dashboard> web component expects.
 */
class MetabaseEmbedUrlFactory
{
    public function __construct(
        private readonly MetabaseEmbedSecretResolver $secrets = new MetabaseEmbedSecretResolver,
    ) {}

    public function instanceUrl(): string
    {
        $siteUrl = rtrim((string) config('services.metabase.embed.site_url'), '/');

        if ($siteUrl === '') {
            throw new MetabaseEmbedException('METABASE_EMBED_URL is not configured.');
        }

        return $siteUrl;
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $embeddingParams
     */
    public function token(int $dashboardId, array $params = [], array $embeddingParams = [], ?int $expiresAt = null): string
    {
        $secret = $this->secrets->get();
        $ttl = (int) config('services.metabase.embed.ttl', 600);

        if ($secret === '') {
            throw new MetabaseEmbedException('Metabase embedding secret is empty.');
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

        $exp = $expiresAt ?? (time() + max($ttl, 60));

        $payload = [
            'resource' => ['dashboard' => $dashboardId],
            'params'   => $filtered === [] ? new stdClass : $filtered,
            'iat'      => $exp - max($ttl, 60),
            'exp'      => $exp,
        ];

        if ($embeddingParams !== []) {
            $payload['_embedding_params'] = $embeddingParams;
        }

        return $this->encode($payload, $secret);
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $embeddingParams
     *
     * @deprecated Guest embeds use token() + embed.js instead of /embed/dashboard/{jwt}.
     */
    public function forDashboard(int $dashboardId, array $params = [], array $embeddingParams = [], ?int $expiresAt = null): string
    {
        return $this->instanceUrl().'/embed/dashboard/'.$this->token($dashboardId, $params, $embeddingParams, $expiresAt).'#bordered=false&titled=true';
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
