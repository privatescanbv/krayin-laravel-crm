<?php

namespace App\Services\Importers\SugarCRM;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Models\File;
use Webkul\Lead\Models\Lead;

class AttachmentImporter
{
    protected Command $command;

    protected string $connection;

    public function __construct(Command $command, string $connection)
    {
        $this->command = $command;
        $this->connection = $connection;
    }

    /**
     * Extract email attachments from SugarCRM for the given leads
     *
     * @param  mixed  $records  The lead records
     * @return array [lead_id => [attachment_data1, attachment_data2, ...]]
     */
    public function extractEmailAttachments($records): array
    {
        $leadIds = collect($records)->pluck('id')->all();

        if (empty($leadIds)) {
            return [];
        }

        try {
            // Check if notes table exists
            if (! Schema::connection($this->connection)->hasTable('notes')) {
                $this->command->info('Notes table does not exist in SugarCRM database, skipping email attachments import');

                return [];
            }

            // First, get all email IDs that belong to our leads
            $emailIds = DB::connection($this->connection)
                ->table('emails_beans as eb')
                ->join('emails as e', 'e.id', '=', 'eb.email_id')
                ->whereIn('eb.bean_id', $leadIds)
                ->where('eb.bean_module', '=', 'Leads')
                ->where('e.deleted', '=', 0)
                ->where('eb.deleted', '=', 0)
                ->pluck('e.id')
                ->all();

            if (empty($emailIds)) {
                $this->command->info('No emails found for leads, skipping email attachments import');

                return [];
            }

            // Now get attachments (notes) for these emails
            $sql = DB::connection($this->connection)
                ->table('notes as n')
                ->join('emails_beans as eb', function ($join) {
                    $join->on('eb.email_id', '=', 'n.parent_id')
                        ->where('n.parent_type', '=', 'Emails')
                        ->where('eb.bean_module', '=', 'Leads')
                        ->where('eb.deleted', '=', 0);
                })
                ->select([
                    'n.id',
                    'n.name',
                    'n.filename',
                    'n.file_mime_type',
                    'n.description',
                    'n.date_entered',
                    'n.date_modified',
                    'n.created_by',
                    'n.assigned_user_id',
                    'n.deleted',
                    'n.parent_id as email_id',
                    'n.parent_type',
                    'eb.bean_id as lead_id',
                ])
                ->whereIn('n.parent_id', $emailIds)
                ->where('n.parent_type', '=', 'Emails')
                ->where('n.deleted', '=', 0)
                ->whereNotNull('n.filename') // Only notes with files
                ->orderBy('n.date_entered', 'asc');

            $this->command->info('Extracting email attachments: '.$sql->toRawSql());
            $attachments = $sql->get();

            $this->command->info('Found '.$attachments->count().' email attachments');

            // Group attachments by lead_id
            $result = [];
            foreach ($attachments as $attachment) {
                if (! isset($result[$attachment->lead_id])) {
                    $result[$attachment->lead_id] = [];
                }
                $result[$attachment->lead_id][] = $attachment;
            }

            return $result;
        } catch (Exception $e) {
            $this->command->error('Failed to extract email attachments: '.$e->getMessage());
            $this->command->info('Continuing import without email attachments');

            return [];
        }
    }

    /**
     * Import email attachments for a lead
     *
     * @param  Lead  $lead  The lead to import attachments for
     * @param  array  $emailAttachments  All email attachments grouped by lead ID
     * @param  array  $emailActivities  Email activities to map attachments to
     * @return array Statistics about imported and skipped attachments
     */
    public function importEmailAttachments(Lead $lead, array $emailAttachments, array $emailActivities): array
    {
        $imported = 0;
        $skipped = 0;

        try {
            $leadEmailAttachments = $emailAttachments[$lead->external_id] ?? [];

            if (empty($leadEmailAttachments)) {
                return ['imported' => $imported, 'skipped' => $skipped];
            }

            $this->command->info('Importing '.count($leadEmailAttachments)." email attachments for lead {$lead->external_id}");

            // Get email activities for this lead to map attachments to
            $leadEmailActivities = $emailActivities[$lead->external_id] ?? [];
            $emailActivityMap = [];
            foreach ($leadEmailActivities as $emailActivity) {
                $emailActivityMap[$emailActivity->id] = $emailActivity;
            }

            foreach ($leadEmailAttachments as $attachmentData) {
                try {
                    // Find the corresponding email activity
                    $emailActivity = $emailActivityMap[$attachmentData->email_id] ?? null;
                    if (! $emailActivity) {
                        $this->command->warn("Email activity not found for attachment {$attachmentData->id}, email_id: {$attachmentData->email_id}");
                        $skipped++;

                        continue;
                    }

                    // Find the imported Activity record by external_id
                    $activity = Activity::where('external_id', $attachmentData->email_id)
                        ->where('lead_id', $lead->id)
                        ->where('type', 'email')
                        ->first();

                    if (! $activity) {
                        $this->command->warn("Activity record not found for email {$attachmentData->email_id}");
                        $skipped++;

                        continue;
                    }

                    // Check if attachment already exists by external reference
                    $existingFile = $activity->files()
                        ->where('name', $attachmentData->filename)
                        ->where('path', 'LIKE', "%{$attachmentData->id}%")
                        ->first();

                    if ($existingFile) {
                        $this->command->info("Skipping existing email attachment: {$attachmentData->filename}");
                        $skipped++;

                        continue;
                    }

                    // Create file path based on attachment data
                    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $attachmentData->filename);
                    $filePath = "email_attachments/{$lead->external_id}/{$attachmentData->email_id}/{$attachmentData->id}_{$safeName}";

                    // Create the file record
                    $fileData = [
                        'name'        => $attachmentData->filename,
                        'path'        => $filePath,
                        'activity_id' => $activity->id,
                    ];

                    $file = $this->createEntityWithTimestamps(File::class, $fileData, [
                        'created_at' => $this->parseSugarDate($attachmentData->date_entered),
                        'updated_at' => $this->parseSugarDate($attachmentData->date_modified),
                    ]);

                    $this->command->info("✓ Imported email attachment: {$attachmentData->filename} for email activity {$activity->id}");
                    $imported++;
                } catch (Exception $e) {
                    $this->command->error("Failed to import email attachment {$attachmentData->id}: ".$e->getMessage());
                    // Continue with next attachment
                }
            }
        } catch (Exception $e) {
            $this->command->error("Failed to import email attachments for lead {$lead->external_id}: ".$e->getMessage());
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * Parse SugarCRM date format to our timezone
     */
    private function parseSugarDate($value): ?string
    {
        if (! $value) {
            return null;
        }
        try {
            // Accept Carbon, DateTimeInterface, or string
            if ($value instanceof \DateTimeInterface) {
                return Carbon::instance($value)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
            }

            // Parse SugarCRM date assuming it's already in the application timezone
            // SugarCRM dates appear to be stored in local time, not UTC
            return Carbon::parse((string) $value, config('app.timezone'))
                ->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Create an entity with proper timestamps from SugarCRM data
     *
     * @param  string  $modelClass  The model class to create
     * @param  array  $data  The entity data
     * @param  array  $timestamps  The timestamps to set (created_at, updated_at)
     * @return mixed The created entity
     */
    private function createEntityWithTimestamps(string $modelClass, array $data, array $timestamps = [])
    {
        // Create entity without timestamps to avoid auto-override
        $entity = new $modelClass($data);
        $entity->timestamps = false;

        // Set custom timestamps if provided
        if (! empty($timestamps['created_at'])) {
            $entity->setAttribute('created_at', $timestamps['created_at']);
        }
        if (! empty($timestamps['updated_at'])) {
            $entity->setAttribute('updated_at', $timestamps['updated_at']);
        }

        // Save without triggering timestamps
        $entity->saveQuietly();

        // Re-enable timestamps for future operations
        $entity->timestamps = true;

        return $entity;
    }
}
