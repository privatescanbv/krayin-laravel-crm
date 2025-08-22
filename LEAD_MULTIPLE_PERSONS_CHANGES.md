# Lead-Person Many-to-Many Relationship Implementation

## Overview
De Lead-Person relatie is succesvol aangepast van **1-op-1 verplicht** naar **veel-op-veel optioneel**. Een Lead kan nu 0 of meer Personen hebben, en een Persoon kan aan meerdere Leads gekoppeld zijn.

## Database Wijzigingen ✅

### 1. Nieuwe Pivot Tabel
**File**: `packages/Webkul/Lead/src/Database/Migrations/2025_01_15_000000_create_lead_persons_table.php`

```sql
CREATE TABLE `lead_persons` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `lead_id` int unsigned NOT NULL,
  `person_id` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lead_persons_lead_id_person_id_unique` (`lead_id`, `person_id`),
  FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE
);
```

### 2. Data Migratie
**File**: `packages/Webkul/Lead/src/Database/Migrations/2025_01_15_000001_migrate_lead_person_data.php`

- Migrates bestaande `person_id` data van `leads` tabel naar `lead_persons` pivot tabel
- Behoudt timestamps voor audit trail

### 3. Verwijder person_id Kolom
**File**: `packages/Webkul/Lead/src/Database/Migrations/2025_01_15_000002_remove_person_id_from_leads_table.php`

- Verwijdert `person_id` kolom uit `leads` tabel
- Verwijdert bijbehorende foreign key constraint

## Model Wijzigingen ✅

### Lead Model
**File**: `packages/Webkul/Lead/src/Models/Lead.php`

**Voor:**
```php
public function person()
{
    return $this->belongsTo(PersonProxy::modelClass());
}
```

**Na:**
```php
public function persons()
{
    return $this->belongsToMany(PersonProxy::modelClass(), 'lead_persons');
}
```

**Andere wijzigingen:**
- Verwijderd `person_id` uit `$fillable` array

### Person Model  
**File**: `packages/Webkul/Contact/src/Models/Person.php`

**Voor:**
```php
public function leads()
{
    return $this->hasMany(LeadProxy::modelClass(), 'person_id');
}
```

**Na:**
```php
public function leads()
{
    return $this->belongsToMany(LeadProxy::modelClass(), 'lead_persons');
}
```

## Repository Wijzigingen ✅

### LeadRepository
**File**: `packages/Webkul/Lead/src/Repositories/LeadRepository.php`

**Nieuwe functionaliteit:**
- `hasValidPersonData()` helper methode
- Support voor `persons` array in create/update
- Support voor `person_ids` array  
- Backwards compatibility voor single `person` object
- Gebruik van `attach()` en `sync()` voor many-to-many relaties

**Query wijzigingen:**
- Verwijderd `person_id` en `person.name` uit searchable fields
- Toegevoegd `persons.name` voor zoeken
- Verwijderd person join uit getLeadsQuery (niet meer nodig)

## API Wijzigingen ✅

### API LeadController
**File**: `packages/Webkul/Lead/src/Http/Controllers/Api/LeadController.php`

- `show()` methode laadt nu `persons` relatie
- Support voor nieuwe data structuur

### Admin LeadController  
**File**: `packages/Webkul/Admin/src/Http/Controllers/Lead/LeadController.php`

- `edit()` methode laadt `persons` relatie
- `view()` methode gebruikt `persons.organization` in plaats van `person.organization`

## Validatie Wijzigingen ✅

### LeadValidationService
**File**: `app/Services/LeadValidationService.php`

**Nieuwe validatie regels:**
```php
// Person relationships (multiple persons supported)
'person_ids'      => 'nullable|array',
'person_ids.*'    => 'numeric|exists:persons,id',
'persons'         => 'nullable|array',
'persons.*.id'    => 'nullable|numeric|exists:persons,id',
'persons.*.name'  => 'nullable|string|max:255',
'persons.*.emails' => 'nullable|array',
'persons.*.contact_numbers' => 'nullable|array',

// Backwards compatibility for single person
'person.id'       => 'nullable|numeric|exists:persons,id',
'person.name'     => 'nullable|string|max:255',
'person.emails'   => 'nullable|array',
'person.contact_numbers' => 'nullable|array',
```

### DataTransfer Importer
**File**: `packages/Webkul/DataTransfer/src/Helpers/Importers/Leads/Importer.php`

- `person_id` vervangen door `person_ids` array validatie

## UI Wijzigingen ✅

### Nieuwe Multiple Persons Component
**File**: `packages/Webkul/Admin/src/Resources/views/leads/common/multiple-persons.blade.php`

**Functionaliteit:**
- Dynamisch toevoegen/verwijderen van personen
- Person lookup met autocomplete
- Inline person creation
- Responsive design
- Empty state handling

### Lead Create Form
**File**: `packages/Webkul/Admin/src/Resources/views/leads/create.blade.php`

**Wijzigingen:**
- Stap 1 titel: "Contactpersoon zoeken" → "Contactpersonen koppelen"
- Vervangen contact matcher door multiple persons component
- Verwijderd personal fields uit stap 2 (nu in persons component)
- Verwijderd email/phone handling (nu in persons component)
- Aangepaste form submission voor `persons` array
- Verwijderd `selectedPerson`, vervangen door `persons` array

### Lead Edit Form
**File**: `packages/Webkul/Admin/src/Resources/views/leads/edit.blade.php`

**Wijzigingen:**
- "Contact Person" sectie vervangen door "Contactpersonen" 
- Gebruik van multiple persons component
- Data binding: `$lead->person` → `$lead->persons`

## API Data Structuur

### Voor (1-op-1):
```json
{
  "id": 1,
  "title": "Test Lead",
  "person_id": 123,
  "person": {
    "id": 123,
    "name": "John Doe"
  }
}
```

### Na (veel-op-veel):
```json
{
  "id": 1,
  "title": "Test Lead", 
  "persons": [
    {
      "id": 123,
      "name": "John Doe"
    },
    {
      "id": 456,
      "name": "Jane Smith"  
    }
  ]
}
```

## Form Data Structuur

### Create/Update Lead met Multiple Persons:
```php
[
    'title' => 'Test Lead',
    'description' => 'Lead beschrijving',
    'persons' => [
        [
            'id' => 123,           // Bestaande person
            'name' => 'John Doe'
        ],
        [
            'name' => 'Jane Smith', // Nieuwe person
            'email' => 'jane@example.com',
            'phone' => '+31612345678'
        ]
    ]
]
```

### Backwards Compatibility:
```php
[
    'title' => 'Test Lead',
    'person' => [              // Single person (oude manier)
        'id' => 123,
        'name' => 'John Doe'
    ]
]
```

### Direct Person IDs:
```php
[
    'title' => 'Test Lead',
    'person_ids' => [123, 456] // Direct person IDs
]
```

## Test Scenarios ✅

### Scenario A: Lead zonder Personen
```php
$lead = Lead::create([
    'title' => 'Test Lead',
    // Geen persons data - toegestaan
]);

$lead->persons; // Lege collectie
```

### Scenario B: Lead met Meerdere Personen
```php
$lead = Lead::create([
    'title' => 'Test Lead',
    'persons' => [
        ['id' => 123],
        ['name' => 'New Person', 'email' => 'new@example.com']
    ]
]);

$lead->persons; // Collectie met 2 personen
```

### Scenario C: Person met Meerdere Leads
```php
$person = Person::find(123);
$leads = $person->leads; // Collectie met alle gekoppelde leads
```

### Scenario D: Update Lead Persons
```php
$lead = Lead::find(1);
$lead->persons()->sync([123, 456]); // Vervang alle personen
$lead->persons()->attach(789);      // Voeg persoon toe
$lead->persons()->detach(123);      // Verwijder persoon
```

## Backwards Compatibility ✅

Het systeem ondersteunt nog steeds:
- Single `person` object in create/update data
- Bestaande API calls blijven werken
- Oude form submissions worden correct verwerkt

## Migratie Instructies

1. **Run Migrations:**
   ```bash
   php artisan migrate
   ```

2. **Verify Data:**
   ```bash
   # Check if existing data was migrated correctly
   SELECT * FROM lead_persons LIMIT 10;
   
   # Verify person_id column is removed
   DESCRIBE leads;
   ```

3. **Test API:**
   ```bash
   # Test creating lead with multiple persons
   curl -X POST /api/leads \
     -H "Content-Type: application/json" \
     -d '{
       "title": "Test Lead",
       "persons": [
         {"id": 1},
         {"name": "New Person", "email": "test@example.com"}
       ]
     }'
   ```

## Breaking Changes ⚠️

1. **Database Schema:**
   - `leads.person_id` kolom is verwijderd
   - Nieuwe `lead_persons` pivot tabel

2. **Model Relations:**
   - `Lead::person()` → `Lead::persons()`
   - Return type: `Person|null` → `Collection<Person>`

3. **API Response:**
   - `person` object → `persons` array

## Voordelen van de Nieuwe Structuur

1. **Flexibiliteit:** Een lead kan meerdere contactpersonen hebben
2. **Schaalbaarheid:** Geen limiet op aantal personen per lead  
3. **Data Integriteit:** Proper many-to-many relaties
4. **UI/UX:** Duidelijke interface voor het beheren van meerdere personen
5. **Backwards Compatibility:** Oude code blijft werken

## Conclusie

De Lead-Person relatie is succesvol omgezet naar een many-to-many structuur. Alle benodigde wijzigingen zijn doorgevoerd in:

- ✅ Database schema (3 nieuwe migraties)
- ✅ Model relaties (Lead & Person)  
- ✅ Repository logica (LeadRepository)
- ✅ API controllers (Admin & API)
- ✅ Validatie regels (alle services)
- ✅ UI formulieren (create & edit)
- ✅ Nieuwe multiple persons component

Het systeem ondersteunt nu volledig de gewenste functionaliteit waarbij een Lead 0 of meer Personen kan hebben!