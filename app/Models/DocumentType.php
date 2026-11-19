<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Activity\Models\Activity;

/**
 * Document type for file activities, shared by the clinic and patient portal.
 *
 * `key` is only set for the system types recognized in code (see KEY_* constants below);
 * it is immutable and the admin screen never exposes it as an editable field.
 *
 * @mixin IdeHelperDocumentType
 */
class DocumentType extends Model
{
    use HasAuditTrail, HasFactory;

    public const KEY_TO_MIGRATE = 'to_migrate';

    public const KEY_PRESCRIPTION = 'prescription';

    public const KEY_FINANCIAL = 'financial';

    public const KEY_OWN_UPLOAD = 'own_upload';

    protected $fillable = [
        'key',
        'name',
        'description',
        'show_in_clinic_portal',
        'show_in_patient_portal',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'show_in_clinic_portal'  => 'boolean',
        'show_in_patient_portal' => 'boolean',
        'sort_order'             => 'integer',
        'created_by'             => 'integer',
        'updated_by'             => 'integer',
    ];

    public static function findByKey(string $key): ?self
    {
        return static::where('key', $key)->first();
    }

    /**
     * Fails hard when the system type is missing, so documents never end up typeless.
     */
    public static function idForKey(string $key): int
    {
        return static::where('key', $key)->firstOrFail()->id;
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'document_type_id');
    }

    /**
     * System types are recognized in code via `key` and cannot be deleted or renamed-by-key.
     */
    public function isSystemType(): bool
    {
        return $this->key !== null;
    }

    public function isInUse(): bool
    {
        return $this->activities()->exists();
    }

    /**
     * Types a user may pick for new files; "to migrate" is only for existing (migrated) data.
     */
    public function scopeAssignable(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('key')->orWhere('key', '!=', self::KEY_TO_MIGRATE));
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
