<?php

namespace App\Services\Metabase;

use stdClass;

/**
 * Turns static embedding on in a Metabase instance for the CRM dashboard pages.
 */
class MetabaseEmbeddingService
{
    public function __construct(
        private readonly MetabaseDashboardRegistry $registry,
    ) {}

    /**
     * Enable global static embedding and per-dashboard embedding.
     *
     * @return list<string> Human-readable status lines.
     */
    public function enable(MetabaseClient $client): array
    {
        $lines = $this->enableGlobalEmbedding($client);

        foreach ($this->registry->pages() as $page) {
            $lines[] = $this->enableDashboardEmbedding($client, $page);
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    public function enableGlobalEmbedding(MetabaseClient $client): array
    {
        $settings = ['enable-embedding-simple', 'enable-embedding-static', 'enable-embedding'];
        $lines = [];
        $enabled = false;

        foreach ($settings as $setting) {
            try {
                $client->putSetting($setting, true);
                $lines[] = "Enabled {$setting}.";
                $enabled = true;
            } catch (MetabaseApiException $e) {
                if ($e->getCode() === 404) {
                    continue;
                }

                throw $e;
            }
        }

        if (! $enabled) {
            throw new MetabaseApiException(
                'Metabase ['.$client->label.'] could not enable static embedding (tried: '.implode(', ', $settings).').'
            );
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $page
     */
    public function enableDashboardEmbedding(MetabaseClient $client, array $page): string
    {
        $id = (int) $page['dashboard_id'];
        $dashboard = $client->getDashboard($id);

        $embeddingParams = [];

        foreach ($dashboard['parameters'] ?? [] as $parameter) {
            $slug = $parameter['slug'] ?? null;

            if (is_string($slug) && $slug !== '') {
                $embeddingParams[$slug] = 'enabled';
            }
        }

        $client->updateDashboard($id, [
            'enable_embedding' => true,
            'embedding_params' => $embeddingParams === [] ? new stdClass : $embeddingParams,
        ]);

        return sprintf(
            'Enabled embedding for dashboard %d (%s)%s.',
            $id,
            $page['name'],
            $embeddingParams === [] ? '' : ' with filters: '.implode(', ', array_keys($embeddingParams))
        );
    }

    public function embeddingSecret(MetabaseClient $client): string
    {
        $props = $client->sessionProperties();
        $fromSession = $props['embedding-secret-key'] ?? null;

        if (is_string($fromSession) && $fromSession !== '') {
            return $fromSession;
        }

        try {
            $fromSetting = $client->getSetting('embedding-secret-key');
        } catch (MetabaseApiException) {
            return '';
        }

        return is_string($fromSetting) ? $fromSetting : '';
    }
}
