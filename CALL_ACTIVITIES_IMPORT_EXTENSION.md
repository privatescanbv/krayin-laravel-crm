# Call Activities Import Extension

## Overview

The `import:leads` command has been extended to also import call activities from the SugarCRM `calls` table. This extension allows you to import both leads and their associated call activities in a single operation.

## What was implemented

### 1. Extended Import Command
- Modified `ImportLeadsFromSugarCRM` class to include call activity import functionality
- Added new methods:
  - `extractCallActivities()`: Extracts call data from SugarCRM calls table
  - `importCallActivities()`: Creates Activity records of type 'call'
  - `mapCallStatus()`: Maps SugarCRM call status to activity completion status

### 2. Call Activity Mapping

#### SugarCRM calls table fields mapped to Activity model:
- `id` → `additional.external_id` (for tracking imported records)
- `name` → `title`
- `description` → `comment`
- `date_start` → `schedule_from`
- `date_end` → `schedule_to`
- `date_entered` → `created_at`
- `date_modified` → `updated_at`
- `status` → `is_done` (mapped via `mapCallStatus()`)
- `direction` → `additional.direction`
- `belgroep_c` → `additional.belgroep`
- `parent_id` → Used to link to lead (via `lead_id`)

#### Additional fields stored in JSON:
The `additional` JSON field stores:
```json
{
  "external_id": "call-uuid-from-sugarcrm",
  "direction": "inbound|outbound",
  "status": "original-sugarcrm-status",
  "belgroep": "call-group-from-sugarcrm"
}
```

### 3. Call Status Mapping
The following SugarCRM call statuses are mapped to `is_done = true`:
- `held`
- `completed` 
- `done`
- `finished`

All other statuses map to `is_done = false`.

### 4. Relationship Requirements
Call activities are only imported for calls where:
- `parent_type = 'Leads'`
- `parent_id` matches a lead being imported
- `deleted = 0`

## Usage

The command usage remains the same. Call activities are automatically imported along with leads:

```bash
php artisan import:leads --connection=sugarcrm --limit=100
```

### Dry Run
Use `--dry-run` to see what would be imported, including call activities:

```bash
php artisan import:leads --connection=sugarcrm --limit=10 --dry-run
```

The dry run output now includes:
- Call activities count per lead
- Summary of total call activities found

### Import Results
After import completion, you'll see statistics for both leads and call activities:

```
Import completed!
✓ Imported: 5
⚠ Skipped: 2
✗ Person not found: 0
✗ Errors: 0

Call Activities:
✓ Call activities imported: 12
⚠ Call activities skipped: 3
```

## Testing

A new test case `imports call activities from sugarcrm` has been added to verify:
- Call activities are properly extracted from SugarCRM
- Activities are created with correct type ('call')
- SugarCRM fields are properly mapped
- Status mapping works correctly
- Activities are linked to the correct lead

## Database Structure

### SugarCRM calls table (source)
```sql
CREATE TABLE calls (
  id char(36) PRIMARY KEY,
  name varchar(50),
  date_entered datetime,
  date_modified datetime,
  modified_user_id char(36),
  created_by char(36),
  description text,
  deleted tinyint(1),
  assigned_user_id char(36),
  date_start datetime,
  date_end datetime,
  parent_type varchar(255),
  status varchar(100),
  direction varchar(100),
  parent_id char(36),
  belgroep_c varchar(100)
);
```

### Target activities table (destination)
The activities are imported into the existing `activities` table with:
- `type = 'call'`
- `lead_id` linking to the imported lead
- Original SugarCRM data preserved in `additional` JSON field

## Error Handling

- Duplicate call activities (same `external_id`) are skipped
- Call activities for leads that fail to import are not imported
- Import continues even if individual call activities fail
- All operations are wrapped in database transactions

## Notes

- Default `user_id = 1` is assigned to all imported call activities
- You may want to implement user mapping based on `assigned_user_id` field
- The `belgroep_c` field is preserved for potential future use
- Call activities maintain their original timestamps from SugarCRM