<?php

namespace App\Console\Commands;

use App\Actions\Keycloak\GetKeycloakClientSecretAction;
use App\Actions\Keycloak\GetKeycloakRealmPublicKeyAction;
use App\Enums\KeyCloakClient;
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
        $clientSecrets = [];
        foreach (KeyCloakClient::cases() as $keyCloakClient) {
            $this->info("✓ Client '{$keyCloakClient->clientId()}' is ingesteld met client ID");
            $secret = $this->getAndDisplayClientSecret($getClientSecretAction, $keyCloakClient, true);
            if (! $secret) {
                return Command::FAILURE;
            }
            $clientSecrets[$keyCloakClient->envKeySecret()] = $secret['secret'];
        }
        // Get and display realm public key
        $publicKeyResult = $this->getAndDisplayRealmPublicKey($getRealmPublicKeyAction);
        if (! $publicKeyResult) {
            return Command::FAILURE;
        }

        // Output parseerbare key=value pairs voor scripts (bijv. reset_base.sh)
        $this->outputParseableValues($clientSecrets, $publicKeyResult);

        return Command::SUCCESS;
    }

    /**
     * Get and display a client secret.
     */
    private function getAndDisplayClientSecret(
        GetKeycloakClientSecretAction $action,
        KeyCloakClient $keyCloakClient,
        bool $required = true
    ): ?array {
        $this->newLine();
        $this->info("Ophalen van client secret ({$keyCloakClient->clientId()})...");

        $result = $action->execute($keyCloakClient);

        if (! $result['success']) {
            if ($required) {
                $this->error($result['message'] ?? 'Failed to retrieve Keycloak client secret.');

                return null;
            }

            if (! empty($result['message'])) {
                $this->warn("{$keyCloakClient->clientId()} client secret kon niet worden opgehaald: {$result['message']}");
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
    private function outputParseableValues(array $secretsEnvKeyBySecret, array $publicKeyResult): void
    {
        $this->newLine();
        $this->line('---');
        foreach ($secretsEnvKeyBySecret as $envKey => $secret) {
            $this->line("{$envKey}={$secret}");
        }
        $this->line('KEYCLOAK_REALM_PUBLIC_KEY='.$publicKeyResult['public_key']);
    }
}
