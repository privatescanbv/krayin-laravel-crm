# Duplicate Lead Detection and Merging Feature

## Overview

This feature provides automatic detection of potential duplicate leads and allows users to merge them through an intuitive interface. The system can detect duplicates based on email addresses, phone numbers, and name similarity.

## Features

### 1. Duplicate Detection

The system automatically detects potential duplicates based on:

- **Email addresses**: Leads with matching email addresses
- **Phone numbers**: Leads with matching phone numbers  
- **Name similarity**: Leads with similar first and last names

### 2. Visual Indicators

#### Kanban View
- Duplicate leads show an orange warning icon (⚠️) on kanban cards
- Hover tooltip shows the number of potential duplicates found
- Tooltip text: "Mogelijke duplicate gevonden (X gelijkenissen)"

#### Lead Detail View
- Orange warning banner appears at the top when duplicates are detected
- Shows count of potential duplicates
- "Merge Duplicates" button provides direct access to merge interface

### 3. Merge Interface

The merge interface (`/admin/leads/{id}/duplicates`) provides:

- **Comparison Table**: Side-by-side comparison of lead fields
- **Field Selection**: Radio buttons to choose which values to keep for each field
- **Multi-select**: Checkboxes to select which leads to merge
- **Primary Lead**: The original lead is always marked as primary and cannot be deselected

#### Supported Fields for Merging
- Title
- First Name
- Last Name
- Email addresses
- Phone numbers

### 4. Merge Process

When leads are merged:

1. **Data Transfer**: Activities, quotes, products, and emails are transferred to the primary lead
2. **Field Mapping**: Selected field values are applied to the primary lead
3. **Archival**: Duplicate leads are marked as inactive and prefixed with "[ARCHIVED]"
4. **Activity Log**: A note is added to the primary lead documenting the merge

## API Endpoints

### Duplicate Management Routes
```php
Route::controller(DuplicateController::class)->prefix('{id}/duplicates')->group(function () {
    Route::get('', 'index')->name('admin.leads.duplicates.index');
    Route::get('check', 'checkDuplicates')->name('admin.leads.duplicates.check');
    Route::get('get', 'getDuplicates')->name('admin.leads.duplicates.get');
    Route::post('merge', 'merge')->name('admin.leads.duplicates.merge');
});
```

### API Response Format

#### Check Duplicates
```json
{
    "has_duplicates": true,
    "duplicates_count": 2
}
```

#### Get Duplicates
```json
{
    "duplicates": [
        {
            "id": 123,
            "title": "Lead Title",
            "first_name": "John",
            "last_name": "Doe",
            "emails": [...],
            "phones": [...],
            "person_name": "John Doe",
            "stage_name": "New",
            "pipeline_name": "Sales",
            "created_at": "2024-01-15 10:30:00"
        }
    ],
    "count": 1
}
```

#### Merge Leads
```json
{
    "success": true,
    "message": "Leads successfully merged.",
    "merged_lead": {
        "id": 456,
        "title": "Merged Lead Title"
    }
}
```

## Usage Examples

### 1. Accessing the Merge Interface

From a lead detail page:
1. If duplicates are detected, an orange warning banner appears
2. Click "Merge Duplicates" button
3. Review potential duplicates in the comparison table
4. Select leads to merge and choose field values
5. Click "Merge Selected Leads"

### 2. Kanban Card Indicators

- Orange warning icon appears on kanban cards with potential duplicates
- Hover over the icon to see duplicate count
- Click the card to view lead details and access merge interface

### 3. Programmatic Access

```php
use Webkul\Lead\Repositories\LeadRepository;

$leadRepository = app(LeadRepository::class);
$lead = $leadRepository->find(123);

// Check for duplicates
if ($lead->hasPotentialDuplicates()) {
    $duplicates = $lead->getPotentialDuplicates();
    $count = $lead->getPotentialDuplicatesCount();
    
    // Merge leads
    $leadRepository->mergeLeads(
        $primaryLeadId = 123,
        $duplicateLeadIds = [124, 125],
        $fieldMappings = [
            'title' => 123,
            'first_name' => 124,
            'emails' => 123
        ]
    );
}
```

## Database Changes

### Lead Model Updates
- Added `hasPotentialDuplicates()` method
- Added `getPotentialDuplicates()` method  
- Added `getPotentialDuplicatesCount()` method

### Repository Updates
- Added `findPotentialDuplicates($lead)` method
- Added `hasPotentialDuplicates($lead)` method
- Added `mergeLeads($primaryId, $duplicateIds, $fieldMappings)` method

### API Resource Updates
- Added `has_duplicates` field to LeadResource
- Added `duplicates_count` field to LeadResource
- Added additional fields for duplicate comparison

## File Structure

```
packages/Webkul/Admin/src/
├── Http/Controllers/Lead/
│   └── DuplicateController.php
├── Resources/views/leads/
│   ├── duplicates/
│   │   └── index.blade.php
│   └── view.blade.php (updated)
├── Resources/lang/en/
│   └── app.php (updated with duplicate translations)
└── Routes/Admin/
    └── leads-routes.php (updated)

packages/Webkul/Lead/src/
├── Models/
│   └── Lead.php (updated)
└── Repositories/
    └── LeadRepository.php (updated)

tests/Feature/
└── LeadDuplicateDetectionTest.php
```

## Language Support

All text is translatable through Laravel's localization system:

```php
// Example translations in app.php
'duplicates' => [
    'potential-found' => 'Potential duplicate found (:count similar lead|:count similar leads)',
    'merge-button' => 'Merge Duplicates',
    'merge-title' => 'Merge Duplicate Leads',
    // ... more translations
],
```

## Testing

The feature includes comprehensive tests covering:

- Email-based duplicate detection
- Phone-based duplicate detection  
- Name similarity detection
- Self-exclusion from duplicate results
- Empty results when no duplicates exist

Run tests with:
```bash
php artisan test tests/Feature/LeadDuplicateDetectionTest.php
```

## Security Considerations

- All merge operations are logged as activities
- User permissions are respected (existing bouncer integration)
- CSRF protection on all merge endpoints
- Validation on all input parameters

## Performance Notes

- Duplicate detection queries are optimized for JSON field searches
- Results are paginated in the kanban view
- Duplicate checks are performed on-demand, not automatically on every lead view
- Consider adding database indexes on email/phone JSON fields for large datasets