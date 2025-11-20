<?php

namespace App\Console\Commands;

use App\Services\KeycloakService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GetKeycloakRealmPublicKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'keycloak:get-realm-public-key';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get the realm public key from Keycloak';

    /**
     * Execute the console command.
     */
    public function handle(KeycloakService $keycloakService): int
    {
        $realm = $keycloakService->getRealm();

        if (empty($realm)) {
            $this->error('KEYCLOAK_REALM is niet geconfigureerd in .env.');

            return Command::FAILURE;
        }

        $this->info("Ophalen van realm public key voor: {$realm}");

        $realmUrl = rtrim($keycloakService->getDockerServiceUrl(), '/').'/realms/'.$realm;

        try {
            $response = Http::get($realmUrl);

            if (! $response->successful()) {
                $this->error('Kon realm public key niet ophalen: '.$response->status().' - '.$response->body());

                return Command::FAILURE;
            }

            $publicKey = $response->json('public_key');

            if (empty($publicKey)) {
                $this->error('Realm public key niet gevonden in Keycloak response.');

                return Command::FAILURE;
            }

            $this->info("✓ Realm public key voor {$realm}:");
            $this->line('   '.$publicKey);
            $this->newLine();
            $this->warn('⚠ Voeg dit toe aan je .env file:');
            $this->line('   KEYCLOAK_REALM_PUBLIC_KEY='.$publicKey);

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error('Fout bij ophalen realm public key: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
