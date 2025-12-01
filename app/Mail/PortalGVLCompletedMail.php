<?php

namespace App\Mail;

use App\Support\KeycloakConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Webkul\Contact\Models\Person;

class PortalGVLCompletedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(private Person $person, private string $showFormUrl) {}

    /**
     * Build the message.
     */
    public function build(): self
    {
        $initialsLastname = trim($this->person->name ?? '');
        if ($initialsLastname === '') {
            $initialsLastname = 'patiënt';
        }

        return $this
            ->subject('Welkom bij het Privatescan patiëntportaal')
            ->view('adminc.emails.portal-gvl-completed-patient', [
                'person'            => $this->person,
                'formUrl'           => $this->showFormUrl,
                'patientPortalUrl'  => config('services.portal.patient.web_url'),
                'initials_lastname' => $initialsLastname,
            ]);
    }

    protected function buildLoginUrl(): string
    {
        $realm = KeycloakConfig::realm();

        // Gebruik de Keycloak account-pagina zodat de patiënt kan inloggen en het wachtwoord wijzigen.
        return KeycloakConfig::externalUrl("realms/{$realm}/account");
    }
}
