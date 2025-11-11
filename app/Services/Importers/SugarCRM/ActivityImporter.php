<?php

namespace App\Services\Importers\SugarCRM;

use App\Console\Commands\AbstractSugarCRMImport;
use App\Enums\ActivityStatus;
use App\Models\Department;
use App\Services\ActivityStatusService;
use App\Services\Importers\SugarCRM\Concerns\ImportsSugarHelpers;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Webkul\Activity\Models\Activity;
use Webkul\Email\Enums\EmailFolderEnum;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;
use Webkul\Tag\Models\Tag;
use Webkul\User\Models\User;

class ActivityImporter
{
    use ImportsSugarHelpers;

    protected AbstractSugarCRMImport $command;

    protected string $connection;

    public function __construct(AbstractSugarCRMImport $command, string $connection)
    {
        $this->command = $command;
        $this->connection = $connection;
    }

    /**
     * Extract call activities from SugarCRM for the given leads
     *
     * @return array [lead_id => [call_data1, call_data2, ...]]
     *
     * @throws Exception
     */
    public function extractCallActivities(array $leadIds): array
    {
        if (empty($leadIds)) {
            return [];
        }

        try {
            $this->command->validateTableExists($this->connection, ['calls', 'calls_cstm']);

            $sql = DB::connection($this->connection)
                ->table('calls as c')
                ->join('calls_cstm as cc', 'c.id', '=', 'cc.id_c')
                ->select([
                    'c.id',
                    'c.name',
                    'c.date_entered',
                    'c.date_modified',
                    'c.modified_user_id',
                    'c.created_by',
                    'c.description',
                    'c.deleted',
                    'c.assigned_user_id',
                    'c.date_start',
                    'c.date_end',
                    'c.parent_type',
                    'c.status',
                    'c.direction',
                    'c.parent_id',
                    'cc.belgroep_c',
                ])
                ->whereIn('c.parent_id', $leadIds)
                ->where('c.parent_type', '=', 'Leads')
                ->where('c.deleted', '=', 0)
                ->orderBy('c.date_entered', 'asc');

            $this->command->infoVV('Extracting call activities: '.$sql->toRawSql());
            $calls = $sql->get();

            $this->command->infoV('Found '.$calls->count().' call activities');

            // Group calls by parent_id (lead_id)
            $result = [];
            foreach ($calls as $call) {
                if (! isset($result[$call->parent_id])) {
                    $result[$call->parent_id] = [];
                }
                $result[$call->parent_id][] = $call;
            }

            return $result;
        } catch (QueryException $e) {
            $this->command->error('SQL Error while extracting call activities: '.$e->getMessage());
            $this->command->error('SQL: '.$e->getSql());
            throw new Exception('Call activities extraction failed due to SQL error: '.$e->getMessage(), 0, $e);
        } catch (Exception $e) {
            $this->command->error('Failed to extract call activities: '.$e->getMessage());
            throw new Exception('Call activities extraction failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Extract email activities from SugarCRM for the given leads
     *
     * @return array [lead_id => [email_data1, email_data2, ...]]
     */
    public function extractEmailActivities(array $leadIds): array
    {
        if (empty($leadIds)) {
            return [];
        }

        try {
            $this->command->validateTableExists($this->connection, ['emails', 'emails_text', 'emails_beans']);

            $sql = DB::connection($this->connection)
                ->table('emails as e')
                ->join('emails_text as et', 'e.id', '=', 'et.email_id')
                ->join('emails_beans as eb', 'e.id', '=', 'eb.email_id')
                ->select([
                    'e.id',
                    'e.name as subject',
                    'e.date_entered',
                    'e.date_modified',
                    'e.assigned_user_id',
                    'e.created_by',
                    'e.deleted',
                    'e.date_sent',
                    'e.message_id',
                    'e.type',
                    'e.status',
                    'e.flagged',
                    'e.reply_to_status',
                    'e.intent',
                    'e.mailbox_id',
                    'e.parent_type',
                    'e.parent_id',
                    'et.description',
                    'et.description_html',
                    'et.raw_source',
                    'eb.bean_id',
                    'eb.bean_module',
                ])
                ->whereIn('eb.bean_id', $leadIds)
                ->where('eb.bean_module', '=', 'Leads')
                ->where('e.deleted', '=', 0)
                ->where('eb.deleted', '=', 0)
                ->orderBy('e.date_sent', 'asc');

            $this->command->infoVV('Extracting email activities: '.$sql->toRawSql());
            $emails = $sql->get();

            $this->command->infoV('Found '.$emails->count().' email activities');

            // Group emails by bean_id (lead_id)
            $result = [];
            foreach ($emails as $email) {
                if (! isset($result[$email->bean_id])) {
                    $result[$email->bean_id] = [];
                }
                $result[$email->bean_id][] = $email;
            }

            return $result;
        } catch (Exception $e) {
            $this->command->error('Failed to extract email activities: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Import call activities for a lead
     *
     * @param  Lead  $lead  The lead to import activities for
     * @param  array  $callActivities  All call activities grouped by lead ID
     * @return array Statistics about imported and skipped activities
     */
    public function importCallActivities(Lead $lead, array $callActivities): array
    {
        $imported = 0;
        $skipped = 0;

        try {
            $leadCallActivities = $callActivities[$lead->external_id] ?? [];

            if (empty($leadCallActivities)) {
                return ['imported' => $imported, 'skipped' => $skipped];
            }

            $this->command->infoV('Importing '.count($leadCallActivities)." call activities for lead {$lead->external_id}");

            foreach ($leadCallActivities as $callData) {
                try {
                    // Check if activity already exists by external reference
                    $existingActivity = Activity::where('external_id', $callData->id)->first();
                    if ($existingActivity) {
                        $this->command->infoV("Skipping existing call activity with external_id={$callData->id}");
                        $skipped++;

                        continue;
                    }

                    // Get group_id from lead's department (will throw exception if invalid)
                    $groupId = Department::getGroupIdForLead($lead);

                    // Create the activity
                    $activityData = [
                        'title'       => $callData->name ?? 'Bel activiteit',
                        'type'        => 'call',
                        'comment'     => $callData->description ?? '',
                        'external_id' => $callData->id,
                        'additional'  => [
                            'direction'   => $callData->direction,
                            'status'      => $callData->status,
                            'belgroep'    => $callData->belgroep_c,
                        ],
                        'schedule_from' => $this->parseSugarDate($callData->date_start),
                        'schedule_to'   => $this->parseSugarDate($callData->date_end),
                        'is_done'       => $this->mapCallStatus($lead->stage, $callData->status),
                        'user_id'       => $this->mapAssignedUser($callData->assigned_user_id),
                        'lead_id'       => $lead->id,
                        'group_id'      => $groupId,
                    ];

                    $timestamps = [
                        'created_at' => $this->parseSugarDate($callData->date_entered),
                        'updated_at' => $this->parseSugarDate($callData->date_modified),
                    ];

                    $activity = $this->createEntityWithTimestamps(Activity::class, $activityData, $timestamps);

                    // Keep status consistent with is_done and dates without touching timestamps
                    $this->syncActivityStatus($activity);

                    $this->command->infoV("✓ Imported call activity: {$callData->name} for lead {$lead->external_id}");
                    $imported++;
                } catch (Exception $e) {
                    $this->command->error("Failed to import call activity {$callData->id}: ".$e->getMessage());
                    // Continue with next call activity
                }
            }
        } catch (Exception $e) {
            $this->command->error("Failed to import call activities for lead {$lead->external_id}: ".$e->getMessage());
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * Import email activities for a lead
     *
     * @param  Lead  $lead  The lead to import activities for
     * @param  array  $emailActivities  All email activities grouped by lead ID
     * @return array Statistics about imported and skipped activities
     */
    public function importEmailActivities(Lead $lead, array $emailActivities): array
    {
        $imported = 0;
        $skipped = 0;

        try {
            $leadEmailActivities = $emailActivities[$lead->external_id] ?? [];

            if (empty($leadEmailActivities)) {
                return ['imported' => $imported, 'skipped' => $skipped];
            }

            $this->command->infoV('Importing '.count($leadEmailActivities)." email activities for lead {$lead->external_id}");

            foreach ($leadEmailActivities as $emailData) {
                try {
                    // Check if activity already exists by external reference
                    $existingActivity = Activity::where('external_id', $emailData->id)->first();
                    if ($existingActivity) {
                        $this->command->infoV("Skipping existing email activity with external_id={$emailData->id}");
                        $skipped++;

                        continue;
                    }

                    // Get group_id from lead's department (will throw exception if invalid)
                    $groupId = Department::getGroupIdForLead($lead);

                    // Create the activity
                    $activityData = [
                        'title'       => $emailData->subject ?? 'Email activiteit',
                        'type'        => 'email',
                        'comment'     => $this->formatEmailContent($emailData),
                        'external_id' => $emailData->id,
                        'additional'  => [
                            'message_id'       => $emailData->message_id,
                            'email_type'       => $emailData->type,
                            'status'           => $emailData->status,
                            'flagged'          => (bool) $emailData->flagged,
                            'reply_to_status'  => $emailData->reply_to_status,
                            'intent'           => $emailData->intent,
                            'mailbox_id'       => $emailData->mailbox_id,
                            'parent_type'      => $emailData->parent_type,
                            'parent_id'        => $emailData->parent_id,
                        ],
                        'schedule_from' => $this->parseSugarDate($emailData->date_sent),
                        'schedule_to'   => $this->parseSugarDate($emailData->date_sent),
                        'is_done'       => $this->mapEmailStatus($emailData->status),
                        'user_id'       => $this->mapAssignedUser($emailData->assigned_user_id),
                        'lead_id'       => $lead->id,
                        'group_id'      => $groupId,
                    ];

                    $timestamps = [
                        'created_at' => $this->parseSugarDate($emailData->date_entered),
                        'updated_at' => $this->parseSugarDate($emailData->date_modified),
                    ];

                    $activity = $this->createEntityWithTimestamps(Activity::class, $activityData, $timestamps);

                    // Keep status consistent with is_done and dates
                    if ($activity->is_done) {
                        $activity->status = ActivityStatus::DONE;
                    } else {
                        $activity->status = ActivityStatusService::computeStatus($activity->schedule_from, $activity->schedule_to, ActivityStatus::ACTIVE);
                    }
                    $activity->saveQuietly();

                    $this->command->infoV("✓ Imported email activity: {$emailData->subject} for lead {$lead->external_id}");
                    $imported++;
                } catch (Exception $e) {
                    $this->command->error("Failed to import email activity {$emailData->id}: ".$e->getMessage());
                    // Continue with next email activity
                }
            }
        } catch (Exception $e) {
            $this->command->error("Failed to import email activities for lead {$lead->external_id}: ".$e->getMessage());
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * Import email activities as Email records with import tag
     *
     * @param  Lead  $lead  The lead to import emails for
     * @param  array  $emailActivities  All email activities grouped by lead ID
     * @return array Statistics about imported and skipped emails and their IDs
     */
    public function importEmailsAsEmailRecords(Lead $lead, array $emailActivities): array
    {
        $imported = 0;
        $skipped = 0;
        $importedEmailIds = [];

        try {
            $leadEmailActivities = $emailActivities[$lead->external_id] ?? [];

            if (empty($leadEmailActivities)) {
                return ['imported' => $imported, 'skipped' => $skipped, 'email_ids' => $importedEmailIds];
            }

            // Get or create 'import' tag
            $importTag = Tag::firstOrCreate(
                ['name' => 'import'],
                ['color' => '#6B7280', 'user_id' => 1] // Default user ID
            );

            $this->command->infoV('Importing '.count($leadEmailActivities)." emails for lead {$lead->external_id}");

            foreach ($leadEmailActivities as $emailData) {
                try {
                    // Check if email already exists by external reference
                    $existingEmail = Email::where('unique_id', $emailData->id)->first();
                    if ($existingEmail) {
                        $this->command->infoV("Skipping existing email with unique_id={$emailData->id}");
                        $skipped++;
                        $importedEmailIds[$emailData->id] = $existingEmail->id;

                        continue;
                    }

                    // Create email content from SugarCRM data
                    $emailContent = $this->formatEmailContent($emailData);

                    // Create the email record
                    $emailRecord = [
                        'subject'      => $emailData->subject ?? 'Email',
                        'source'       => 'web',
                        'name'         => $emailData->subject ?? 'Email',
                        'user_type'    => 'person',
                        'is_read'      => 1,
                        'folder_id'    => $this->getImportedFolderId(), // Don't show in inbox, only visible from lead view
                        'from'         => ['name' => 'SugarCRM Import', 'email' => 'import@sugarcrm.local'],
                        'sender'       => ['name' => 'SugarCRM Import', 'email' => 'import@sugarcrm.local'],
                        'reply_to'     => [],
                        'cc'           => [],
                        'bcc'          => [],
                        'reply'        => $emailContent,
                        'unique_id'    => $emailData->id,
                        'message_id'   => $emailData->message_id,
                        'parent_id'    => null, // Main email, not a reply
                        'lead_id'      => $lead->id,
                    ];

                    $timestamps = [
                        'created_at' => $this->parseSugarDate($emailData->date_entered),
                        'updated_at' => $this->parseSugarDate($emailData->date_modified),
                    ];

                    $email = $this->createEntityWithTimestamps(Email::class, $emailRecord, $timestamps);

                    // Attach import tag to email
                    $email->tags()->attach($importTag->id);

                    $this->command->infoV("✓ Imported email: {$emailData->subject} for lead {$lead->external_id} (Email ID: {$email->id})");
                    $imported++;
                    $importedEmailIds[$emailData->id] = $email->id; // Map SugarCRM email ID to Krayin email ID
                } catch (Exception $e) {
                    $this->command->error("Failed to import email {$emailData->id}: ".$e->getMessage());
                    // Continue with next email
                }
            }
        } catch (Exception $e) {
            $this->command->error("Failed to import emails for lead {$lead->external_id}: ".$e->getMessage());
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'email_ids' => $importedEmailIds];
    }

    /**
     * Get the imported folder ID
     *
     * @return int|null
     */
    protected function getImportedFolderId()
    {
        $folder = Folder::where('name', EmailFolderEnum::PROCESSED->getFolderName())->first();

        return $folder ? $folder->id : null;
    }

    /**
     * Format email content for activity comment
     */
    private function formatEmailContent($emailData): string
    {
        $content = [];
        // Prefer HTML content, fallback to plain text
        $body = $emailData->description_html ?? $emailData->description ?? '';
        if (! empty($body)) {
            // Strip HTML tags for plain text storage in comment
            $plainBody = strip_tags($body);
            // Limit length to prevent extremely long comments
            if (strlen($plainBody) > 1000) {
                $plainBody = substr($plainBody, 0, 1000).'...';
            }
            $content[] = $plainBody;
        }

        return implode("\n\n", $content);
    }

    /**
     * Map email status to is_done boolean
     */
    private function mapEmailStatus(?string $status): bool
    {
        if (! $status) {
            return false;
        }

        // Email is considered "done" if it's sent or archived
        $completedStatuses = ['sent', 'archived', 'delivered'];

        return in_array(strtolower(trim($status)), $completedStatuses);
    }

    /**
     * Map call status to is_done boolean
     */
    private function mapCallStatus(Stage $stage, ?string $status): bool
    {
        if ($stage->is_lost || $stage->is_won) {
            return true;
        }
        if (! $status) {
            return false;
        }

        $completedStatuses = ['held', 'completed', 'done', 'finished'];

        return in_array(strtolower(trim($status)), $completedStatuses);
    }

    /**
     * Map assigned user ID from SugarCRM to existing user by external_id
     *
     * @param  string|null  $assignedUserId  The SugarCRM user ID
     * @return int|null The user ID to assign
     *
     * @throws Exception when user could not be found by external_id
     */
    private function mapAssignedUser(?string $assignedUserId): ?int
    {
        if (empty($assignedUserId)) {
            return null;
        }
        // Look up user by external_id
        $user = User::where('external_id', $assignedUserId)->first();
        if (is_null($user)) {
            throw new Exception('User not found by external_id: '.$assignedUserId);
        }

        $this->command->infoV("Mapped assigned user {$assignedUserId} to user: {$user->name} (ID: {$user->id})");

        return $user->id;
    }
}
