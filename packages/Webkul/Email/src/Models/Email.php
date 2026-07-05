<?php

namespace Webkul\Email\Models;

use App\Enums\EmailEntityLink;
use App\Helpers\ValueNormalizer;
use App\Models\Clinic;
use App\Models\Order;
use App\Models\SalesLead;
use App\Services\Mail\MailboxConfig;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;
use Webkul\Contact\Models\PersonProxy;
use Webkul\Email\Contracts\Email as EmailContract;
use Webkul\Email\Enums\EmailFolderEnum;
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
        'llm_metadata'  => 'array',
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
        'quote_split',
        'to_display',
    ];

    /**
     * Per-instance memoized result of {@see getQuoteSplitAttribute()}.
     *
     * @var array{main: string, quoted: string}|null
     */
    private ?array $quoteSplitCache = null;

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
        'mailbox_key',
        'from',
        'sender',
        'reply_to',
        'cc',
        'bcc',
        'unique_id',
        'message_id',
        'reference_ids',
        'llm_metadata',
        'reply',
        'person_id',
        'parent_id',
        'lead_id',
        'sales_lead_id',
        'order_id',
        'clinic_id',
        'activity_id',
        'created_at',
        'updated_at',
    ];

    /**
     * Foreign keys on `emails` that mean this message is linked to a CRM entity.
     *
     * @see EmailEntityLink
     *
     * @return list<string>
     */
    public static function entityLinkForeignKeys(): array
    {
        return EmailEntityLink::foreignKeys();
    }

    /**
     * SQL CASE expression for the primary linked entity label (datagrid `entity_type`).
     * Case order follows {@see EmailEntityLink} declaration order.
     */
    public static function entityTypeCaseSql(string $alias = 'emails'): string
    {
        $whens = [];
        foreach (EmailEntityLink::cases() as $link) {
            $column = $link->getForeignKey();
            $label = $link->value;
            $whens[] = "WHEN {$alias}.{$column} IS NOT NULL THEN '{$label}'";
        }

        return 'CASE '.implode(' ', $whens)." ELSE 'N/A' END";
    }

    /**
     * @param  Builder<Email>  $query
     */
    public function scopeWhereUnlinkedFromAllEntities(Builder $query): void
    {
        $table = $query->getModel()->getTable();
        foreach (self::entityLinkForeignKeys() as $column) {
            $query->whereNull("{$table}.{$column}");
        }
    }

    /**
     * @param  Builder<Email>  $query
     */
    public function scopeWhereLinkedToAnyEntity(Builder $query): void
    {
        $table = $query->getModel()->getTable();
        $query->where(function (Builder $q) use ($table) {
            foreach (self::entityLinkForeignKeys() as $column) {
                $q->orWhereNotNull("{$table}.{$column}");
            }
        });
    }

    /**
     * Apply the same "no entity link" semantics as {@see scopeWhereUnlinkedFromAllEntities} on a base query builder.
     */
    public static function applyUnlinkedFromAllEntitiesConstraints(QueryBuilder $query, string $alias = 'emails'): void
    {
        foreach (self::entityLinkForeignKeys() as $column) {
            $query->whereNull("{$alias}.{$column}");
        }
    }

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
    public function parent(): BelongsTo
    {
        return $this->belongsTo(EmailProxy::modelClass(), 'parent_id');
    }

    /**
     * Get the lead.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(LeadProxy::modelClass());
    }

    /**
     * Get the sales lead.
     */
    public function salesLead(): BelongsTo
    {
        return $this->belongsTo(SalesLead::class, 'sales_lead_id');
    }

    /**
     * Get the order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the clinic.
     */
    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    /**
     * Get the emails.
     */
    public function emails(): HasMany
    {
        return $this->hasMany(EmailProxy::modelClass(), 'parent_id')->orderBy('created_at', 'desc');
    }

    /**
     * Get the person that owns the thread.
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(PersonProxy::modelClass());
    }

    /**
     * The tags that belong to the lead.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(TagProxy::modelClass(), 'email_tags');
    }

    /**
     * Get the attachments.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(AttachmentProxy::modelClass(), 'email_id');
    }

    /**
     * Get the folder that contains this email.
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(FolderProxy::modelClass());
    }

    /**
     * Get the time ago.
     */
    public function getTimeAgoAttribute(): string
    {
        if (! $this->created_at || empty($this->created_at)) {
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
            if (! empty($keys) && str_contains((string) $keys[0], '@')) {
                return trim((string) $keys[0]);
            }
        }

        return '';
    }

    /**
     * Normalize 'from' to always be {"name": "...", "email": "..."} (for Vue component compatibility).
     *
     * Some records were written via a code path that skipped {@see normalizeFromField} and stored
     * 'from' as a bare/legacy value (e.g. a plain email string or a single-item array), which the
     * mail view template renders as an empty "Van:" line because it reads from.name / from.email
     * directly. This accessor normalizes any legacy shape on read so consumers can always rely on
     * the standard structure, mirroring {@see getReplyToAttribute}.
     */
    public function getFromAttribute($value)
    {
        // Get the raw value from attributes (before cast)
        $rawValue = $this->attributes['from'] ?? null;

        // If explicitly null (not set), return null (for test compatibility)
        if ($rawValue === null) {
            return null;
        }

        // Unwind JSON string encoding (handles legacy/double-encoded values).
        $decoded = $rawValue;
        for ($i = 0; $i < 3 && is_string($decoded); $i++) {
            $next = json_decode($decoded, true);

            if (json_last_error() !== JSON_ERROR_NONE || $next === $decoded) {
                break;
            }

            $decoded = $next;
        }

        // Plain (non-JSON) string - it's a bare email address.
        if (is_string($decoded)) {
            $decoded = trim($decoded);

            return $decoded === '' ? [] : ['name' => '', 'email' => $decoded];
        }

        if (! is_array($decoded) || empty($decoded)) {
            return [];
        }

        // Standard structure: {"name": "...", "email": "..."}
        if (isset($decoded['email']) && is_string($decoded['email'])) {
            return [
                'name'  => is_string($decoded['name'] ?? null) ? $decoded['name'] : '',
                'email' => trim($decoded['email']),
            ];
        }

        // Legacy: {"value": "..."}
        if (isset($decoded['value']) && is_string($decoded['value'])) {
            return [
                'name'  => is_string($decoded['name'] ?? null) ? $decoded['name'] : '',
                'email' => trim($decoded['value']),
            ];
        }

        // Legacy: list of entries, e.g. ["a@b.com"] or [{"email": "a@b.com"}]
        if (array_is_list($decoded)) {
            $first = $decoded[0] ?? null;

            if (is_string($first)) {
                return ['name' => '', 'email' => trim($first)];
            }

            if (is_array($first)) {
                return [
                    'name'  => is_string($first['name'] ?? null) ? $first['name'] : '',
                    'email' => trim((string) ($first['email'] ?? $first['value'] ?? '')),
                ];
            }

            return [];
        }

        // Legacy map-like format: {"email@example.com": "Name"}
        $keys = array_keys($decoded);
        if (is_string($keys[0]) && str_contains($keys[0], '@')) {
            return ['name' => (string) $decoded[$keys[0]], 'email' => trim($keys[0])];
        }

        return [];
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
        if (is_array($replyTo) && array_is_list($replyTo) && ! empty($replyTo) && is_string($replyTo[0] ?? null)) {
            return array_values(array_filter($replyTo));
        }

        // If it's an object with 'email' key (legacy format from old PatientMailService)
        if (is_array($replyTo) && ! array_is_list($replyTo) && isset($replyTo['email']) && is_string($replyTo['email'])) {
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
     * Preferred "To" recipients for display in the mail UI.
     *
     * Inbound Microsoft Graph messages sometimes have an empty reply_to because
     * the upstream payload used replyTo instead of toRecipients. Fall back to
     * the configured mailbox address so the UI never shows an impossible empty
     * "Aan" field on received mail.
     *
     * @return list<string>
     */
    public function getToDisplayAttribute(): array
    {
        $replyTo = $this->reply_to;

        if (is_array($replyTo) && $replyTo !== []) {
            return $replyTo;
        }

        $mailboxAddress = MailboxConfig::address($this->mailbox_key);

        if (is_string($mailboxAddress) && $mailboxAddress !== '') {
            return [$mailboxAddress];
        }

        return [];
    }

    /**
     * Whether this email is linked to any entity.
     */
    public function getHasRelationshipsAttribute(): bool
    {
        foreach (self::entityLinkForeignKeys() as $column) {
            if (! empty($this->{$column})) {
                return true;
            }
        }

        return false;
    }

    /**
     * Split `reply` into ['main' => ..., 'quoted' => ...] at the point quoted
     * history content starts, so the frontend can render the history
     * collapsed behind a toggle without doing DOM parsing client-side.
     *
     * @see \App\Services\Mail\EmailQuoteSplitter
     *
     * @return array{main: string, quoted: string}
     */
    public function getQuoteSplitAttribute(): array
    {
        if ($this->quoteSplitCache === null) {
            $this->quoteSplitCache = app(\App\Services\Mail\EmailQuoteSplitter::class)
                ->split((string) $this->reply);
        }

        return $this->quoteSplitCache;
    }

    /**
     * Normalize name attribute to always return a string.
     * Handles cases where name might be stored as an object or array.
     * This ensures Vue components receive a string value.
     *
     * @param  mixed  $value
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
     * @throws Exception
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
     * Scope: latest email per thread matching the given condition.
     *
     * @param  \Closure(Builder, string): void  $condition  Receives ($query, $tablePrefix) to add where clauses
     */
    public function scopeLatestPerThread(Builder $query, \Closure $condition): Builder
    {
        return $query->whereIn('id', function ($sub) use ($condition) {
            $sub->selectRaw('MAX(thread.id)')
                ->from('emails as thread')
                ->where(function ($w) use ($condition) {
                    $w->where(fn ($q) => $condition($q, 'thread'))
                        ->orWhereIn('thread.parent_id', function ($p) use ($condition) {
                            $p->select('id')->from('emails')->where(fn ($q) => $condition($q, 'emails'));
                        });
                })
                ->groupByRaw('COALESCE(thread.parent_id, thread.id)');
        });
    }

    /**
     * Restrict a query to the newest email per thread root within a folder (inbox collapse).
     *
     * @param  Builder|QueryBuilder  $query
     */
    public static function constrainToLatestPerThreadRootInFolder($query, int $folderId, string $idColumn = 'emails.id'): void
    {
        [$subquery, $bindings] = static::latestPerThreadRootInFolderSubquerySql($folderId);

        $query->whereRaw("{$idColumn} IN ({$subquery})", $bindings);
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    public static function latestPerThreadRootInFolderSubquerySql(int $folderId): array
    {
        $table = DB::getTablePrefix().(new static)->getTable();

        return [
            "SELECT MAX(e.id)
            FROM {$table} e
            INNER JOIN (
                WITH RECURSIVE thread_roots AS (
                    SELECT id, id AS root_id FROM {$table} WHERE parent_id IS NULL
                    UNION ALL
                    SELECT e2.id, tr.root_id
                    FROM {$table} e2
                    INNER JOIN thread_roots tr ON e2.parent_id = tr.id
                )
                SELECT id, root_id FROM thread_roots
            ) tr ON e.id = tr.id
            WHERE e.folder_id = ?
            GROUP BY tr.root_id",
            [$folderId],
        ];
    }

    /**
     * @return list<string>
     */
    public static function inboxFolderNames(): array
    {
        return [
            EmailFolderEnum::INBOX->getFolderName(),
            EmailFolderEnum::INBOX_HERNIAPOLI->getFolderName(),
        ];
    }

    /**
     * Scope: all emails in thread matching the given condition (parents + children).
     *
     * @param  \Closure(Builder, string): void  $condition
     */
    public function scopeAllInThread(Builder $query, \Closure $condition): Builder
    {
        return $query->where(function ($q) use ($condition) {
            $q->where(fn ($w) => $condition($w, 'emails'))
                ->orWhereIn('parent_id', function ($p) use ($condition) {
                    $p->select('id')->from('emails')->where(fn ($w) => $condition($w, 'emails'));
                });
        });
    }

    public function scopeForLeadThread(Builder $query, int $leadId): Builder
    {
        return $query->latestPerThread(fn ($q, $t) => $q->where("{$t}.lead_id", $leadId));
    }

    public function scopeForClinicThread(Builder $query, int $clinicId): Builder
    {
        return $query->latestPerThread(fn ($q, $t) => $q->where("{$t}.clinic_id", $clinicId));
    }

    public function scopeForSalesLeadThread(Builder $query, int $salesLeadId): Builder
    {
        return $query->latestPerThread(fn ($q, $t) => $q
            ->where("{$t}.sales_lead_id", $salesLeadId)
            ->orWhereIn("{$t}.order_id", function ($sub) use ($salesLeadId) {
                $sub->select('id')->from('orders')->where('sales_lead_id', $salesLeadId);
            })
        );
    }

    public function scopeForOrderThread(Builder $query, int $orderId): Builder
    {
        return $query->latestPerThread(fn ($q, $t) => $q->where("{$t}.order_id", $orderId));
    }

    public function scopeForPersonThread(Builder $query, int $personId, array $leadIds, array $salesLeadIds = [], array $orderIds = []): Builder
    {
        return $query->latestPerThread(function ($q, $t) use ($personId, $leadIds, $salesLeadIds, $orderIds) {
            $q->where("{$t}.person_id", $personId);
            if (! empty($leadIds)) {
                $q->orWhereIn("{$t}.lead_id", $leadIds);
            }
            if (! empty($salesLeadIds)) {
                $q->orWhereIn("{$t}.sales_lead_id", $salesLeadIds);
            }
            if (! empty($orderIds)) {
                $q->orWhereIn("{$t}.order_id", $orderIds);
            }
        });
    }

    public function scopeForLeadThreadAndUnread(Builder $query, int $leadId): Builder
    {
        return $query
            ->allInThread(fn ($q, $t) => $q->where("{$t}.lead_id", $leadId))
            ->where('is_read', 0);
    }

    public function scopeForSalesLeadThreadAndUnread(Builder $query, int $salesLeadId): Builder
    {
        return $query
            ->allInThread(fn ($q, $t) => $q
                ->where("{$t}.sales_lead_id", $salesLeadId)
                ->orWhereIn("{$t}.order_id", function ($sub) use ($salesLeadId) {
                    $sub->select('id')->from('orders')->where('sales_lead_id', $salesLeadId);
                })
            )
            ->where('is_read', 0);
    }

    /**
     * Entity links copied to replies/forwards: omit person/lead when sales is already linked.
     *
     * @return array<string, int>
     */
    public static function entityLinksToInheritFrom(self $email): array
    {
        $links = [];

        foreach (self::entityLinkForeignKeys() as $foreignKey) {
            if (! empty($email->{$foreignKey})) {
                $links[$foreignKey] = $email->{$foreignKey};
            }
        }

        if (! empty($links['sales_lead_id'])) {
            unset($links['person_id'], $links['lead_id']);
        }

        return $links;
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
     * All email IDs in this thread (root + descendants).
     *
     * @return list<int>
     */
    public function getThreadEmailIds(): array
    {
        $root = $this->getThreadRoot();
        $ids = [$root->id];
        $queue = [$root->id];

        while ($queue !== []) {
            $parentId = array_shift($queue);
            $childIds = self::query()
                ->where('parent_id', $parentId)
                ->pluck('id')
                ->all();

            foreach ($childIds as $childId) {
                if (! in_array($childId, $ids, true)) {
                    $ids[] = $childId;
                    $queue[] = $childId;
                }
            }
        }

        return $ids;
    }

    public function getThreadEmailsForDisplay(): EloquentCollection
    {
        $root = $this->getThreadRoot();
        $threadIds = array_values(array_filter(
            $root->getThreadEmailIds(),
            fn (int $emailId) => $emailId !== $root->id
        ));

        if ($threadIds === []) {
            return new EloquentCollection;
        }

        return self::query()
            ->whereIn('id', $threadIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
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
