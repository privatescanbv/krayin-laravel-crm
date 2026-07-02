<?php

namespace Webkul\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EmailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id'            => $this->id,
            'subject'       => $this->subject,
            'source'        => $this->source,
            'user_type'     => $this->user_type,
            'name'          => $this->name,
            'reply'         => $this->reply,
            'quote_split'   => $this->quote_split,
            'is_read'       => $this->is_read,
            'folders'       => $this->folders,
            'from'          => $this->from,
            'sender_email'  => $this->sender_email,
            'has_relationships' => $this->has_relationships,
            'sender'        => $this->sender,
            'reply_to'      => $this->reply_to,
            'to_display'    => $this->to_display,
            'cc'            => $this->cc,
            'bcc'           => $this->bcc,
            'unique_id'     => $this->unique_id,
            'message_id'    => $this->message_id,
            'reference_ids' => $this->reference_ids,
            'llm_metadata'  => $this->llm_metadata,
            'person_id'     => $this->person_id,
            'person'        => $this->person ? new PersonResource($this->person) : null,
            'lead_id'       => $this->lead_id,
            'lead'          => $this->lead ? new LeadResource($this->lead) : null,
            'sales_lead_id' => $this->sales_lead_id,
            'clinic_id'     => $this->clinic_id,
            'activity_id'   => $this->activity_id,
            'order_id'      => $this->order_id,
            'order'         => $this->order ? [
                'id'    => $this->order->id,
                'title' => $this->order->title ?? $this->order->name ?? null,
            ] : null,
            'parent_id'     => $this->parent_id,
            'parent'        => $this->parent ? new EmailResource($this->parent) : null,
            'attachments'   => EmailAttachmentResource::collection($this->attachments),
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
