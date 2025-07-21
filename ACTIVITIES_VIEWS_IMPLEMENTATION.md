# Activities Views Implementation

## Overzicht

Dit document beschrijft de implementatie van het views systeem voor de admin/activities sectie. Het views systeem vervangt de oude filter knoppen (Hernia/Privatescan) met een flexibele dropdown die verschillende voorgedefinieerde filters bevat.

## Geïmplementeerde Views

### 1. Voor mij (default)
- **Beschrijving**: Activiteiten toegewezen aan de huidige gebruiker
- **Filters**: 
  - `activities.user_id = current_user_id`
  - `activities.is_done = 0` (alleen open activiteiten)

### 2. Voor mij of mijn groep(en)
- **Beschrijving**: Activiteiten toegewezen aan de huidige gebruiker of groepen waar de gebruiker lid van is
- **Filters**:
  - `activities.user_id = current_user_id` OF `activities.group_id IN (user_group_ids)`
  - `activities.is_done = 0` (alleen open activiteiten)

### 3. Hernia
- **Beschrijving**: Activiteiten van afdeling Hernia
- **Filters**:
  - `activities.group_id = 2` (Hernia groep ID)
  - `activities.is_done = 0` (alleen open activiteiten)

### 4. Privatescan
- **Beschrijving**: Activiteiten van afdeling Privatescan  
- **Filters**:
  - `activities.group_id = 1` (Privatescan groep ID)
  - `activities.is_done = 0` (alleen open activiteiten)

## Architectuur

### Bestanden Toegevoegd/Gewijzigd

#### 1. ViewService
**Bestand**: `/packages/Webkul/Activity/src/Services/ViewService.php`

De ViewService is verantwoordelijk voor:
- Het definiëren van beschikbare views
- Het toepassen van view filters op query builders
- Het beheren van default view logica

#### 2. ActivityController Updates
**Bestand**: `/packages/Webkul/Admin/src/Http/Controllers/Activity/ActivityController.php`

Wijzigingen:
- ViewService dependency injection toegevoegd
- `index()` methode geüpdatet om views data door te geven
- Nieuwe `getViews()` API endpoint toegevoegd
- `get()` methode geüpdatet om view parameter te ondersteunen

#### 3. ActivityDataGrid Updates  
**Bestand**: `/packages/Webkul/Admin/src/DataGrids/Activity/ActivityDataGrid.php`

Wijzigingen:
- ViewService integration in `prepareQueryBuilder()`
- Automatische toepassing van view filters op basis van request parameter

#### 4. ActivityRepository Updates
**Bestand**: `/packages/Webkul/Activity/src/Repositories/ActivityRepository.php`

Wijzigingen:
- `getActivities()` methode geüpdatet om view parameter te accepteren
- View filters toegepast op calendar data

#### 5. View Template Updates
**Bestand**: `/packages/Webkul/Admin/src/Resources/views/activities/index.blade.php`

Wijzigingen:
- Oude filter knoppen vervangen door views dropdown
- JavaScript logica geüpdatet voor view selectie
- Session storage voor view persistentie
- Loading states en visuele feedback
- Calendar view integration

#### 6. Routes Updates
**Bestand**: `/packages/Webkul/Admin/src/Routes/Admin/activities-routes.php`

Wijzigingen:
- Nieuwe `/activities/views` route toegevoegd

#### 7. Service Provider Updates
**Bestand**: `/packages/Webkul/Activity/src/Providers/ActivityServiceProvider.php`

Wijzigingen:
- ViewService singleton registration

## Functionaliteiten

### 1. View Persistentie
- Geselecteerde view wordt opgeslagen in session storage
- View blijft behouden bij wisselen tussen kanban/tabel view
- View blijft behouden bij navigeren naar activiteit detail en terug

### 2. URL Integratie
- View selectie wordt gereflecteerd in URL parameters
- Direct linking naar specifieke views mogelijk
- Browser back/forward button ondersteuning

### 3. Real-time Filtering
- Filters worden onmiddellijk toegepast bij view selectie
- Zowel tabel als calendar view ondersteunen view filtering
- Loading states voor betere UX

### 4. Responsive Design
- Dropdown werkt op desktop en mobile
- Visuele indicatoren voor actieve view
- Toegankelijkheids-vriendelijke implementatie

## API Endpoints

### GET /admin/activities/views
Retourneert alle beschikbare views met hun configuratie.

**Response**:
```json
{
  "views": {
    "for_me": {
      "key": "for_me",
      "label": "Voor mij",
      "description": "Activiteiten toegewezen aan mij",
      "is_default": true,
      "filters": [...]
    },
    // ... andere views
  }
}
```

### GET /admin/activities/get?view={view_key}
Bestaande endpoint geüpdatet om view parameter te accepteren.

## Uitbreidbaarheid

Het systeem is ontworpen om eenvoudig uitgebreid te kunnen worden:

### Nieuwe View Toevoegen

1. **ViewService uitbreiden**:
```php
// In getAvailableViews() method
'new_view' => [
    'key' => 'new_view',
    'label' => 'Nieuwe View',
    'description' => 'Beschrijving van nieuwe view',
    'is_default' => false,
    'filters' => $this->getNewViewFilters(),
],
```

2. **Filter methode toevoegen**:
```php
protected function getNewViewFilters(): array
{
    return [
        [
            'column' => 'custom_field',
            'operator' => 'eq',
            'value' => 'custom_value',
        ],
    ];
}
```

3. **Filter logica implementeren**:
```php
// In applyFilter() method
case 'custom_field':
    $queryBuilder->where('activities.custom_field', $value);
    break;
```

## Testen

Voor het testen van de implementatie:

1. **Functionaliteit testen**:
   - Selecteer verschillende views en controleer filtering
   - Wissel tussen tabel en calendar view
   - Navigeer naar activiteit detail en terug
   - Vernieuw pagina en controleer view persistentie

2. **Browser compatibiliteit**:
   - Test in verschillende browsers
   - Test responsive gedrag op mobile

3. **Performance**:
   - Controleer query performance met verschillende views
   - Test met grote datasets

## Migratie van Oude Implementatie

De oude filter knoppen zijn vervangen, maar de onderliggende filtering logica blijft grotendeels hetzelfde. Bestaande gebruikers zullen automatisch de default "Voor mij" view zien bij hun eerste bezoek na de update.

## Troubleshooting

### View wordt niet toegepast
- Controleer of ViewService correct is geregistreerd in service provider
- Verificeer dat view parameter correct wordt doorgegeven in requests

### Persistentie werkt niet
- Controleer browser session storage ondersteuning
- Verificeer dat JavaScript zonder errors draait

### Performance problemen
- Analyseer database queries met view filters
- Overweeg indexing op gefilterde kolommen