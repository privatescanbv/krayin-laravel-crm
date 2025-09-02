<?php

namespace App\Services\Importers\SugarCRM;

use App\Models\Department;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Activity\Models\Activity;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

class MeetingImporter
{
    protected Command $command;

    protected string $connection;

    public function __construct(Command $command, string $connection)
    {
        $this->command = $command;
        $this->connection = $connection;
    }

    /**
     * Extract meeting activities from SugarCRM for the given leads
     *
     * @param  mixed  $records  The lead records
     * @return array [lead_id => [meeting_data1, meeting_data2, ...]]
     */
    public function extractMeetingActivities($records): array
    {
        $leadIds = collect($records)->pluck('id')->all();

        if (empty($leadIds)) {
            return [];
        }

        try {
            // Check if meetings table exists
            if (! Schema::connection($this->connection)->hasTable('meetings')) {
                $this->command->info('Meetings table does not exist in SugarCRM database, skipping meeting activities import');

                return [];
            }

            $sql = DB::connection($this->connection)
                ->table('meetings as m')
                ->select([
                    'm.id',
                    'm.name',
                    'm.date_entered',
                    'm.date_modified',
                    'm.modified_user_id',
                    'm.created_by',
                    'm.description',
                    'm.deleted',
                    'm.assigned_user_id',
                    'm.duration_hours',
                    'm.duration_minutes',
                    'm.date_start',
                    'm.date_end',
                    'm.parent_type',
                    'm.status',
                    'm.parent_id',
                    'm.reminder_time',
                ])
                ->whereIn('m.parent_id', $leadIds)
                ->where('m.parent_type', '=', 'Leads')
                ->where('m.deleted', '=', 0)
                ->orderBy('m.date_entered', 'asc');

            $this->command->info('Extracting meeting activities: '.$sql->toRawSql());
            $meetings = $sql->get();

            $this->command->info('Found '.$meetings->count().' meeting activities');

            // Group meetings by parent_id (lead_id)
            $result = [];
            foreach ($meetings as $meeting) {
                if (! isset($result[$meeting->parent_id])) {
                    $result[$meeting->parent_id] = [];
                }
                $result[$meeting->parent_id][] = $meeting;
            }

            return $result;
        } catch (\Illuminate\Database\QueryException $e) {
            $this->command->error('SQL Error while extracting meeting activities: '.$e->getMessage());
            $this->command->error('SQL: '.$e->getSql());
            throw new Exception('Meeting activities extraction failed due to SQL error: '.$e->getMessage(), 0, $e);
        } catch (Exception $e) {
            $this->command->error('Failed to extract meeting activities: '.$e->getMessage());
            throw new Exception('Meeting activities extraction failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Import meeting activities for a lead
     *
     * @param  Lead  $lead  The lead to import activities for
     * @param  array  $meetingActivities  All meeting activities grouped by lead ID
     * @return array Statistics about imported and skipped activities
     */
    public function importMeetingActivities(Lead $lead, array $meetingActivities): array
    {
        $imported = 0;
        $skipped = 0;

        try {
            $leadMeetingActivities = $meetingActivities[$lead->external_id] ?? [];

            if (empty($leadMeetingActivities)) {
                return ['imported' => $imported, 'skipped' => $skipped];
            }

            $this->command->info('Importing '.count($leadMeetingActivities)." meeting activities for lead {$lead->external_id}");

            foreach ($leadMeetingActivities as $meetingData) {
                try {
                    // Check if activity already exists by external reference
                    $existingActivity = Activity::where('external_id', $meetingData->id)->first();
                    if ($existingActivity) {
                        $this->command->info("Skipping existing meeting activity with external_id={$meetingData->id}");
                        $skipped++;

                        continue;
                    }

                    // Get group_id from lead's department (will throw exception if invalid)
                    $groupId = Department::getGroupIdForLead($lead);

                    // Calculate duration in minutes
                    $durationMinutes = ($meetingData->duration_hours ?? 0) * 60 + ($meetingData->duration_minutes ?? 0);

                    // Create the activity
                    $activityData = [
                        'title'       => $meetingData->name ?? 'Afspraak',
                        'type'        => 'meeting',
                        'comment'     => $meetingData->description ?? '',
                        'external_id' => $meetingData->id,
                        'additional'  => [
                            'status'           => $meetingData->status,
                            'duration_hours'   => $meetingData->duration_hours,
                            'duration_minutes' => $meetingData->duration_minutes,
                            'duration_total_minutes' => $durationMinutes,
                            'reminder_time'    => $meetingData->reminder_time,
                        ],
                        'schedule_from' => $this->parseSugarDate($meetingData->date_start),
                        'schedule_to'   => $this->parseSugarDate($meetingData->date_end),
                        'is_done'       => $this->mapMeetingStatus($meetingData->status),
                        'user_id'       => $this->mapAssignedUser($meetingData->assigned_user_id),
                        'lead_id'       => $lead->id,
                        'group_id'      => $groupId,
                    ];

                    $timestamps = [
                        'created_at' => $this->parseSugarDate($meetingData->date_entered),
                        'updated_at' => $this->parseSugarDate($meetingData->date_modified),
                    ];

                    $activity = $this->createEntityWithTimestamps(Activity::class, $activityData, $timestamps);

                    $this->command->info("✓ Imported meeting activity: {$meetingData->name} for lead {$lead->external_id}");
                    $imported++;
                } catch (Exception $e) {
                    $this->command->error("Failed to import meeting activity {$meetingData->id}: ".$e->getMessage());
                    // Continue with next meeting activity
                }
            }
        } catch (Exception $e) {
            $this->command->error("Failed to import meeting activities for lead {$lead->external_id}: ".$e->getMessage());
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * Map meeting status to is_done boolean
     */
    private function mapMeetingStatus(?string $status): bool
    {
        if (! $status) {
            return false;
        }

        // Meeting is considered "done" if it's held or completed
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

        $this->command->info("Mapped assigned user {$assignedUserId} to user: {$user->name} (ID: {$user->id})");

        return $user->id;
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