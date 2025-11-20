<?php

namespace App\Console\Commands;

use App\Services\KeycloakConfigService;
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
        KeycloakConfigService $configService
    ): int {
        $this->info('Keycloak realm aanmaken...');

        $configResults = $configService->syncConfig();

        if (! empty($configResults['errors'])) {
            foreach ($configResults['errors'] as $error) {
                $this->error($error);
            }

            return Command::FAILURE;
        }

        $exitCode = $this->call('keycloak:get-client-secret');

        if ($exitCode !== 0) {
            $this->error('Failed to retrieve Keycloak client secret.');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
