<?php

namespace Webkul\Admin\Http\Resources;

use Exception;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;

class LeadResource extends JsonResource
{
    private readonly LeadRepository $leadRepository;
    public function __construct(Lead $lead)
    {
        parent::__construct($lead);
        $this->leadRepository = app(LeadRepository::class);
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        $duplicatesCount = $this->getPotentialDuplicatesCount();
        return [
            'id'                   => $this->id,
            'name'                 => $this->name,
            'description'          => $this->description,
            'status'               => $this->status,
            'mri_status'           => $this->mri_status,
            'mri_status_label'     => $this->mri_status?->label() ?? null,
            'has_diagnosis_form'   => (bool) ($this->has_diagnosis_form ?? false),
            'lost_reason'          => $this->lost_reason?->value,
            'lost_reason_label'    => $this->lost_reason?->label() ?? null,
            'closed_at'            => $this->closed_at?->format('Y-m-d H:i:s'),
            'rotten_days'          => $this->rotten_days,
            'created_at'           => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'           => $this->updated_at?->format('Y-m-d H:i:s'),

            // Personal information (for matching purposes)
            'salutation'           => $this->salutation,
            'first_name'           => $this->first_name,
            'last_name'            => $this->last_name,
            'lastname_prefix'      => $this->lastname_prefix,
            'married_name'         => $this->married_name,
            'married_name_prefix'  => $this->married_name_prefix,
            'initials'             => $this->initials,
            'date_of_birth'        => $this->date_of_birth?->format('Y-m-d'),
            'gender'               => $this->gender,

            // Contact information (for matching purposes)
            'emails'               => is_array($this->emails) ? $this->emails : [],
            'phones'               => is_array($this->phones) ? $this->phones : [],

            // Relationships
            'persons'              => PersonResource::collection($this->persons ?? []),
            'persons_count'        => $this->persons_count ?? 0,
            'has_multiple_persons' => $this->hasMultiplePersons(),
            'organization'         => $this->organization ? new OrganizationResource($this->organization) : null,
            'user'                 => $this->user ? new UserResource($this->user) : null,
            'type'                 => $this->type ? new TypeResource($this->type) : null,
            'source'               => $this->source ? new SourceResource($this->source) : null,
            'pipeline'             => $this->pipeline ? new PipelineResource($this->pipeline) : null,
            'stage'                => $this->stage ? new StageResource($this->stage) : null,
            'address'              => $this->address ? new AddressResource($this->address) : null,
            'tags'                 => TagResource::collection($this->tags),

            // Computed attributes
            'open_activities_count'=> $this->open_activities_count ?? 0,
            // Include unread emails from direct lead emails plus nested activity emails
            'unread_emails_count'  => $this->getUnreadEmailsCountNestedAttribute() ?? 0,
            'days_until_due_date'  => $this->days_until_due_date,
            'has_duplicates'       => $duplicatesCount > 0,
            'duplicates_count'     => $duplicatesCount,

            // IDs for relationships
            'user_id'              => $this->user_id,
            'person_id'            => $this->person_id,
            'lead_source_id'       => $this->lead_source_id,
            'lead_type_id'         => $this->lead_type_id,
            'lead_pipeline_id'     => $this->lead_pipeline_id,
            'lead_pipeline_stage_id'=> $this->lead_pipeline_stage_id,
            'lead_channel_id'      => $this->lead_channel_id,
            'department_id'        => $this->department_id,
            'created_by'           => $this->created_by,
            'updated_by'           => $this->updated_by,
        ];
    }
}
