<?php

namespace App\Console\Commands;

use App\Services\Metabase\MetabaseApiException;
use App\Services\Metabase\MetabaseEmbeddingService;
use App\Services\Metabase\MetabaseEnvironments;
use App\Services\Metabase\MetabaseSyncException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class EnableMetabaseEmbeds extends Command
{
    protected $signature = 'metabase:enable-embeds
        {--environment=dev : Metabase environment name from config/services.php}';

    protected $description = 'Zet guest embedding aan in Metabase voor de CRM-dashboardpagina\'s uit config/metabase_dashboards.php.';

    public function handle(MetabaseEnvironments $environments, MetabaseEmbeddingService $embedding): int
    {
        $environment = (string) $this->option('environment');

        try {
            $client = $environments->resolve($environment);
        } catch (MetabaseSyncException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line("Environment: {$client->label} ({$client->baseUrl()})");

        try {
            foreach ($embedding->enable($client) as $line) {
                $this->info($line);
            }
        } catch (MetabaseApiException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        Cache::forget('metabase.embed.secret.'.$client->label);

        $secret = $embedding->embeddingSecret($client);

        if ($secret === '') {
            $this->warn('Metabase did not return an embedding-secret-key. Static embeds will fail until that setting exists.');
        } else {
            $this->info('Metabase embedding secret is set ('.strlen($secret).' chars). The CRM fetches it via the API — do not put a Metabase API key (mb_...) in METABASE_EMBEDDING_SECRET_KEY.');
        }

        return self::SUCCESS;
    }
}
