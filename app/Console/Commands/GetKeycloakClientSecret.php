<?php

namespace App\Console\Commands;

use App\Actions\Keycloak\GetKeycloakClientSecretAction;
use Illuminate\Console\Command;

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
    public function handle(GetKeycloakClientSecretAction $action): int
    {
        $clientId = $this->argument('client_id');

        $this->info('Ophalen van client secret...');

        $result = $action->execute($clientId);

        if (! $result['success']) {
            $this->error($result['message'] ?? 'Kon client secret niet ophalen.');

            return Command::FAILURE;
        }

        $this->info("✓ Client secret voor {$result['client_id']}:");
        $this->line('   '.$result['secret']);
        $this->newLine();
        $this->warn('⚠ Voeg dit toe aan je .env file:');
        $this->line("   {$result['env_key']}={$result['secret']}");

        return Command::SUCCESS;
    }
}
