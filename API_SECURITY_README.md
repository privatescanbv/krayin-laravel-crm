# API Security Implementation

## Overzicht

De API laag is nu beveiligd met API key authenticatie. Alle endpoints onder `/api/` vereisen nu een geldige API key in de `X-API-KEY` header.

## Configuratie

### 1. API Keys instellen

Voeg je API keys toe aan je `.env` bestand:

```env
# API Keys voor API authenticatie
API_KEY_1=your-secret-api-key-1
API_KEY_2=your-secret-api-key-2
API_KEY_3=your-secret-api-key-3
```

**Belangrijk:** Gebruik sterke, unieke API keys in productie!

### 2. Middleware configuratie

De `ApiKeyAuth` middleware is automatisch geregistreerd als `api.key` in `app/Http/Kernel.php` en toegepast op alle API routes in `routes/api.php`.

## Gebruik

### API Requests maken

Alle API requests moeten nu de `X-API-KEY` header bevatten:

```bash
curl -X POST http://your-domain.com/api/leads \
  -H "X-API-KEY: your-secret-api-key-1" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "title": "Test Lead",
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "lead_source_id": 1,
    "lead_channel_id": 1,
    "lead_type_id": 1
  }'
```

### JavaScript/Axios voorbeeld

```javascript
const response = await axios.post('/api/leads', leadData, {
  headers: {
    'X-API-KEY': 'your-secret-api-key-1',
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
});
```

## Beveiligde Endpoints

De volgende endpoints zijn nu beveiligd:

- `GET /api/leads` - Lijst van leads
- `POST /api/leads` - Nieuwe lead aanmaken
- `GET /api/leads/{id}` - Lead details
- `PUT /api/leads/{id}` - Lead bijwerken
- `PATCH /api/leads/{id}/stage` - Lead stage bijwerken
- `PATCH /api/leads/{id}/next_stage` - Lead naar volgende stage
- `DELETE /api/leads/{id}` - Lead verwijderen
- `POST /api/leads/{leadId}/notes` - Notitie toevoegen
- `POST /api/leads/{id}/activities` - Activiteit toevoegen
- `GET /api/leads/{id}/activities` - Lead activiteiten
- `GET /api/groups/byDepartment/{departmentName}` - Groepen per afdeling
- `POST /api/workflow-leads` - Workflow lead aanmaken

## Error Responses

### Geen API key
```json
{
  "error": "API key is required",
  "message": "Please provide a valid API key in the X-API-KEY header"
}
```
HTTP Status: 401

### Ongeldige API key
```json
{
  "error": "Invalid API key", 
  "message": "The provided API key is not valid"
}
```
HTTP Status: 401

## Tests

### API Key Authentication Tests

De `ApiKeyAuthTest.php` test suite controleert:
- Requests zonder API key worden afgewezen (401)
- Requests met ongeldige API key worden afgewezen (401) 
- Requests met geldige API key worden geaccepteerd
- Alle beveiligde endpoints vereisen API key

### Bestaande API Tests bijgewerkt

De `ApiLeadCreationWithAnamnesisTest.php` is bijgewerkt om API key authenticatie te gebruiken via de `makeApiRequest()` helper functie.

### Tests uitvoeren

```bash
# API key authenticatie tests
./vendor/bin/pest tests/Feature/ApiKeyAuthTest.php --verbose

# Bijgewerkte API lead tests  
./vendor/bin/pest tests/Feature/ApiLeadCreationWithAnamnesisTest.php --verbose

# Of gebruik het bestaande script
./run_api_lead_test.sh
```

## Implementatie Details

### Files aangepast/toegevoegd:

1. **`app/Http/Middleware/ApiKeyAuth.php`** - Nieuwe middleware voor API key authenticatie
2. **`config/api.php`** - Configuratie bestand voor API keys
3. **`app/Http/Kernel.php`** - Middleware geregistreerd als 'api.key'
4. **`routes/api.php`** - Alle routes beschermd met api.key middleware
5. **`tests/Feature/ApiKeyAuthTest.php`** - Nieuwe test suite voor API authenticatie
6. **`tests/Feature/ApiLeadCreationWithAnamnesisTest.php`** - Bijgewerkt voor API key gebruik
7. **`.env.example`** - API key configuratie toegevoegd

### Middleware werking:

1. Controleert aanwezigheid van `X-API-KEY` header
2. Vergelijkt met geconfigureerde API keys uit `config('api.keys')`
3. Retourneert 401 bij ontbrekende of ongeldige key
4. Laat request door bij geldige key

### Flexibiliteit:

- Meerdere API keys ondersteund (standaard 3)
- Eenvoudig nieuwe keys toevoegen via environment variabelen
- Keys kunnen per omgeving verschillen (dev/staging/prod)

## Security Best Practices

1. **Gebruik sterke API keys**: Minimaal 32 karakters, willekeurig gegenereerd
2. **Roteer keys regelmatig**: Vervang API keys periodiek
3. **Beperk toegang**: Geef alleen benodigde keys aan clients
4. **Monitor gebruik**: Log API key gebruik voor security audits
5. **HTTPS verplicht**: Gebruik altijd HTTPS in productie om keys te beschermen

## Troubleshooting

### "API key is required" fout
- Controleer of `X-API-KEY` header is meegestuurd
- Controleer spelling van header naam (hoofdlettergevoelig)

### "Invalid API key" fout  
- Controleer of API key correct is in .env bestand
- Controleer of cache is geleegd na .env wijzigingen
- Controleer of API key geen extra spaties bevat

### Tests falen
- Controleer of test API keys zijn ingesteld in test configuratie
- Controleer of `makeApiRequest()` helper wordt gebruikt in API tests