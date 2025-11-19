<?php

namespace App\Console\Commands;

use App\Services\KeycloakService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ConfigureKeycloakRealm extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'keycloak:configure-realm';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configure Keycloak realm with correct frontend URL';

    /**
     * Execute the console command.
     */
    public function handle(KeycloakService $keycloakService): int
    {
        $realm = $keycloakService->getRealm();

        $this->info('Authenticeren met Keycloak admin...');
        $accessToken = $keycloakService->getAdminToken();

        if (! $accessToken) {
            $this->error('Kon niet authenticeren met Keycloak admin.');

            return Command::FAILURE;
        }

        $realmUrl = $keycloakService->getRealmAdminUrl();

        // Get current realm config
        $this->info("Ophalen huidige configuratie voor realm: {$realm}...");
        $realmResponse = Http::withToken($accessToken)->get($realmUrl);

        if (! $realmResponse->successful()) {
            $this->error('Kon realm configuratie niet ophalen: '.$realmResponse->body());

            return Command::FAILURE;
        }

        // Set frontend URL to base URL (for browser access)
        $frontendUrl = $keycloakService->getBaseUrl();

        $this->info("Instellen frontend URL naar: {$frontendUrl}");

        // Update realm with frontend URL
        $updateData = [
            'frontendUrl' => $frontendUrl,
        ];

        $updateResponse = Http::withToken($accessToken)->put($realmUrl, $updateData);

        if (! $updateResponse->successful()) {
            $this->error('Kon realm niet updaten: '.$updateResponse->body());

            return Command::FAILURE;
        }

        $this->info("✓ Realm geconfigureerd met frontend URL: {$frontendUrl}");
        $this->info("Tokens zullen nu altijd {$frontendUrl} als issuer gebruiken.");

        return Command::SUCCESS;
    }
}
