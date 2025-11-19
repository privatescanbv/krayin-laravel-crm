<?php

namespace App\Console\Commands;

use App\Services\KeycloakConfigService;
use Illuminate\Console\Command;

class SyncKeycloakConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'keycloak:sync-config';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Keycloak configuration: create realm and client if they do not exist';

    /**
     * Execute the console command.
     */
    public function handle(KeycloakConfigService $configService): int
    {
        $this->info('Synchroniseren van Keycloak configuratie...');

        $results = $configService->syncConfig();

        if (! empty($results['errors'])) {
            foreach ($results['errors'] as $error) {
                $this->error($error);
            }

            return Command::FAILURE;
        }

        // Display results
        if ($results['realm_exists']) {
            $this->info('✓ Realm bestaat al: '.config('services.keycloak.realm'));
        } elseif ($results['realm_created']) {
            $this->info('✓ Realm aangemaakt: '.config('services.keycloak.realm'));
        }

        if ($results['client_exists']) {
            $this->info('✓ Client bestaat al: '.config('services.keycloak.client_id'));

            if ($results['client_updated']) {
                $this->info('✓ Client geüpdatet: '.config('services.keycloak.client_id'));
            }
        } elseif ($results['client_created']) {
            $this->info('✓ Client aangemaakt: '.config('services.keycloak.client_id'));

            if (isset($results['client_secret'])) {
                $this->warn('⚠ Client secret gegenereerd. Update KEYCLOAK_CLIENT_SECRET in .env met:');
                $this->line('   '.$results['client_secret']);
            } else {
                $this->warn('⚠ Let op: Client secret is gegenereerd. Haal het op via Keycloak Admin Console of check de logs.');
            }
        }

        $this->info('✓ Keycloak configuratie gesynchroniseerd');

        return Command::SUCCESS;
    }
}
