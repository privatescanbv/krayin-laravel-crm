<?php

namespace App\Console\Commands;

use App\Actions\Keycloak\GetKeycloakClientSecretAction;
use App\Actions\Keycloak\GetKeycloakRealmPublicKeyAction;
use App\Services\Keycloak\KeycloakConfigService;
use Illuminate\Console\Command;

class CreateKeycloakRealm extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'keycloak:create-realm';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates Keycloak realm and configures clients, roles, and groups, if not existing';

    /**
     * Execute the console command.
     */
    public function handle(
        KeycloakConfigService $configService,
        GetKeycloakClientSecretAction $getClientSecretAction,
        GetKeycloakRealmPublicKeyAction $getRealmPublicKeyAction
    ): int {
        $this->info('Keycloak realm aanmaken...');

        $configResults = $configService->syncConfig();

        if (! empty($configResults['errors'])) {
            foreach ($configResults['errors'] as $error) {
                $this->error($error);
            }

            return Command::FAILURE;
        }

        // Get and display CRM client secret
        $crmSecretResult = $this->getAndDisplayClientSecret($getClientSecretAction, 'CRM');
        if (! $crmSecretResult) {
            return Command::FAILURE;
        }

        // Get and display Forms client secret (optional)
        $formsSecretResult = $this->getAndDisplayClientSecret($getClientSecretAction, 'Forms', 'forms-app', false);

        // Get and display realm public key
        $publicKeyResult = $this->getAndDisplayRealmPublicKey($getRealmPublicKeyAction);
        if (! $publicKeyResult) {
            return Command::FAILURE;
        }

        // Output parseerbare key=value pairs voor scripts (bijv. reset_base.sh)
        $this->outputParseableValues($crmSecretResult, $formsSecretResult, $publicKeyResult);

        return Command::SUCCESS;
    }

    /**
     * Get and display a client secret.
     */
    private function getAndDisplayClientSecret(
        GetKeycloakClientSecretAction $action,
        string $label,
        ?string $clientId = null,
        bool $required = true
    ): ?array {
        $this->newLine();
        $this->info("Ophalen van client secret ({$label})...");

        $result = $action->execute($clientId);

        if (! $result['success']) {
            if ($required) {
                $this->error($result['message'] ?? 'Failed to retrieve Keycloak client secret.');

                return null;
            }

            if (! empty($result['message'])) {
                $this->warn("{$label} client secret kon niet worden opgehaald: {$result['message']}");
            }

            return null;
        }

        $this->info("✓ Client secret voor {$result['client_id']}:");
        $this->line('   '.$result['secret']);
        $this->newLine();
        $this->warn('⚠ Voeg dit toe aan je .env file:');
        $this->line("   {$result['env_key']}={$result['secret']}");

        return $result;
    }

    /**
     * Get and display the realm public key.
     */
    private function getAndDisplayRealmPublicKey(GetKeycloakRealmPublicKeyAction $action): ?array
    {
        $this->newLine();
        $this->info('Ophalen van realm public key...');

        $result = $action->execute();

        if (! $result['success']) {
            $this->error($result['message'] ?? 'Failed to retrieve Keycloak realm public key.');

            return null;
        }

        return $result;
    }

    /**
     * Output parseerbare key=value pairs voor scripts.
     */
    private function outputParseableValues(array $crmSecretResult, ?array $formsSecretResult, array $publicKeyResult): void
    {
        $this->newLine();
        $this->line('---');
        $this->line('KEYCLOAK_CLIENT_SECRET='.$crmSecretResult['secret']);

        if ($formsSecretResult) {
            $this->line('FORMS_KEYCLOAK_CLIENT_SECRET='.$formsSecretResult['secret']);
        }

        $this->line('KEYCLOAK_REALM_PUBLIC_KEY='.$publicKeyResult['public_key']);
    }
}
