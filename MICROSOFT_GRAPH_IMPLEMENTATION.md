# Microsoft Graph E-mailverzending Implementatie

## Overzicht

Deze implementatie zorgt ervoor dat alle e-mails die door het CRM worden verstuurd via Microsoft Graph lopen, met het domein `crm.private-scan.nl` en de persoonlijke naam van de ingelogde gebruiker als afzender.

## Geïmplementeerde Features

### 1. Database Wijzigingen

**Migratie**: `database/migrations/2025_10_07_100000_add_first_name_last_name_to_users_table.php`

- Toegevoegd: `first_name` (nullable string)
- Toegevoegd: `last_name` (nullable string)

**Models Bijgewerkt**:
- `app/Models/User.php`
- `packages/Webkul/User/src/Models/User.php`

### 2. Microsoft Graph Mail Transport

**Nieuw**: `app/Services/Mail/MicrosoftGraphMailTransport.php`

Implementeert Symfony's `TransportInterface` en verzorgt:
- OAuth2 authenticatie met Microsoft Graph API
- Dynamische afzendergegevens genereren uit gebruikersinformatie
- E-mails verzenden via Graph API `/sendMail` endpoint
- Attachment handling
- Comprehensive error handling en logging
- Automatische fallback naar default configuratie

**Features**:
- Gebruikt altijd het service account e-mailadres (`crm@privatescan.nl`) om SendAs permission issues te voorkomen
- Gebruikt `first_name + last_name` als afzendernaam voor personalisatie
- Fallback naar `name` veld als first_name/last_name niet beschikbaar zijn
- Alle e-mails worden verstuurd vanuit het geconfigureerde mailbox account

### 3. Service Provider

**Nieuw**: `app/Providers/MicrosoftGraphMailServiceProvider.php`

Registreert de `microsoft-graph` mail transport in Laravel's mail systeem.

**Geregistreerd in**: `config/app.php` providers array

### 4. Configuratie

**Bijgewerkt**: `config/mail.php`

Nieuwe mailer configuratie:
```php
'mailers' => [
    'microsoft-graph' => [
        'transport' => 'microsoft-graph',
    ],
    // ... andere mailers
],

'graph' => [
    'client_id'     => env('GRAPH_CLIENT_ID'),
    'tenant_id'     => env('GRAPH_TENANT_ID'),
    'client_secret' => env('GRAPH_CLIENT_SECRET'),
    'mailbox'       => env('GRAPH_MAILBOX', 'crm@privatescan.nl'),
    'sender_domain' => env('GRAPH_SENDER_DOMAIN', 'crm.private-scan.nl'),
],
```

Default mailer ingesteld op `failover` voor automatische fallback:
```php
'default' => env('MAIL_MAILER', 'failover'),

'failover' => [
    'transport' => 'failover',
    'mailers'   => [
        'microsoft-graph',
        'smtp',
        'log',
    ],
],
```

### 5. Mailable Classes Bijgewerkt

**Bijgewerkt**:
- `packages/Webkul/Email/src/Mails/Email.php`
  - Gebruikt nu `first_name + last_name` voor afzendernaam
  - Fallback naar `name` veld
  
- `packages/Webkul/Admin/src/Notifications/Common.php`
  - Ondersteunt optionele `from` in data array
  - Laat Transport automatisch afzender instellen als niet opgegeven

### 6. Omgevingsvariabelen

**Nieuw**: `.env.example`

Toegevoegde variabelen:
```bash
MAIL_MAILER=failover

GRAPH_CLIENT_ID=
GRAPH_TENANT_ID=
GRAPH_CLIENT_SECRET=
GRAPH_MAILBOX=crm@privatescan.nl
GRAPH_SENDER_DOMAIN=crm.private-scan.nl
```

### 7. Documentatie

**Nieuw**: `docs/modules/application/pages/microsoft-graph-email.adoc`

Complete documentatie inclusief:
- Configuratie instructies
- Azure AD setup
- Architectuur uitleg
- Troubleshooting guide
- Gebruiksvoorbeelden

**Bijgewerkt**: `docs/modules/functional_design/pages/db_structure.adoc`
- User tabel diagram bijgewerkt met `first_name` en `last_name`

## Installatie & Configuratie

### 1. Database Migratie Uitvoeren

```bash
php artisan migrate
```

### 2. Azure AD Configureren

1. Ga naar Azure Portal → Azure Active Directory → App registrations
2. Maak een nieuwe app registratie of gebruik bestaande
3. Noteer:
   - Application (client) ID
   - Directory (tenant) ID
4. Ga naar "Certificates & secrets" → maak een nieuwe client secret
5. Ga naar "API permissions" → voeg toe:
   - `Mail.Send` (Application permission)
   - `Mail.ReadWrite` (Application permission)
6. Klik op "Grant admin consent"

### 3. .env Configureren

```bash
cp .env.example .env
```

Vul de volgende waarden in:
```bash
MAIL_MAILER=failover  # of 'microsoft-graph' voor directe verbinding

GRAPH_CLIENT_ID=your-client-id-from-azure
GRAPH_TENANT_ID=your-tenant-id-from-azure
GRAPH_CLIENT_SECRET=your-client-secret-from-azure
GRAPH_MAILBOX=crm@privatescan.nl
GRAPH_SENDER_DOMAIN=crm.private-scan.nl
```

### 4. Gebruikers Bijwerken

Voor bestaande gebruikers, vul `first_name` en `last_name` in:
- Via database: `UPDATE users SET first_name='...', last_name='...' WHERE ...`
- Via applicatie UI (als beschikbaar)
- Via seeder/import script

## Gebruik

Na configuratie werkt alles automatisch. Geen codewijzigingen nodig:

```php
// Handmatige e-mail
Mail::send(new Email($email));

// Via queue
Mail::queue(new Common([
    'to'      => 'recipient@example.com',
    'subject' => 'Test',
    'body'    => 'Content',
]));

// Automation workflows
// Werken automatisch met nieuwe transport
```

## Fallback Mechanisme

### Automatische Fallback Volgorde (bij MAIL_MAILER=failover)

1. **Microsoft Graph** - Primaire verzendmethode
   - Als credentials geldig zijn
   - Als token succesvol verkregen kan worden
   - Als Graph API bereikbaar is

2. **SMTP** - Fallback bij Graph problemen
   - Gebruikt bestaande SMTP configuratie
   - Automatisch geactiveerd bij Graph errors

3. **Log** - Laatste fallback
   - Logt e-mails naar `storage/logs/`
   - Voorkomt complete failure

### Graph-Only Modus

Voor productieomgevingen met werkende Graph configuratie:
```bash
MAIL_MAILER=microsoft-graph
```

## Testing

### Lokale Test

```bash
# Start artisan tinker
php artisan tinker

# Verzend test e-mail
Mail::raw('Test email body', function($message) {
    $message->to('test@example.com')
            ->subject('Test Email');
});
```

### Logging Checken

```bash
# Kijk naar recente logs
tail -f storage/logs/laravel.log

# Filter Graph logs
grep "Microsoft Graph" storage/logs/laravel.log
```

### Success Indicatoren

✅ Log bevat: "Email sent successfully via Microsoft Graph"
✅ Geen errors in logs
✅ E-mail ontvangen met correcte afzender

### Failure Indicatoren

❌ "Failed to get access token"
❌ "Failed to send email via Microsoft Graph"
❌ "Microsoft Graph credentials not configured"

## Troubleshooting

### Token Errors

**Probleem**: "Failed to get access token"

**Oplossingen**:
1. Controleer GRAPH_CLIENT_ID, GRAPH_TENANT_ID, GRAPH_CLIENT_SECRET
2. Controleer of application permissions correct zijn in Azure
3. Controleer of admin consent is gegeven
4. Controleer of client secret niet is verlopen

### Permission Errors

**Probleem**: 403 Forbidden bij verzenden

**Oplossingen**:
1. Controleer of `Mail.Send` permission is toegevoegd
2. Controleer of admin consent is gegeven
3. Wacht 5-10 minuten na permission wijzigingen

### Mailbox Errors

**Probleem**: Mailbox niet gevonden

**Oplossingen**:
1. Controleer of GRAPH_MAILBOX correct is (email@domain.com)
2. Controleer of mailbox bestaat in Microsoft 365
3. Controleer of service account toegang heeft tot mailbox

### User Zonder first_name/last_name

**Probleem**: E-mail verstuurd met fallback naam

**Oplossing**:
1. Update gebruiker met first_name en last_name
2. Of accepteer fallback naar `name` veld
3. Check logs voor gebruikte afzendernaam

## Impact

### Bestaande Functionaliteit

✅ Alle bestaande e-mail functionaliteit blijft werken
✅ Backward compatible met bestaande Mailable classes
✅ Automatische fallback voorkomt downtime
✅ Geen breaking changes

### Nieuwe Mogelijkheden

✨ Consistente afzender per gebruiker
✨ Professionele e-mailadressen (@crm.private-scan.nl)
✨ Centrale mail configuratie via Azure AD
✨ Betere logging en monitoring
✨ Compliance met corporate mail policies

## Code Quality

- ✅ Volgt Laravel conventions
- ✅ Comprehensive error handling
- ✅ Extensive logging
- ✅ Type hints gebruikt
- ✅ Documentatie toegevoegd
- ✅ Class diagram bijgewerkt
- ✅ Backward compatible

## Maintenance

### Regular Checks

1. **Client Secret Expiry**: Azure client secrets verlopen (check Azure Portal)
2. **Token Errors**: Monitor logs voor authentication issues
3. **Mail Delivery**: Check of e-mails correct aankomen
4. **User Data**: Ensure gebruikers first_name/last_name hebben

### Updates

Bij wijzigingen in Microsoft Graph API:
1. Check Microsoft Graph v1.0 changelog
2. Test in development environment
3. Update Transport class indien nodig
4. Deploy met fallback enabled

## Support

Voor vragen of problemen:
1. Check deze documentatie
2. Check `docs/modules/application/pages/microsoft-graph-email.adoc`
3. Check logs in `storage/logs/laravel.log`
4. Check Azure Portal voor Graph API status
