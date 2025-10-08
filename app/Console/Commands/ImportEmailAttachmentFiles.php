<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Webkul\Email\Models\Attachment;

/**
 * Import actual email attachment files from upload_sugarcrm to Krayin storage
 *
 * This command reads email_attachments records and copies the actual files
 * from upload_sugarcrm/{attachment_id} to the Krayin storage path specified in the path field.
 */
class ImportEmailAttachmentFiles extends AbstractSugarCRMImport
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:email-attachment-files
                            {--dry-run : Show what would be copied without actually copying}
                            {--limit= : Limit number of attachments to process}
                            {--attachment-ids=* : Specific attachment IDs to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import actual email attachment files from upload_sugarcrm to Krayin storage';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');
        $attachmentIds = $this->option('attachment-ids');

        $this->info('Starting email attachment files import...');
        $this->info('Dry run: '.($dryRun ? 'Yes' : 'No'));
        if ($limit) {
            $this->info("Limit: {$limit}");
        }
        if (! empty($attachmentIds)) {
            $this->info('Attachment IDs: '.implode(', ', $attachmentIds));
        }

        return $this->executeImport($dryRun, function () use ($limit, $attachmentIds, $dryRun) {
            // Start import run tracking
            if (! $dryRun) {
                $this->startImportRun('email-attachments');
            }

            // Check if upload_sugarcrm directory exists
            $uploadDir = '/var/www/html/upload_sugarcrm';
            if (! File::exists($uploadDir)) {
                throw new Exception("Upload directory does not exist: {$uploadDir}");
            }

            // Get email attachments to process
            $query = Attachment::query();

            if (! empty($attachmentIds)) {
                $query->whereIn('id', $attachmentIds);
            }

            if ($limit) {
                $query->limit((int) $limit);
            }

            $attachments = $query->get();

            if ($attachments->isEmpty()) {
                $this->info('No email attachments found to process');

                return;
            }

            $this->info("Found {$attachments->count()} email attachments to process");

            if ($dryRun) {
                $this->showDryRunResults($attachments, $uploadDir);

                return;
            }

            $this->processAttachments($attachments, $uploadDir);
        });
    }

    /**
     * Show dry run results
     */
    private function showDryRunResults($attachments, string $uploadDir): void
    {
        $this->info("\n=== DRY RUN RESULTS ===");

        $headers = [
            'ID',
            'Name',
            'Path',
            'Email ID',
            'Source File',
            'Source Exists',
            'Target Path',
            'Target Exists',
            'Action',
        ];

        $rows = [];

        foreach ($attachments as $attachment) {
            $sourceFile = $this->getSourceFilePath($attachment, $uploadDir);
            $targetPath = $this->getTargetPath($attachment);
            $sourceExists = File::exists($sourceFile);
            $targetExists = Storage::exists($targetPath);

            $action = 'Skip';
            if ($sourceExists && ! $targetExists) {
                $action = 'Copy';
            } elseif ($sourceExists && $targetExists) {
                $action = 'Skip (exists)';
            } elseif (! $sourceExists) {
                $action = 'Skip (no source)';
            }

            $rows[] = [
                $attachment->id,
                $attachment->name,
                $attachment->path,
                $attachment->email_id,
                $sourceFile,
                $sourceExists ? '✓' : '✗',
                $targetPath,
                $targetExists ? '✓' : '✗',
                $action,
            ];
        }

        $this->table($headers, $rows);

        $copyCount = count(array_filter($rows, fn ($row) => $row[8] === 'Copy'));
        $skipCount = count($rows) - $copyCount;

        $this->info("Would copy: {$copyCount} files");
        $this->info("Would skip: {$skipCount} files");
    }

    /**
     * Process attachments and copy files
     */
    private function processAttachments($attachments, string $uploadDir): int
    {
        $bar = $this->output->createProgressBar($attachments->count());
        $bar->start();

        $copied = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($attachments as $attachment) {
            try {
                $sourceFile = $this->getSourceFilePath($attachment, $uploadDir);
                $targetPath = $this->getTargetPath($attachment);

                // Check if source file exists
                if (! File::exists($sourceFile)) {
                    $this->warn("\nSource file not found: {$sourceFile}");
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                // Check if target already exists
                if (Storage::exists($targetPath)) {
                    $this->info("\nTarget file already exists: {$targetPath}");
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                // Create target directory if it doesn't exist
                $targetDir = dirname($targetPath);
                if (! Storage::exists($targetDir)) {
                    Storage::makeDirectory($targetDir);
                }

                // Copy the file
                $sourceContent = File::get($sourceFile);
                Storage::put($targetPath, $sourceContent);

                $this->infoV("\n✓ Copied: {$sourceFile} → {$targetPath}");
                $copied++;

            } catch (Exception $e) {
                $this->error("\nFailed to copy attachment {$attachment->id}: ".$e->getMessage());
                $errors++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Import completed!');
        $this->info("✓ Copied: {$copied}");
        $this->info("⚠ Skipped: {$skipped}");
        $this->info("✗ Errors: {$errors}");

        // Complete import run tracking
        $this->completeImportRun([
            'processed' => $copied + $skipped + $errors,
            'imported'  => $copied,
            'skipped'   => $skipped,
            'errored'   => $errors,
        ]);

        return $errors > 0 ? 1 : 0;
    }

    /**
     * Get source file path from upload_sugarcrm directory
     */
    private function getSourceFilePath(Attachment $attachment, string $uploadDir): string
    {
        // Extract attachment ID from path: emails/{email_id}/{attachment_id}
        $pathParts = explode('/', $attachment->path);
        $attachmentId = end($pathParts); // Last part is the attachment ID

        return $uploadDir.'/'.$attachmentId;
    }

    /**
     * Get target path for Krayin storage
     */
    private function getTargetPath(Attachment $attachment): string
    {
        // Use the path from the email_attachments table directly
        return $attachment->path;
    }
}
