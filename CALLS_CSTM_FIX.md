# Call Activities Import - Custom Fields Fix

## Issue Identified
The call activities import was failing with the error:
```
Column not found: 1054 Unknown column 'belgroep_c'
```

## Root Cause
Custom fields ending with `_c` (like `belgroep_c`) are stored in a separate `calls_cstm` table in SugarCRM, similar to how `leads_cstm` works for lead custom fields. The original query was trying to select `belgroep_c` directly from the `calls` table, but it doesn't exist there.

## Solution Applied

### 1. Updated Query with JOIN
**File:** `app/Console/Commands/ImportLeadsFromSugarCRM.php`
**Method:** `extractCallActivities()`

**Before:**
```php
$sql = DB::connection($connection)
    ->table('calls')
    ->select([...])
    ->whereIn('parent_id', $leadIds)
```

**After:**
```php
$sql = DB::connection($connection)
    ->table('calls as c')
    ->join('calls_cstm as cc', 'c.id', '=', 'cc.id_c')
    ->select([
        'c.id',
        'c.name',
        // ... other calls fields with 'c.' prefix
        'cc.belgroep_c'  // Custom field from calls_cstm
    ])
    ->whereIn('c.parent_id', $leadIds)
```

### 2. Added Table Existence Check
Added check for both `calls` and `calls_cstm` tables:
```php
if (!Schema::connection($connection)->hasTable('calls_cstm')) {
    $this->info('Calls_cstm table does not exist in SugarCRM database, skipping call activities import');
    return [];
}
```

### 3. Updated Test Setup
**File:** `tests/Feature/ImportLeadsFromSugarCRMTest.php`

Added `calls_cstm` table creation:
```php
Schema::connection('sugarcrm')->create('calls_cstm', function (Blueprint $table) {
    $table->string('id_c')->primary();
    $table->string('belgroep_c')->nullable();
});
```

Updated test data insertion to insert into both tables:
```php
// Insert into calls table (without belgroep_c)
DB::connection('sugarcrm')->table('calls')->insert([...]);

// Insert into calls_cstm table (with custom fields)
DB::connection('sugarcrm')->table('calls_cstm')->insert([
    ['id_c' => $callId1, 'belgroep_c' => 'intake'],
    ['id_c' => $callId2, 'belgroep_c' => 'follow-up'],
]);
```

### 4. Fixed Activity Model JSON Casting
**File:** `packages/Webkul/Activity/src/Models/Activity.php`

Added missing cast for the `additional` JSON field:
```php
protected $casts = [
    'schedule_from' => 'datetime',
    'schedule_to'   => 'datetime',
    'assigned_at'   => 'datetime',
    'additional'    => 'array',  // Added this
];
```

## Database Structure
The SugarCRM calls structure follows the same pattern as leads:

- **calls** - Main table with standard fields
- **calls_cstm** - Custom fields table
- **Relationship**: `calls.id = calls_cstm.id_c`

## Expected Result
✅ **Call activities extraction** - Now properly joins both tables  
✅ **Custom fields access** - `belgroep_c` field is available  
✅ **Test compatibility** - Test setup matches production structure  
✅ **Error handling** - Graceful fallback if custom table doesn't exist  

The test should now pass completely, importing both leads and their associated call activities with all custom fields properly preserved.