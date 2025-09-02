# Import Email Attachment Files Command

## Overview

The `import:email-attachment-files` command copies actual email attachment files from the `upload_sugarcrm` directory to the Krayin storage system based on the paths stored in the `email_attachments` table.

## Usage

```bash
php artisan import:email-attachment-files [options]
```

## Options

- `--dry-run`: Show what would be copied without actually copying files
- `--limit=N`: Limit number of attachments to process
- `--attachment-ids=ID1,ID2,...`: Process only specific attachment IDs

## How it works

1. Reads email attachment records from the `email_attachments` table
2. For each attachment, extracts the attachment ID from the `path` field (format: `emails/{email_id}/{attachment_id}`)
3. Looks for the source file in `upload_sugarcrm/{attachment_id}`
4. Copies the file to the Krayin storage path specified in the `path` field

## Path Structure

- **Source**: `upload_sugarcrm/{attachment_id}` (where attachment_id is extracted from the path)
- **Target**: The exact path from the `email_attachments.path` field (e.g., `emails/123/456`)

## Examples

```bash
# Dry run to see what would be processed
php artisan import:email-attachment-files --dry-run

# Process all attachments
php artisan import:email-attachment-files

# Process only 10 attachments
php artisan import:email-attachment-files --limit=10

# Process specific attachments
php artisan import:email-attachment-files --attachment-ids=123,456,789

# Dry run for specific attachments
php artisan import:email-attachment-files --dry-run --attachment-ids=123,456
```

## Prerequisites

1. The `upload_sugarcrm` directory must exist in the project root
2. Source files must be named with their attachment ID (as extracted from the path)
3. Email attachments must already be imported via the leads import process

## Output

The command provides detailed progress information including:
- Number of files to process
- Copy progress with source and target paths
- Summary of copied, skipped, and error counts
- Reasons for skipping files (already exists, source not found, etc.)