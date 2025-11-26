<?php

namespace App\Mail;

use App\Support\KeycloakConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Webkul\Contact\Models\Person;

class PortalWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Person $person, public string $temporaryPassword) {}

    /**
     * Build the message.
     */
    public function build(): self
    {
        $loginUrl = $this->buildLoginUrl();

        return $this
            ->subject('Welkom bij het Privatescan patiëntportaal')
            ->view('adminc.emails.portal-welcome', [
                'person'            => $this->person,
                'temporaryPassword' => $this->temporaryPassword,
                'loginUrl'          => $loginUrl,
            ]);
    }

    protected function buildLoginUrl(): string
    {
        $realm = KeycloakConfig::realm();

        // Gebruik de Keycloak account-pagina zodat de patiënt kan inloggen en het wachtwoord wijzigen.
        return KeycloakConfig::externalUrl("realms/{$realm}/account");
    }
}
