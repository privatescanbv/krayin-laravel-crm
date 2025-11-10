<?php

namespace Webkul\Email\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;
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
        'sender_email',
        'has_relationships',
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

    /**
     * Get normalized sender email address from the `from` field.
     */
    public function getSenderEmailAttribute(): string
    {
        $from = $this->from;

        $normalizeCandidate = static function ($candidate): string {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                return '';
            }
            // Unwrap JSON-like array string e.g. '["test@example.com"]'
            if (str_starts_with($candidate, '[') && str_ends_with($candidate, ']')) {
                try {
                    $decoded = json_decode($candidate, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && count($decoded) > 0) {
                        $first = $decoded[0];
                        if (is_string($first)) {
                            return trim($first);
                        }
                        if (is_array($first)) {
                            if (!empty($first['value'])) {
                                return trim((string) $first['value']);
                            }
                            if (!empty($first['email'])) {
                                return  trim((string) $first['email']);
                            }
                        }
                    }
                } catch (Throwable) {
                    // ignore and return original candidate
                }
            }
            return$candidate;
        };

        // If cast didn't decode (edge cases), attempt to decode when string looks like JSON
        if (is_string($from)) {
            $trimmed = trim($from);
            if ((str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']'))
                || (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}'))) {
                try {
                    $decoded = json_decode($trimmed, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $from = $decoded;
                    } else {
                        // Not valid JSON, but looks like array syntax - extract content between brackets
                        // e.g. '[test@example.com]' -> 'test@example.com'
                        if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
                            $content = substr($trimmed, 1, -1);
                            return $normalizeCandidate(trim($content));
                        }
                    }
                } catch (Throwable) {
                    // Not valid JSON, but looks like array syntax - extract content between brackets
                    if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
                        $content = substr($trimmed, 1, -1);
                        return $normalizeCandidate(trim($content));
                    }
                }
            } else {
                // Plain string email
                return $normalizeCandidate($trimmed);
            }
        }

        // Array formats
        if (is_array($from)) {
            // Case: array of entries
            if (array_is_list($from) && count($from) > 0) {
                $first = $from[0];
                if (is_string($first)) {
                    return $normalizeCandidate($first);
                }
                if (is_array($first)) {
                    if (!empty($first['value'])) {
                        return $normalizeCandidate($first['value']);
                    }
                    if (!empty($first['email'])) {
                        return $normalizeCandidate($first['email']);
                    }
                    // Map-like: {"email@example.com": "Name"}
                    $keys = array_keys($first);
                    if (!empty($keys) && str_contains((string) $keys[0], '@')) {
                        return trim((string) $keys[0]);
                    }
                }
            }

            // Case: single object
            if (isset($from['value'])) {
                return $normalizeCandidate($from['value']);
            }
            if (isset($from['email'])) {
                return $normalizeCandidate($from['email']);
            }
            $keys = array_keys($from);
            if (!empty($keys) && str_contains((string) $keys[0], '@')) {
                return trim((string) $keys[0]);
            }
        }

        return '';
    }

    /**
     * Whether this email is linked to any entity.
     */
    public function getHasRelationshipsAttribute(): bool
    {
        return $this->person_id || $this->lead_id || $this->sales_lead_id || $this->activity_id;
    }
}
