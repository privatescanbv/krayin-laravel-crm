<?php

namespace Webkul\Email\Models;

use App\Helpers\ValueNormalizer;
use App\Models\Clinic;
use App\Models\SalesLead;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;
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
        'clinic_id',
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
        return $this->belongsTo(SalesLead::class, 'sales_lead_id');
    }

    /**
     * Get the clinic.
     */
    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
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
     *
     * The `from` field should be in the standardized format: {"name": "...", "email": "..."}
     * This method extracts the email address from this structure.
     */
    public function getSenderEmailAttribute(): string
    {
        $from = $this->from;

        // Handle legacy string format (for backward compatibility)
        if (is_string($from)) {
            $trimmed = trim($from);
            // If it looks like JSON, try to decode it
            if ((str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}'))
                || (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']'))) {
                try {
                    $decoded = json_decode($trimmed, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $from = $decoded;
                    } else {
                        // Not valid JSON, return as plain string
                        return $trimmed;
                    }
                } catch (Throwable) {
                    // Not valid JSON, return as plain string
                    return $trimmed;
                }
            } else {
                // Plain string email (legacy format)
                return $trimmed;
            }
        }

        // Standard format: {"name": "...", "email": "..."}
        if (is_array($from)) {
            // Check for the standardized structure first
            if (isset($from['email'])) {
                return trim((string) $from['email']);
            }

            // Legacy array formats (for backward compatibility)
            if (isset($from['value'])) {
                return trim((string) $from['value']);
            }

            // Handle array of entries (legacy)
            if (array_is_list($from) && count($from) > 0) {
                $first = $from[0];
                if (is_string($first)) {
                    return trim($first);
                }
                if (is_array($first)) {
                    if (isset($first['email'])) {
                        return trim((string) $first['email']);
                    }
                    if (isset($first['value'])) {
                        return trim((string) $first['value']);
                    }
                }
            }

            // Map-like format: {"email@example.com": "Name"} (legacy)
            $keys = array_keys($from);
            if (!empty($keys) && str_contains((string) $keys[0], '@')) {
                return trim((string) $keys[0]);
            }
        }

        return '';
    }

    /**
     * Normalize reply_to to always be an array of email strings (for Vue component compatibility).
     * Handles legacy formats where reply_to might be an object with 'email' key.
     *
     * This accessor ensures backward compatibility with old records that may have
     * reply_to stored as {"email": "..."} instead of ["..."].
     */
    public function getReplyToAttribute($value)
    {
        // Get the raw value from attributes (before cast)
        $rawValue = $this->attributes['reply_to'] ?? null;

        // If explicitly null (not set), return null (for test compatibility)
        if ($rawValue === null) {
            return null;
        }

        // If empty string or empty array, return empty array
        if (empty($rawValue)) {
            return [];
        }

        // Decode JSON if it's a string
        if (is_string($rawValue)) {
            $decoded = json_decode($rawValue, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $replyTo = $decoded;
            } else {
                return [$rawValue];
            }
        } else {
            // Use the value passed (already cast by Laravel)
            $replyTo = $value;
        }

        // If already an array of strings, return as is
        if (is_array($replyTo) && array_is_list($replyTo) && !empty($replyTo) && is_string($replyTo[0] ?? null)) {
            return array_values(array_filter($replyTo));
        }

        // If it's an object with 'email' key (legacy format from old PatientMailService)
        if (is_array($replyTo) && !array_is_list($replyTo) && isset($replyTo['email']) && is_string($replyTo['email'])) {
            return [$replyTo['email']];
        }

        // If it's an array of objects, extract email addresses
        if (is_array($replyTo) && array_is_list($replyTo)) {
            $emails = [];
            foreach ($replyTo as $item) {
                if (is_string($item)) {
                    $emails[] = $item;
                } elseif (is_array($item)) {
                    if (isset($item['email'])) {
                        $emails[] = $item['email'];
                    } elseif (isset($item['address'])) {
                        $emails[] = $item['address'];
                    }
                }
            }
            return array_values(array_filter($emails));
        }

        // Fallback: return empty array
        return [];
    }

    /**
     * Whether this email is linked to any entity.
     */
    public function getHasRelationshipsAttribute(): bool
    {
        return $this->person_id || $this->lead_id || $this->sales_lead_id || $this->clinic_id;
    }

    /**
     * Normalize name attribute to always return a string.
     * Handles cases where name might be stored as an object or array.
     * This ensures Vue components receive a string value.
     *
     * @param mixed $value
     * @return string
     */
    public function getNameAttribute($value): string
    {
        return ValueNormalizer::toString($value);
    }

    /**
     * Normalize and validate the 'from' field to the standard structure.
     *
     * The standard structure is: {"name": "...", "email": "..."}
     * If name is not available, it will be an empty string.
     *
     * @param  string|null  $email
     * @param  string|null  $name
     * @return array
     * @throws \Exception
     */
    public static function normalizeFromField(?string $email, ?string $name = null): array
    {
        // Validate email is not empty
        $email = trim((string) $email);
        if (empty($email)) {
            throw new Exception('Email address is required for the from field');
        }

        // Validate email format
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address format: {$email}");
        }

        // Normalize name (empty string if not provided or empty)
        $name = trim((string) $name);
        if (empty($name)) {
            $name = '';
        }

        return [
            'name'  => $name,
            'email' => $email,
        ];
    }
    /**
     * Scope: all emails (parents + replies) for a given lead.
     */
    public function scopeForLeadThread(Builder $query, int $leadId): Builder
    {
        // Subquery: parents that belong to the lead
        $parentEmails = static::query()
            ->select('id')
            ->where('lead_id', $leadId);

        return $query
            ->whereIn('parent_id', $parentEmails)
            ->orWhere('lead_id', $leadId);
    }

    public function scopeForClinicThread(Builder $query, int $clinicId): Builder
    {
        // Subquery: parents that belong to the lead
        $parentEmails = static::query()
            ->select('id')
            ->where('clinic_id', $clinicId);

        return $query
            ->whereIn('parent_id', $parentEmails)
            ->orWhere('clinic_id', $clinicId);
    }

    public function scopeForPersonThread(Builder $query, int $personId, array $leadIds): Builder
    {
        // Subquery: parents that belong to the lead
        $parentEmails = static::query()
            ->select('id')
            ->where('person_id', $personId)
            ->orWhereIn('lead_id', $leadIds);

        return $query
            ->whereIn('parent_id', $parentEmails)
            ->orWhere('person_id', $personId)
            ->orWhereIn('lead_id', $leadIds);
    }

    public function scopeForLeadThreadAndUnread(Builder $query, int $leadId): Builder
    {
        return self::forLeadThread($leadId)
            ->where('is_read', 0);
    }

    public function getThreadRoot(): self
    {
        $email = $this;

        while ($email->parent_id) {
            $email = $email->parent;
        }

        return $email;
    }

    /**
     * Get the full parent chain (oldest first, including self).
     */
    public function getThreadChain(): Collection
    {
        $chain = collect();
        $current = $this;

        while ($current) {
            $chain->prepend($current);
            $current = $current->parent;
        }

        return $chain;
    }
}
