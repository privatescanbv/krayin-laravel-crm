# Debug Call Activities Import Issue

## Problem
Call activities import seems to work (no errors), but test still fails:
```
Failed asserting that actual size 0 matches expected size 2.
```

## Debugging Steps Applied

### 1. Fixed Custom Fields Issue
- ✅ Added `calls_cstm` table join for custom fields like `belgroep_c`
- ✅ Updated test to create both `calls` and `calls_cstm` tables
- ✅ Split test data insertion between main and custom tables

### 2. Fixed Activity Model JSON Casting
- ✅ Added `'additional' => 'array'` cast to Activity model
- ✅ This ensures JSON fields are properly handled

### 3. Fixed User ID Issue
- ✅ Added user creation in test: `$user = \Webkul\User\Models\User::factory()->create()`
- ✅ Updated import to use first available user: `\Webkul\User\Models\User::first()?->id ?? 1`

### 4. Added Comprehensive Debugging
Added debug output to test to check:
- Lead ID and external_id
- Total activities for the lead
- Call activities count
- Activity types and lead_ids
- First activity data structure
- Total activities in entire database

### 5. Enhanced Import Logging
Added detailed logging in import process:
- Activity ID after creation
- Lead ID verification
- Verification that activity is linked to correct lead

## Potential Issues Still to Investigate

1. **Transaction Isolation**: Activities might be created in a different transaction
2. **Database Connection**: Test might be using different database than import
3. **Timing Issue**: Activities created after test query runs
4. **Foreign Key Constraint**: User or Lead foreign keys might be failing silently
5. **Model Relationship**: Lead->activities() relationship might be incorrect

## Next Steps

Run the test with debug output to see:
1. Are activities being created at all? (check total in DB)
2. Are they linked to the correct lead? (check lead_ids)
3. Do they have the correct type? (check types array)
4. Is the relationship working? (check lead activities count)

## Expected Debug Output

If working correctly, should see:
```
Lead ID: 1
Lead external_id: "lead-001"  
Total activities: 2
Call activities: 2
Activity types: ["call", "call"]
Activity lead_ids: [1, 1]
Total activities in database: 2
```

If broken, might see:
- Total activities in database: 0 (not created at all)
- Total activities: 0, but database > 0 (not linked to lead)
- Activity types: not "call" (wrong type)
- Activity lead_ids: not matching lead ID (wrong association)