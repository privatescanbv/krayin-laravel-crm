<?php

namespace App\Services\Importers\SugarCRM;

use App\Console\Commands\AbstractSugarCRMImport;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Webkul\Email\Models\Attachment;
use Webkul\Lead\Models\Lead;

class AttachmentImporter
{
    protected AbstractSugarCRMImport $command;

    protected string $connection;

    public function __construct(AbstractSugarCRMImport $command, string $connection)
    {
        $this->command = $command;
        $this->connection = $connection;
    }

    /**
     * Extract email attachments from SugarCRM for the given leads
     *
     * @return array [lead_id => [attachment_data1, attachment_data2, ...]]
     *
     * @throws Exception
     */
    public function extractEmailAttachments(array $leadIds): array
    {
        $this->command->infoV('Extracting email attachments for '.count($leadIds).' leads');
        if (empty($leadIds)) {
            return [];
        }

        try {
            $this->command->validateTableExists($this->connection, ['notes']);

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
                $this->command->infoVV('No emails found for leads, skipping email attachments import');

                return [];
            }

            // Now get attachments (notes) for these emails
            $sql = DB::connection($this->connection)
                ->table('notes as n')
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
                    'n.contact_id',
                    'n.portal_flag',
                    'n.embed_flag',

                ])
                ->whereIn('n.parent_id', $emailIds)
                ->where('n.parent_type', '=', 'Emails')
                ->where('n.deleted', '=', 0)
                ->whereRaw("n.name NOT LIKE 'image00%'")
                ->orderBy('n.date_entered', 'asc');

            $attachments = $sql->get();

            $this->command->infoV('Found '.$attachments->count().' email attachments');

            // Get email-bean mappings to determine lead_id for each attachment
            $emailBeanMap = DB::connection($this->connection)
                ->table('emails_beans')
                ->whereIn('email_id', $emailIds)
                ->where('bean_module', '=', 'Leads')
                ->where('deleted', '=', 0)
                ->pluck('bean_id', 'email_id')
                ->all();

            // Group attachments by lead_id
            $result = [];
            foreach ($attachments as $attachment) {
                $leadId = $emailBeanMap[$attachment->email_id] ?? null;
                if ($leadId) {
                    if (! isset($result[$leadId])) {
                        $result[$leadId] = [];
                    }
                    // Add lead_id to attachment data for later use
                    $attachment->lead_id = $leadId;
                    $result[$leadId][] = $attachment;
                }
            }

            return $result;
        } catch (QueryException $e) {
            $this->command->error('SQL Error while extracting email attachments: '.$e->getMessage());
            $this->command->error('SQL: '.$e->getSql());
            $this->command->error('Bindings: '.json_encode($e->getBindings()));
            throw new Exception('Email attachments extraction failed due to SQL error: '.$e->getMessage(), 0, $e);
        } catch (Exception $e) {
            $this->command->error('Failed to extract email attachments: '.$e->getMessage());
            throw new Exception('Email attachments extraction failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Import email attachments as Email attachments instead of Activity files
     *
     * @param  Lead  $lead  The lead to import attachments for
     * @param  array  $emailAttachments  All email attachments grouped by lead ID
     * @param  array  $importedEmailIds  Mapping of SugarCRM email ID to Krayin email ID
     * @return array Statistics about imported and skipped attachments
     */
    public function importEmailAttachmentsAsEmailAttachments(Lead $lead, array $emailAttachments, array $importedEmailIds): array
    {
        $imported = 0;
        $skipped = 0;

        try {
            $leadEmailAttachments = $emailAttachments[$lead->external_id] ?? [];

            if (empty($leadEmailAttachments)) {
                return ['imported' => $imported, 'skipped' => $skipped];
            }

            $this->command->infoV('Importing '.count($leadEmailAttachments)." email attachments for lead {$lead->external_id}");

            foreach ($leadEmailAttachments as $attachmentData) {
                try {
                    // Find the corresponding Krayin email ID
                    $krayinEmailId = $importedEmailIds[$attachmentData->email_id] ?? null;
                    if (! $krayinEmailId) {
                        $this->command->warn("Krayin email not found for attachment {$attachmentData->id}, SugarCRM email_id: {$attachmentData->email_id}");
                        $skipped++;

                        continue;
                    }

                    // Check if attachment already exists
                    $existingAttachment = Attachment::where('email_id', $krayinEmailId)
                        ->where('name', $attachmentData->filename)
                        ->first();

                    if ($existingAttachment) {
                        $this->command->infoVV("Skipping existing email attachment: {$attachmentData->filename}");
                        $skipped++;

                        continue;
                    }

                    // Create file path based on attachment data
                    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $attachmentData->filename);
                    $finalFilename = $this->ensureProperExtension($safeName, $attachmentData->file_mime_type);
                    $filePath = "emails/{$krayinEmailId}/{$attachmentData->id}";

                    // do not donwload the file, will be done later by another process

                    // Create the email attachment record
                    $attachmentRecord = [
                        'name'         => $attachmentData->filename,
                        'path'         => $filePath,
                        'size'         => 1,
                        'content_type' => $attachmentData->file_mime_type ?? 'application/octet-stream',
                        'email_id'     => $krayinEmailId,
                    ];

                    $attachment = $this->createEntityWithTimestamps(Attachment::class, $attachmentRecord, [
                        'created_at' => $this->parseSugarDate($attachmentData->date_entered),
                        'updated_at' => $this->parseSugarDate($attachmentData->date_modified),
                    ]);

                    $this->command->infoV("✓ Imported email attachment: {$attachmentData->filename} for email {$krayinEmailId}");
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

    /**
     * Ensure proper file extension based on mime type
     */
    private function ensureProperExtension(string $filename, ?string $mimeType): string
    {
        // If no mime type, return filename as-is
        if (empty($mimeType)) {
            return $filename;
        }

        // Check if filename already has an extension
        $currentExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Map mime types to extensions
        $mimeToExtension = [
            'application/pdf'                                                           => 'pdf',
            'application/msword'                                                        => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'docx',
            'application/vnd.ms-excel'                                                  => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => 'xlsx',
            'application/vnd.ms-powerpoint'                                             => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain'                                                                => 'txt',
            'text/html'                                                                 => 'html',
            'text/csv'                                                                  => 'csv',
            'image/jpeg'                                                                => 'jpg',
            'image/png'                                                                 => 'png',
            'image/gif'                                                                 => 'gif',
            'application/zip'                                                           => 'zip',
            'application/octet-stream'                                                  => null, // Keep original extension for binary files
        ];

        $expectedExtension = $mimeToExtension[$mimeType] ?? null;

        // If we have an expected extension and the file doesn't have it (or has wrong one)
        if ($expectedExtension && $currentExtension !== $expectedExtension) {
            // If filename has no extension, add the correct one
            if (empty($currentExtension)) {
                return $filename.'.'.$expectedExtension;
            }
            // If filename has wrong extension, replace it
            $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);

            return $nameWithoutExt.'.'.$expectedExtension;
        }

        // For application/octet-stream or unknown types, keep original filename
        return $filename;
    }

    /**
     * Create a minimal valid PDF file
     */
    private function createMinimalPdf($attachmentData): string
    {
        // Create a minimal but valid PDF structure
        $pdf = "%PDF-1.4\n";
        $pdf .= "1 0 obj\n";
        $pdf .= "<<\n";
        $pdf .= "/Type /Catalog\n";
        $pdf .= "/Pages 2 0 R\n";
        $pdf .= ">>\n";
        $pdf .= "endobj\n\n";

        $pdf .= "2 0 obj\n";
        $pdf .= "<<\n";
        $pdf .= "/Type /Pages\n";
        $pdf .= "/Kids [3 0 R]\n";
        $pdf .= "/Count 1\n";
        $pdf .= ">>\n";
        $pdf .= "endobj\n\n";

        $pdf .= "3 0 obj\n";
        $pdf .= "<<\n";
        $pdf .= "/Type /Page\n";
        $pdf .= "/Parent 2 0 R\n";
        $pdf .= "/MediaBox [0 0 612 792]\n";
        $pdf .= "/Contents 4 0 R\n";
        $pdf .= ">>\n";
        $pdf .= "endobj\n\n";

        $content = 'IMPORTED FROM SUGARCRM\\n\\n';
        $content .= 'Original filename: '.($attachmentData->filename ?? 'Unknown').'\\n';
        $content .= 'SugarCRM Note ID: '.($attachmentData->id ?? 'Unknown').'\\n';
        $content .= 'Email ID: '.($attachmentData->email_id ?? 'Unknown').'\\n\\n';
        $content .= 'This PDF was imported from SugarCRM but the original content was not available.\\n';
        $content .= 'To restore: Export original from SugarCRM and replace this file.';

        $pdf .= "4 0 obj\n";
        $pdf .= "<<\n";
        $pdf .= '/Length '.strlen($content)."\n";
        $pdf .= ">>\n";
        $pdf .= "stream\n";
        $pdf .= "BT\n";
        $pdf .= "/F1 12 Tf\n";
        $pdf .= "50 750 Td\n";
        $pdf .= "($content) Tj\n";
        $pdf .= "ET\n";
        $pdf .= "endstream\n";
        $pdf .= "endobj\n\n";

        $pdf .= "xref\n";
        $pdf .= "0 5\n";
        $pdf .= "0000000000 65535 f \n";
        $pdf .= "0000000009 00000 n \n";
        $pdf .= "0000000074 00000 n \n";
        $pdf .= "0000000120 00000 n \n";
        $pdf .= "0000000179 00000 n \n";
        $pdf .= "trailer\n";
        $pdf .= "<<\n";
        $pdf .= "/Size 5\n";
        $pdf .= "/Root 1 0 R\n";
        $pdf .= ">>\n";
        $pdf .= "startxref\n";
        $pdf .= "492\n";
        $pdf .= "%%EOF\n";

        return $pdf;
    }
}
