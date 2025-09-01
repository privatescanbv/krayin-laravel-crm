# Import Leads Command - Call Activities Extension Fixes

## Issue Identified
The test was failing because the lead import was not completing successfully, causing the lead to be null when the test tried to verify it.

## Root Cause
The issue was in the database transaction closure where `$callActivities` was included in the `use` clause but was no longer needed since call activities import was moved outside the main transaction.

## Fixes Applied

### 1. Transaction Scope Fix
**File:** `app/Console/Commands/ImportLeadsFromSugarCRM.php`
**Line:** ~357

**Before:**
```php
DB::transaction(function () use ($record, $persons, $leadByPersonsByAnamnesis, $callActivities, &$lead) {
```

**After:**
```php
DB::transaction(function () use ($record, $persons, $leadByPersonsByAnamnesis, &$lead) {
```

**Reason:** Removed unnecessary `$callActivities` from the closure scope since call activities import is now handled outside the main transaction.

### 2. Call Activities Import Moved Outside Transaction
**File:** `app/Console/Commands/ImportLeadsFromSugarCRM.php`
**Lines:** ~421-424

The call activities import is now performed after the main lead import transaction completes successfully:

```php
});

// Import call activities for this lead (outside main transaction)
$callStats = $this->importCallActivities($lead, $callActivities);
$callActivitiesImported += $callStats['imported'];
$callActivitiesSkipped += $callStats['skipped'];
```

**Benefits:**
- If call activities import fails, it doesn't affect the lead import
- Lead import transaction is kept minimal and focused
- Better error isolation

### 3. Robust Error Handling
**File:** `app/Console/Commands/ImportLeadsFromSugarCRM.php`
**Method:** `extractCallActivities()`

Added comprehensive error handling:
- Check if calls table exists before querying
- Graceful fallback if calls table is missing
- Exception handling with proper logging
- Continue import even if call activities extraction fails

### 4. Enhanced Test Setup
**File:** `tests/Feature/ImportLeadsFromSugarCRMTest.php`

- Added calls table creation in test setup
- Added comprehensive test case for call activities import
- Proper test data setup for both leads and call activities

## Expected Behavior After Fixes

1. **Existing tests should pass** - The original lead import functionality is preserved
2. **Call activities import is optional** - If calls table doesn't exist, import continues without call activities
3. **Error isolation** - Call activities import errors don't affect lead import
4. **Backward compatibility** - All existing functionality remains unchanged

## Test Results Expected

The failing test should now pass because:
1. The lead import transaction completes successfully
2. The lead is properly created and can be retrieved
3. Call activities are imported separately (if available)

## Verification Steps

1. Run the existing tests to ensure they pass
2. Test with SugarCRM database that has calls table
3. Test with SugarCRM database that doesn't have calls table
4. Verify dry-run functionality works correctly
5. Check import statistics include call activities counts