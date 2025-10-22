<?php

namespace Webkul\Email\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\PersonProxy;
use Webkul\Email\Contracts\Email as EmailContract;
use Webkul\Lead\Models\LeadProxy;
use Webkul\Tag\Models\TagProxy;

class Email extends Model implements EmailContract
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'emails';

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'folders'       => 'array', // Keep for migration compatibility
        'sender'        => 'array',
        'from'          => 'array',
        'reply_to'      => 'array',
        'cc'            => 'array',
        'bcc'           => 'array',
        'reference_ids' => 'array',
    ];

    /**
     * The attributes that are appended.
     *
     * @var array
     */
    protected $appends = [
        'time_ago',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'subject',
        'source',
        'name',
        'user_type',
        'is_read',
        'folders',      // Keep for migration compatibility
        'folder_id',
        'from',
        'sender',
        'reply_to',
        'cc',
        'bcc',
        'unique_id',
        'message_id',
        'reference_ids',
        'reply',
        'person_id',
        'parent_id',
        'lead_id',
        'sales_lead_id',
        'activity_id',
        'created_at',
        'updated_at',
    ];

    protected static function booted()
    {
        static::creating(function (self $email) {
            if (empty($email->source)) {
                $email->source = 'system';
            }
            if (empty($email->message_id)) {
                $email->message_id = (string) Str::uuid();
            }
            if (empty($email->user_type)) {
                $email->user_type = 'user';
            }
        });
    }

    /**
     * Get the parent email.
     */
    public function parent()
    {
        return $this->belongsTo(EmailProxy::modelClass(), 'parent_id');
    }

    /**
     * Get the lead.
     */
    public function lead()
    {
        return $this->belongsTo(LeadProxy::modelClass());
    }

    /**
     * Get the sales lead.
     */
    public function salesLead()
    {
        return $this->belongsTo(\App\Models\SalesLead::class, 'sales_lead_id');
    }

    /**
     * Get the emails.
     */
    public function emails()
    {
        return $this->hasMany(EmailProxy::modelClass(), 'parent_id');
    }

    /**
     * Get the person that owns the thread.
     */
    public function person()
    {
        return $this->belongsTo(PersonProxy::modelClass());
    }

    /**
     * The tags that belong to the lead.
     */
    public function tags()
    {
        return $this->belongsToMany(TagProxy::modelClass(), 'email_tags');
    }

    /**
     * Get the attachments.
     */
    public function attachments()
    {
        return $this->hasMany(AttachmentProxy::modelClass(), 'email_id');
    }

    /**
     * Get the activity associated with this email.
     */
    public function activity()
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    /**
     * Get the folder that contains this email.
     */
    public function folder()
    {
        return $this->belongsTo(FolderProxy::modelClass());
    }

    /**
     * Get the time ago.
     */
    public function getTimeAgoAttribute(): string
    {
        if (!$this->created_at || empty($this->created_at)) {
            return 'Unknown';
        }
        
        return $this->created_at->diffForHumans();
    }
}
