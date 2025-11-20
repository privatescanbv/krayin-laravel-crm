<?php

namespace App\Console\Commands;

use App\Actions\Keycloak\SyncUsersToKeycloakAction;
use App\Services\KeycloakConfigService;
use Illuminate\Console\Command;

/**
 * Potiential candidate to remove, because this is auto done even when users user seeder or person seeder.
 */
class SyncUsersToKeycloak extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'keycloak:sync-users
                            {--dry-run : Show what would be synced without actually syncing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync existing CRM users to Keycloak using their CRM passwords';

    /**
     * Execute the console command.
     */
    public function handle(
        SyncUsersToKeycloakAction $action,
        KeycloakConfigService $configService
    ): int {
        $dryRun = $this->option('dry-run');

        $this->info('Keycloak configuratie synchroniseren...');

        $configResults = $configService->syncConfig();

        if (! empty($configResults['errors'])) {
            foreach ($configResults['errors'] as $error) {
                $this->error($error);
            }

            return Command::FAILURE;
        }

        $this->info('Authenticeren met Keycloak admin...');

        $result = $action->execute($dryRun);

        if (! $result['success']) {
            $this->error($result['message'] ?? 'Synchronisatie mislukt');

            return Command::FAILURE;
        }

        if ($dryRun) {
            $this->warn('DRY RUN MODE - Geen gebruikers zijn daadwerkelijk gesynchroniseerd');
        }

        $this->newLine();
        $this->info('=== Samenvatting ===');
        $this->info("Gesynchroniseerd: {$result['synced']}");
        $this->info("Overgeslagen: {$result['skipped']}");
        $this->info("Fouten: {$result['errors']}");

        if ($result['skipped'] > 0) {
            $this->newLine();
            $this->warn('Let op: Gebruikers met gehashte wachtwoorden kunnen niet worden gesynchroniseerd.');
            $this->warn('Gebruikers worden automatisch gesynchroniseerd wanneer:');
            $this->warn('  - Ze worden aangemaakt/bijgewerkt via UserObserver (plaintext wachtwoord beschikbaar)');
            $this->warn('  - Ze inloggen via SSO (account wordt automatisch gekoppeld)');
        }

        if ($dryRun) {
            $this->warn('Dit was een dry-run. Voer het commando opnieuw uit zonder --dry-run om daadwerkelijk te synchroniseren.');
        }

        return Command::SUCCESS;
    }
}
