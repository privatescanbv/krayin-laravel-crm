# E-mail Processor Architectuur

Deze documentatie beschrijft de gelaagde architectuur van de e-mail processing systemen binnen het CRM.

## Overzicht

Het e-mail processing systeem is opgebouwd uit drie lagen:

1. **AbstractEmailProcessor** - Abstracte basis klasse met gedeelde logica
2. **Concrete Processors** - Specifieke implementaties voor verschillende e-mail bronnen
3. **Contracts** - Interfaces voor compatibiliteit met bestaande systemen

## Architectuur Diagram

```
InboundEmailProcessor (Contract)
        ↑
AbstractEmailProcessor (Abstract Class)
        ↑
    ┌───┴───┐
    │       │
GraphMailService    ImapEmailProcessor
(Microsoft Graph)   (IMAP)
```

## AbstractEmailProcessor

De `AbstractEmailProcessor` klasse bevat alle gedeelde logica tussen verschillende e-mail processors:

### Gedeelde Functionaliteiten

- **Message Processing**: Algemene logica voor het verwerken van e-mail berichten
- **Entity Linking**: Automatische koppeling aan Person/Lead/Activity records
- **Activity Creation**: Aanmaken van activiteiten voor e-mail communicatie
- **Logging**: Database logging en error handling
- **Parent Email Detection**: Detectie van reply chains en conversaties

### Abstracte Methoden

Concrete processors moeten de volgende methoden implementeren:

```php
// Data fetching
abstract protected function fetchMessages(): array;
abstract protected function isValidMessage($message): bool;

// Message parsing
abstract protected function getMessageId($message): string;
abstract protected function getConversationId($message): ?string;
abstract protected function getFolderName($message): string;
abstract protected function extractEmailData($message, string $folderName, ?Email $parentEmail): array;

// Recipient handling
abstract protected function getFromEmail($message): string;
abstract protected function getToRecipients($message): array;
abstract protected function extractEmailFromRecipient($recipient): ?string;

// Attachments
abstract protected function hasAttachments($message): bool;
abstract protected function processAttachments(Email $email, $message): void;

// Message state
abstract protected function markMessageAsRead($message): void;

// Configuration
abstract protected function getSyncType(): string;
abstract protected function getProcessorName(): string;
abstract protected function getSyncMetadata(): array;
```

## Concrete Processors

### GraphMailService

**Doel**: Microsoft Graph API integratie

**Specifieke Functionaliteiten**:
- OAuth 2.0 client credentials flow
- Microsoft Graph API communicatie
- JSON response parsing
- Base64 attachment handling
- REST API calls voor message state updates

**Configuratie**:
```env
MAIL_RECEIVER_DRIVER=microsoft-graph
GRAPH_CLIENT_ID=your-client-id
GRAPH_TENANT_ID=your-tenant-id
GRAPH_CLIENT_SECRET=your-client-secret
GRAPH_MAILBOX=crm@example.com
```

### ImapEmailProcessor

**Doel**: IMAP server integratie

**Specifieke Functionaliteiten**:
- IMAP client connectie
- Folder traversal
- MIME message parsing
- IMAP flag management
- Attachment download via IMAP

**Configuratie**:
```env
MAIL_RECEIVER_DRIVER=imap
IMAP_HOST=mail.example.com
IMAP_PORT=993
IMAP_USERNAME=user@example.com
IMAP_PASSWORD=password
```

## Voordelen van deze Architectuur

### 1. Code Hergebruik
- Gedeelde logica in abstracte klasse
- Geen duplicatie van entity linking, logging, etc.
- Consistente error handling

### 2. Uitbreidbaarheid
- Nieuwe e-mail bronnen kunnen eenvoudig worden toegevoegd
- Alleen specifieke logica hoeft te worden geïmplementeerd
- Bestaande functionaliteit blijft behouden

### 3. Onderhoudbaarheid
- Wijzigingen in gedeelde logica worden automatisch doorgevoerd
- Duidelijke scheiding van verantwoordelijkheden
- Testbare componenten

### 4. Compatibiliteit
- Implementeert bestaande `InboundEmailProcessor` contract
- Geen breaking changes voor bestaande code
- Backward compatibility behouden

## Implementatie van een Nieuwe Processor

Om een nieuwe e-mail processor toe te voegen:

1. **Extend AbstractEmailProcessor**:
```php
class NewEmailProcessor extends AbstractEmailProcessor
{
    // Implementeer alle abstracte methoden
}
```

2. **Registreer in ServiceProvider**:
```php
if ($driver === 'new-processor') {
    return $app->make(NewEmailProcessor::class);
}
```

3. **Voeg configuratie toe**:
```php
// In config/mail-receiver.php
'default' => env('MAIL_RECEIVER_DRIVER', 'new-processor'),
```

4. **Schrijf tests**:
```php
class NewEmailProcessorTest extends TestCase
{
    // Test de specifieke functionaliteit
}
```

## Testing Strategy

### Unit Tests
- Test abstracte klasse via concrete implementaties
- Mock dependencies (repositories, HTTP clients)
- Test error scenarios en edge cases

### Integration Tests
- Test volledige e-mail processing flow
- Test database logging
- Test entity linking functionaliteit

### Contract Tests
- Verificeer implementatie van `InboundEmailProcessor`
- Test backward compatibility

## Performance Overwegingen

### Caching
- Access tokens worden gecached in processors
- Database queries worden geoptimaliseerd
- Message processing wordt gebatcht

### Error Handling
- Graceful degradation bij service failures
- Retry logic voor tijdelijke fouten
- Uitgebreide logging voor debugging

### Resource Management
- Proper cleanup van connections
- Memory management voor grote attachments
- Rate limiting respecteren

## Monitoring en Logging

### Database Logging
- Alle sync activiteiten worden gelogd in `email_logs`
- Performance metrics per processor
- Error tracking en reporting

### Application Logging
- Structured logging met context
- Different log levels per component
- Correlation IDs voor tracing

### Metrics
- Messages processed per minute
- Error rates per processor
- Response times voor external APIs

## Conclusie

De gelaagde architectuur biedt een schaalbare en onderhoudbare oplossing voor e-mail processing. Door de gedeelde logica te abstraheren en specifieke implementaties te isoleren, kunnen nieuwe e-mail bronnen eenvoudig worden toegevoegd zonder bestaande functionaliteit te beïnvloeden.