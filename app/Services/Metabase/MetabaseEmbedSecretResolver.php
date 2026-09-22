<?php

namespace App\Services\Metabase;

use Illuminate\Support\Facades\Cache;

/**
 * Resolves the HMAC secret Metabase uses to verify static-embed JWTs.
 *
 * METABASE_EMBEDDING_SECRET_KEY is an optional override. Metabase API keys
 * (they start with "mb_") are not embedding secrets and are ignored.
 */
class MetabaseEmbedSecretResolver
{
    public function __construct(
        private readonly MetabaseEnvironments $environments = new MetabaseEnvironments,
        private readonly MetabaseEmbeddingService $embedding = new MetabaseEmbeddingService(new MetabaseDashboardRegistry),
    ) {}

    public function get(): string
    {
        $configured = trim((string) config('services.metabase.embed.secret'));

        if ($this->isUsableSecret($configured)) {
            return $configured;
        }

        return Cache::remember($this->cacheKey(), 300, function (): string {
            $client = $this->client();
            $secret = $this->embedding->embeddingSecret($client);

            if ($secret === '') {
                $this->embedding->enable($client);
                $secret = $this->embedding->embeddingSecret($client);
            }

            if ($secret === '') {
                throw new MetabaseEmbedException(
                    'Metabase has no embedding secret. Run `php artisan metabase:enable-embeds` and do not put a Metabase API key (mb_...) in METABASE_EMBEDDING_SECRET_KEY.'
                );
            }

            return $secret;
        });
    }

    private function isUsableSecret(string $secret): bool
    {
        return $secret !== '' && ! str_starts_with($secret, 'mb_');
    }

    private function client(): MetabaseClient
    {
        $preferred = (string) config('services.metabase.embed.environment', 'dev');

        try {
            return $this->environments->resolve($preferred);
        } catch (MetabaseSyncException $e) {
            $names = $this->environments->names();

            if ($names !== [] && $names[0] !== $preferred) {
                return $this->environments->resolve($names[0]);
            }

            throw new MetabaseEmbedException(
                'Cannot sign Metabase embeds: '.$e->getMessage().' Do not put a Metabase API key (mb_...) in METABASE_EMBEDDING_SECRET_KEY; the CRM fetches Metabase\'s embedding secret via the API after `php artisan metabase:enable-embeds`.'
            );
        }
    }

    private function cacheKey(): string
    {
        return 'metabase.embed.secret.'.(string) config('services.metabase.embed.environment', 'dev');
    }
}
