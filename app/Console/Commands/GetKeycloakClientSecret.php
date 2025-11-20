<?php

namespace App\Console\Commands;

use App\Services\KeycloakService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GetKeycloakClientSecret extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'keycloak:get-client-secret {client_id? : The client ID (default: from config)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get the client secret for a Keycloak client';

    /**
     * Execute the console command.
     */
    public function handle(KeycloakService $keycloakService): int
    {
        $clientId = $this->argument('client_id') ?? $keycloakService->getClientId();
        $realmName = $keycloakService->getRealm();

        if (empty($clientId)) {
            $this->error('Client ID is required. Either provide it as an argument or set KEYCLOAK_CLIENT_ID in .env');

            return Command::FAILURE;
        }

        $this->info("Ophalen van client secret voor: {$clientId}");

        // Get admin token
        $accessToken = $keycloakService->getAdminToken();

        if (! $accessToken) {
            $this->error('Kon niet authenticeren met Keycloak admin. Check KEYCLOAK_ADMIN en KEYCLOAK_ADMIN_PASSWORD.');

            return Command::FAILURE;
        }

        // Get client
        $client = $keycloakService->getClientById($clientId, $realmName, $accessToken);

        if (! $client) {
            $this->error("Client {$clientId} niet gevonden in realm {$realmName}.");

            return Command::FAILURE;
        }

        // Get client secret
        $clientSecretUrl = $keycloakService->getDockerServiceUrl()
            .'/admin/realms/'.$realmName
            .'/clients/'.$client['id'].'/client-secret';

        try {
            $response = Http::withToken($accessToken)->get($clientSecretUrl);

            if ($response->successful()) {
                $secret = $response->json('value');

                if ($secret) {
                    $this->info("✓ Client secret voor {$clientId}:");
                    $this->line('   '.$secret);
                    $this->newLine();
                    $this->warn('⚠ Voeg dit toe aan je .env file:');
                    $envKey = 'KEYCLOAK_CLIENT_SECRET';
                    $this->line("   {$envKey}={$secret}");

                    return Command::SUCCESS;
                } else {
                    $this->error('Client secret is leeg of niet gevonden.');

                    return Command::FAILURE;
                }
            } else {
                $this->error('Kon client secret niet ophalen: '.$response->status().' - '.$response->body());

                return Command::FAILURE;
            }
        } catch (Exception $e) {
            $this->error('Fout bij ophalen client secret: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
