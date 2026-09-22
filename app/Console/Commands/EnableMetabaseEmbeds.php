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

    protected $description = 'Publiceer elk CRM-dashboard in Metabase als guest embed (per dashboard verplicht; HTML-snippet niet plakken).';

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
            $this->warn('Metabase did not return an embedding-secret-key. Guest embeds will fail until that setting exists.');
        } else {
            $this->info('Metabase embedding secret is set ('.strlen($secret).' chars). Put that hex key in METABASE_EMBEDDING_SECRET_KEY — not an API key (mb_...).');
        }

        return self::SUCCESS;
    }
}
