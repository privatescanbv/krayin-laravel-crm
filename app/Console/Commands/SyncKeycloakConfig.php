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

        // Display client results
        foreach ($results['clients'] ?? [] as $clientId => $clientResult) {
            if ($clientResult['exists']) {
                $this->info("✓ Client bestaat al: {$clientId}");

                if ($clientResult['updated']) {
                    $this->info("✓ Client geüpdatet: {$clientId}");
                }

                // Show secret if available (useful for production setup)
                if (isset($clientResult['secret'])) {
                    $secretEnvKey = $clientId === 'forms-app' ? 'FORMS_KEYCLOAK_CLIENT_SECRET' : 'KEYCLOAK_CLIENT_SECRET';
                    $this->warn("⚠ Client secret voor {$clientId}. Update {$secretEnvKey} in .env met:");
                    $this->line('   '.$clientResult['secret']);
                }
            } elseif ($clientResult['created']) {
                $this->info("✓ Client aangemaakt: {$clientId}");

                if (isset($clientResult['secret'])) {
                    // Determine secret env key based on client ID
                    $secretEnvKey = $clientId === 'forms-app' ? 'FORMS_KEYCLOAK_CLIENT_SECRET' : 'KEYCLOAK_CLIENT_SECRET';
                    $this->warn("⚠ Client secret gegenereerd. Update {$secretEnvKey} in .env met:");
                    $this->line('   '.$clientResult['secret']);
                } else {
                    $this->warn('⚠ Let op: Client secret is gegenereerd. Haal het op via Keycloak Admin Console of check de logs.');
                }
            }
        }

        $this->info('✓ Keycloak configuratie gesynchroniseerd');

        return Command::SUCCESS;
    }
}
