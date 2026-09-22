<?php

namespace App\Console\Commands;

use App\Services\Metabase\MetabaseApiException;
use App\Services\Metabase\MetabaseClient;
use App\Services\Metabase\MetabaseDashboardRegistry;
use App\Services\Metabase\MetabaseEnvironments;
use App\Services\Metabase\MetabaseSyncException;
use Illuminate\Console\Command;
use stdClass;

class EnableMetabaseEmbeds extends Command
{
    protected $signature = 'metabase:enable-embeds
        {--environment=dev : Metabase environment name from config/services.php}';

    protected $description = 'Zet static embedding aan in Metabase voor de CRM-dashboardpagina\'s uit config/metabase_dashboards.php.';

    public function handle(MetabaseEnvironments $environments, MetabaseDashboardRegistry $registry): int
    {
        $environment = (string) $this->option('environment');
        $pages = $registry->pages();

        if ($pages === []) {
            $this->error('No Metabase dashboards configured in config/metabase_dashboards.php.');

            return self::FAILURE;
        }

        try {
            $client = $environments->resolve($environment);
        } catch (MetabaseSyncException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line("Environment: {$client->label} ({$client->baseUrl()})");

        try {
            $this->enableGlobalEmbedding($client);
        } catch (MetabaseApiException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $failed = false;

        foreach ($pages as $page) {
            try {
                $this->enableDashboardEmbedding($client, $page);
            } catch (MetabaseApiException $e) {
                $this->error($e->getMessage());
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function enableGlobalEmbedding(MetabaseClient $client): void
    {
        $settings = ['enable-embedding-static', 'enable-embedding'];
        $enabled = false;
        $errors = [];

        foreach ($settings as $setting) {
            try {
                $client->putSetting($setting, true);
                $this->info("Enabled {$setting}.");
                $enabled = true;
            } catch (MetabaseApiException $e) {
                if ($e->getCode() === 404) {
                    $errors[] = $setting;

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
    }

    /**
     * @param  array<string, mixed>  $page
     */
    private function enableDashboardEmbedding(MetabaseClient $client, array $page): void
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
            'enable_embedding'  => true,
            'embedding_params'  => $embeddingParams === [] ? new stdClass : $embeddingParams,
            'width'             => 'full',
        ]);

        $this->info(sprintf(
            'Enabled embedding for dashboard %d (%s)%s.',
            $id,
            $page['name'],
            $embeddingParams === [] ? '' : ' with filters: '.implode(', ', array_keys($embeddingParams))
        ));
    }
}
