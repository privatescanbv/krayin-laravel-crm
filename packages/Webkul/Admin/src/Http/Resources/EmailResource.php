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
            'is_read'       => $this->is_read,
            'folders'       => $this->folders,
            'from'          => $this->from,
            'sender_email'  => $this->sender_email,
            'has_relationships' => $this->has_relationships,
            'sender'        => $this->sender,
            'reply_to'      => $this->reply_to,
            'cc'            => $this->cc,
            'bcc'           => $this->bcc,
            'unique_id'     => $this->unique_id,
            'message_id'    => $this->message_id,
            'reference_ids' => $this->reference_ids,
            'person'        => $this->person ? new PersonResource($this->person) : null,
            'lead'          => $this->lead ? new LeadResource($this->lead) : null,
            'parent_id'     => $this->parent_id,
            'parent'        => $this->parent ? new EmailResource($this->parent) : null,
            'attachments'   => EmailAttachmentResource::collection($this->attachments),
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
