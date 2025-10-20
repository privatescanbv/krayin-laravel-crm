<?php

namespace Webkul\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;

class LeadKanbanResource extends JsonResource
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
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'name'                 => $this->name,
            'first_name'           => $this->first_name,
            'last_name'            => $this->last_name,
            'created_at'           => $this->created_at?->format('Y-m-d H:i:s'),
            'rotten_days'          => $this->rotten_days,
            'lost_reason_label'    => $this->lost_reason?->label() ?? null,
            'mri_status'           => $this->mri_status,
            'mri_status_label'     => $this->mri_status?->label() ?? null,
            'has_diagnosis_form'   => (bool) ($this->has_diagnosis_form ?? false),

            // Relationships - only what's needed for kanban
            'persons'              => [],
            'persons_count'        => 0,
            'has_multiple_persons' => false,
            'stage'                => $this->stage ? new StageResource($this->stage) : null,

            // Computed attributes - simplified for performance
            'open_activities_count'=> 0,
            'unread_emails_count'  => 0,
            'days_until_due_date'  => null,
            'has_duplicates'       => false,
            'duplicates_count'     => 0,
        ];
    }
}
