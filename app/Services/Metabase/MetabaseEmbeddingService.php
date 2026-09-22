<?php

namespace App\Services\Metabase;

use stdClass;

/**
 * Publishes CRM dashboard pages as Metabase guest embeds.
 *
 * Metabase requires enable_embedding per dashboard (Share → Embed → Publish).
 * The CRM HTML snippet is shared; only this publish step is per dashboard.
 */
class MetabaseEmbeddingService
{
    public function __construct(
        private readonly MetabaseDashboardRegistry $registry,
    ) {}

    /**
     * Enable guest embedding globally and publish each configured dashboard.
     *
     * @return list<string> Human-readable status lines.
     */
    public function enable(MetabaseClient $client): array
    {
        $lines = $this->enableGlobalEmbedding($client);

        foreach ($this->registry->pages() as $page) {
            $lines[] = $this->enableDashboardEmbedding($client, $page);
        }

        foreach ($this->otherDashboards($client) as $dashboard) {
            $lines[] = sprintf(
                'Metabase dashboard %d (%s) is not a CRM page yet — add dashboard_id => %d to config/metabase_dashboards.php and re-run this command to publish it.',
                (int) $dashboard['id'],
                (string) ($dashboard['name'] ?? 'untitled'),
                (int) $dashboard['id'],
            );
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
                'Metabase ['.$client->label.'] could not enable guest embedding (tried: '.implode(', ', $settings).').'
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
        $embeddingParams = $this->embeddingParamsFor($page, $dashboard);

        if ($this->alreadyPublished($dashboard, $embeddingParams)) {
            return sprintf(
                'Dashboard %d (%s) is already published as a guest embed%s.',
                $id,
                $page['name'],
                $embeddingParams === [] ? '' : ' with filters: '.implode(', ', array_keys($embeddingParams))
            );
        }

        $client->updateDashboard($id, [
            'enable_embedding' => true,
            'embedding_params' => $embeddingParams === [] ? new stdClass : $embeddingParams,
        ]);

        return sprintf(
            'Published guest embed for dashboard %d (%s)%s.',
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

    /**
     * Dashboards that exist in Metabase but are not registered as CRM pages.
     *
     * @return list<array<string, mixed>>
     */
    public function otherDashboards(MetabaseClient $client): array
    {
        $knownIds = array_map(
            fn (array $page): int => (int) $page['dashboard_id'],
            $this->registry->pages()
        );

        try {
            $listed = $client->listDashboards();
        } catch (MetabaseApiException) {
            return [];
        }

        $others = [];

        foreach ($listed as $dashboard) {
            $id = (int) ($dashboard['id'] ?? 0);

            if ($id > 0 && ! in_array($id, $knownIds, true)) {
                $others[] = $dashboard;
            }
        }

        return $others;
    }

    /**
     * Config embedding_params win per slug; any extra Metabase filter is enabled.
     *
     * @param  array<string, mixed>  $page
     * @param  array<string, mixed>  $dashboard
     * @return array<string, string>
     */
    private function embeddingParamsFor(array $page, array $dashboard): array
    {
        $params = [];

        foreach ($dashboard['parameters'] ?? [] as $parameter) {
            if (! is_array($parameter)) {
                continue;
            }

            $slug = $parameter['slug'] ?? null;

            if (is_string($slug) && $slug !== '') {
                $params[$slug] = 'enabled';
            }
        }

        foreach ($page['embedding_params'] ?? [] as $slug => $state) {
            $params[(string) $slug] = (string) $state;
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @param  array<string, string>  $embeddingParams
     */
    private function alreadyPublished(array $dashboard, array $embeddingParams): bool
    {
        if (($dashboard['enable_embedding'] ?? false) !== true) {
            return false;
        }

        $current = $dashboard['embedding_params'] ?? [];

        if (! is_array($current)) {
            return $embeddingParams === [];
        }

        ksort($current);
        $expected = $embeddingParams;
        ksort($expected);

        return $current === $expected;
    }
}
