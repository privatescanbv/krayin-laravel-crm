# Lead-Person Sync Feature

## Overview
This feature automatically redirects users to the lead-person sync page when editing a lead, based on specific conditions.

## Conditions for Sync Redirect
The system will redirect to the sync page when **both** of the following conditions are met:

1. **Lead has exactly 1 person** attached to it
2. **Match score is not 100** (not a perfect match between lead and person)

## Implementation Details

### Modified Files

#### 1. LeadController.php
- **Location**: `/workspace/packages/Webkul/Admin/src/Http/Controllers/Lead/LeadController.php`
- **Method**: `update(LeadForm $request, int $id)`
- **Changes**: Added sync condition check after lead update

#### 2. PersonController.php
- **Location**: `/workspace/packages/Webkul/Admin/src/Http/Controllers/Contact/Persons/PersonController.php`
- **Method**: `calculateMatchScore(Lead $lead, Person $person)`
- **Changes**: Made method public to allow access from LeadController

### New Methods Added to LeadController

1. **`shouldRedirectToSync($lead)`** - Checks if sync conditions are met
2. **`calculateMatchScore(Lead $lead, Person $person)`** - Calculates match score
3. **`buildMatchBreakdown(Lead $lead, Person $person)`** - Builds detailed match breakdown
4. **Various scoring methods** for name, email, phone, and address matching
5. **Helper methods** for data extraction and normalization

### How It Works

1. User edits a lead and submits the form
2. Lead is updated successfully
3. System checks if lead has exactly 1 person attached
4. If yes, calculates match score between lead and person
5. If match score < 100, redirects to sync page
6. If conditions not met, redirects to normal lead view

### Sync Page
- **Route**: `admin.contacts.persons.edit_with_lead`
- **Parameters**: `personId` and `leadId`
- **Purpose**: Allows users to compare and synchronize data between lead and person

### Testing

#### Test File
- **Location**: `/workspace/tests/Feature/LeadSyncRedirectTest.php`
- **Coverage**: 
  - Redirect when conditions are met (1 person + match score < 100)
  - No redirect when lead has 0 persons
  - No redirect when lead has 2+ persons
  - No redirect when match score is 100
  - AJAX request handling

#### Running Tests
```bash
php artisan test tests/Feature/LeadSyncRedirectTest.php
```

### Match Score Calculation

The match score is calculated based on:
- **Name fields** (85% weight): first_name, last_name, lastname_prefix, married_name, married_name_prefix, initials, date_of_birth
- **Email** (5% weight): email addresses
- **Phone** (5% weight): phone numbers
- **Address** (5% weight): street, house_number, city, postal_code, country

### Example Scenarios

#### Scenario 1: Sync Redirect
- Lead: John Smith, john@example.com
- Person: John Doe, john@example.com
- Result: Redirects to sync page (name mismatch, email match)

#### Scenario 2: No Redirect
- Lead: John Doe, john@example.com
- Person: John Doe, john@example.com
- Result: Redirects to lead view (perfect match, score = 100)

#### Scenario 3: No Redirect
- Lead with 0 or 2+ persons
- Result: Redirects to lead view (condition not met)

### Benefits

1. **Improved Data Quality**: Automatically prompts users to sync when there are discrepancies
2. **Better User Experience**: Seamless workflow for data synchronization
3. **Reduced Manual Work**: Users don't need to manually check for sync opportunities
4. **Consistent Data**: Ensures lead and person data are properly synchronized

### Technical Notes

- The implementation uses the existing sync page functionality
- Match score calculation is consistent with existing PersonController logic
- Handles both regular HTTP requests and AJAX requests
- Includes comprehensive error handling and edge case management
- Maintains backward compatibility with existing functionality