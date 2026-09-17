<?php

namespace Webkul\Admin\Http\Resources;

use BackedEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadKanbanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request): array
    {
        $gender = $this->gender;
        $genderValue = $gender instanceof BackedEnum ? $gender->value : $gender;

        $includeDuplicates = filter_var($request->query('include_duplicates', false), FILTER_VALIDATE_BOOLEAN);
        $duplicatesCount = $includeDuplicates ? $this->getPotentialDuplicatesCount() : 0;

        return [
            'id'                   => $this->id,
            'user_id'              => $this->user_id,
            'department_id'        => $this->department_id,
            'name'                 => $this->name,
            'first_name'           => $this->first_name,
            'last_name'            => $this->last_name,
            'created_at'           => $this->created_at?->format('Y-m-d H:i:s'),
            'rotten_days'          => $this->rotten_days,
            'lost_reason_label'    => $this->lost_reason?->label() ?? null,
            'mri_status'           => $this->mri_status,
            'mri_status_label'     => $this->mri_status?->label() ?? null,
            'has_diagnosis_form'   => (bool) ($this->has_diagnosis_form ?? false),

            'date_of_birth'        => $this->date_of_birth?->format('Y-m-d'),
            'gender'               => $genderValue,
            'age'                  => $this->age,

            'persons'              => [],
            'persons_count'        => 0,
            'has_multiple_persons' => false,
            'stage'                => $this->stage ? new StageResource($this->stage) : null,

            'open_activities_count' => (int) ($this->open_activities_count_query ?? 0),
            'unread_emails_count'  => (int) ($this->open_email_count_query ?? 0),
            'days_until_due_date'  => null,
            'has_duplicates'       => $duplicatesCount > 0,
            'duplicates_count'     => $duplicatesCount,
            'duplicate'            => $duplicatesCount,
        ];
    }
}
