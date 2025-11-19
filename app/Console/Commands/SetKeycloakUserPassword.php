<?php

namespace App\Console\Commands;

use App\Actions\Keycloak\SetKeycloakUserPasswordAction;
use Illuminate\Console\Command;

class SetKeycloakUserPassword extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'keycloak:set-password
                            {email : Email van de gebruiker}
                            {password : Nieuw wachtwoord}
                            {--temporary : Maak het wachtwoord tijdelijk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Stel wachtwoord in voor een Keycloak gebruiker';

    /**
     * Execute the console command.
     */
    public function handle(SetKeycloakUserPasswordAction $action): int
    {
        $email = $this->argument('email');
        $password = $this->argument('password');
        $temporary = $this->option('temporary');

        $this->info('Authenticeren met Keycloak admin...');

        $result = $action->execute($email, $password, $temporary);

        if (! $result['success']) {
            $this->error($result['message'] ?? 'Kon wachtwoord niet instellen');

            return Command::FAILURE;
        }

        $this->info("✓ Wachtwoord ingesteld voor gebruiker: {$email}");

        if ($temporary) {
            $this->warn('Wachtwoord is ingesteld als tijdelijk - gebruiker moet het bij eerste login wijzigen');
        }

        return Command::SUCCESS;
    }
}
