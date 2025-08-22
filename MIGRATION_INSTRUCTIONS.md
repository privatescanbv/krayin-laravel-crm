# Lead-Person Many-to-Many Migration Instructions

## 🚨 Belangrijke Informatie

De Lead-Person many-to-many functionaliteit is **geïmplementeerd maar tijdelijk uitgeschakeld** om database errors te voorkomen. Na het uitvoeren van de migraties moet je enkele TODO items activeren.

## 📋 Stap 1: Run Migraties

Voer de volgende migraties uit in de juiste volgorde:

```bash
php artisan migrate
```

Dit zal de volgende migraties uitvoeren:
1. `2025_01_15_000000_create_lead_persons_table.php` - Maakt pivot tabel aan
2. `2025_01_15_000001_migrate_lead_person_data.php` - Migreert bestaande data
3. `2025_01_15_000002_remove_person_id_from_leads_table.php` - Verwijdert person_id kolom
4. `2025_01_15_000004_add_organization_id_to_leads_table.php` - Voegt organization_id toe
5. `2025_01_15_000005_modify_lead_persons_table_structure.php` - Composite primary key

## 📋 Stap 2: Activeer Persons Relationship Loading

Na het uitvoeren van de migraties, zoek naar alle **TODO** comments en uncomment de persons relationship loading:

### 2.1 LeadController - Kanban Data Loading
**File**: `packages/Webkul/Admin/src/Http/Controllers/Lead/LeadController.php`

```php
// VOOR (line ~137):
'data' => LeadResource::collection($paginator = $query->with([
    'tags',
    'type',
    'source',
    'user',
    'pipeline',
    'pipeline.stages',
    'stage',
    'attribute_values',
    // TODO: Add 'persons', 'persons.organization' after migrations are run
])->paginate(10)),

// NA:
'data' => LeadResource::collection($paginator = $query->with([
    'tags',
    'type',
    'source',
    'user',
    'persons',
    'persons.organization',
    'pipeline',
    'pipeline.stages',
    'stage',
    'attribute_values',
])->paginate(10)),
```

### 2.2 LeadController - Edit Method
**File**: `packages/Webkul/Admin/src/Http/Controllers/Lead/LeadController.php`

```php
// VOOR (line ~268):
$lead = $this->leadRepository->with(['address'])->findOrFail($id);

// NA:
$lead = $this->leadRepository->with(['address', 'persons'])->findOrFail($id);
```

### 2.3 LeadController - View Method
**File**: `packages/Webkul/Admin/src/Http/Controllers/Lead/LeadController.php`

```php
// VOOR (line ~280):
$lead = $this->leadRepository->with([
    'anamnesis', 
    'address', 
    // TODO: Add 'persons.organization' back after migrations are run
    'source', 
    'type', 
    'channel', 
    'department',
    'user'
])->findOrFail($id);

// NA:
$lead = $this->leadRepository->with([
    'anamnesis', 
    'address', 
    'persons.organization', 
    'source', 
    'type', 
    'channel', 
    'department',
    'user'
])->findOrFail($id);
```

### 2.4 API LeadController - Show Method
**File**: `packages/Webkul/Lead/src/Http/Controllers/Api/LeadController.php`

```php
// VOOR (line ~157):
$lead = $this->leadRepository->with(['address'])->findOrFail($id);

// NA:
$lead = $this->leadRepository->with(['address', 'persons'])->findOrFail($id);
```

### 2.5 DuplicateController
**File**: `packages/Webkul/Admin/src/Http/Controllers/Lead/DuplicateController.php`

```php
// VOOR (line ~27):
$lead = $this->leadRepository->with(['stage', 'pipeline', 'user'])->findOrFail($leadId);

// NA:
$lead = $this->leadRepository->with(['persons', 'stage', 'pipeline', 'user'])->findOrFail($leadId);
```

### 2.6 LeadRepository - Duplicate Detection
**File**: `packages/Webkul/Lead/src/Repositories/LeadRepository.php`

Zoek naar alle **3 instanties** van:
```php
// VOOR:
$query = LeadModel::with(['stage', 'pipeline', 'user'])

// NA:
$query = LeadModel::with(['persons', 'stage', 'pipeline', 'user'])
```

## 📋 Stap 3: Test Functionaliteit

Na het activeren van alle TODO items:

1. **Test Kanban Board**: `http://localhost:8000/admin/leads`
2. **Test Lead Create**: Kan meerdere personen toevoegen
3. **Test Lead Edit**: Kan personen beheren
4. **Test API**: `/api/leads/{id}` toont persons array

## 🔧 Troubleshooting

### Als je nog steeds errors krijgt:

1. **Check Migraties**:
   ```bash
   php artisan migrate:status
   ```

2. **Check Pivot Tabel**:
   ```sql
   DESCRIBE lead_persons;
   SELECT COUNT(*) FROM lead_persons;
   ```

3. **Check Relatie**:
   ```php
   // In tinker:
   $lead = \Webkul\Lead\Models\Lead::first();
   $lead->persons()->count(); // Should work without error
   ```

## ✅ Verwacht Resultaat

Na het voltooien van alle stappen:
- ✅ Kanban board toont leads met multiple persons
- ✅ Create form heeft persons management component  
- ✅ Edit form kan personen toevoegen/verwijderen
- ✅ API responses bevatten persons array
- ✅ Person matching werkt met Lead personal fields
- ✅ Many-to-many relatie volledig functioneel

## 🎯 Samenvatting

De implementatie is **compleet** maar **tijdelijk uitgeschakeld** voor stabiliteit. Na het uitvoeren van migraties en het activeren van de TODO items heb je een volledig werkende many-to-many Lead-Person relatie!