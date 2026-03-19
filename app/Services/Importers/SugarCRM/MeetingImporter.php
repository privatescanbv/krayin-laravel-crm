<?php

namespace App\Services\Importers\SugarCRM;

use App\Console\Commands\AbstractSugarCRMImport;
use App\Enums\ActivityType;
use App\Models\Department;
use App\Services\Importers\SugarCRM\Concerns\ImportsSugarHelpers;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Webkul\Activity\Models\Activity;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

class MeetingImporter
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
     * Extract meeting activities from SugarCRM for the given leads
     *
     * @return array [lead_id => [meeting_data1, meeting_data2, ...]]
     *
     * @throws Exception
     */
    public function extractMeetingActivities(array $leadIds): array
    {
        if (empty($leadIds)) {
            return [];
        }
        // disable for now, pick this up later with import orders.

        try {
            $this->command->validateTableExists($this->connection, ['meetings']);

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

            $this->command->infoVV('Extracting meeting activities: '.$sql->toRawSql());
            $meetings = $sql->get();

            $this->command->infoV('Found '.$meetings->count().' meeting activities');

            // Group meetings by parent_id (lead_id)
            $result = [];
            foreach ($meetings as $meeting) {
                if (! isset($result[$meeting->parent_id])) {
                    $result[$meeting->parent_id] = [];
                }
                $result[$meeting->parent_id][] = $meeting;
            }

            return $result;
        } catch (QueryException $e) {
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

            $this->command->infoV('Importing '.count($leadMeetingActivities)." meeting activities for lead {$lead->external_id}");

            foreach ($leadMeetingActivities as $meetingData) {
                try {
                    // Check if activity already exists by external reference
                    $existingActivity = Activity::where('external_id', $meetingData->id)->first();
                    if ($existingActivity) {
                        $this->command->infoV("Skipping existing meeting activity with external_id={$meetingData->id}");
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
                        'type'        => ActivityType::TASK->value,
                        'comment'     => $meetingData->description ?? '',
                        'external_id' => $meetingData->id,
                        'additional'  => [
                            'status'                 => $meetingData->status,
                            'duration_hours'         => $meetingData->duration_hours,
                            'duration_minutes'       => $meetingData->duration_minutes,
                            'duration_total_minutes' => $durationMinutes,
                            'reminder_time'          => $meetingData->reminder_time,
                        ],
                        'schedule_from' => $this->parseSugarDate($meetingData->date_start),
                        'schedule_to'   => $this->parseSugarDate($meetingData->date_end),
                        'is_done'       => $this->mapMeetingStatusToIsDone($lead->stage, $meetingData->status),
                        'user_id'       => $this->mapAssignedUser($meetingData->assigned_user_id),
                        'lead_id'       => $lead->id,
                        'group_id'      => $groupId,
                    ];

                    $timestamps = [
                        'created_at' => $this->parseSugarDate($meetingData->date_entered),
                        'updated_at' => $this->parseSugarDate($meetingData->date_modified),
                    ];

                    $activity = $this->createEntityWithTimestamps(Activity::class, $activityData, $timestamps);

                    // Keep status consistent with is_done and dates without touching timestamps
                    $this->syncActivityStatus($activity);

                    $this->command->infoV("✓ Imported meeting activity: {$meetingData->name} for lead {$lead->external_id}");
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
     *
     * SugarCRM meeting status values:
     * - "Held": Meeting was held (completed) -> is_done = true
     * - "Not Held": Meeting was not held -> is_done = false
     * - "Planned": Meeting is planned/scheduled -> is_done = false
     */
    private function mapMeetingStatusToIsDone(Stage $stage, ?string $status): bool
    {
        if ($stage->is_lost || $stage->is_won) {
            return true;
        }
        if (! $status) {
            return false;
        }

        // Only "Held" status means the meeting is done/completed
        // Handle case-insensitive matching for SugarCRM data
        return strtolower(trim($status)) === 'held';
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
