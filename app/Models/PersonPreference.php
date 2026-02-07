<?php

namespace App\Models;

use App\Enums\PersonPreferenceKey;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Webkul\Contact\Models\PersonProxy;

/**
 * @mixin IdeHelperPersonPreference
 */
class PersonPreference extends Model
{
    use HasAuditTrail;

    protected $table = 'person_preferences';

    protected $fillable = [
        'person_id',
        'key',
        'value',
        'value_type',
        'is_system_managed',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'value'             => 'json',
        'is_system_managed' => 'boolean',
        'created_by'        => 'integer',
        'updated_by'        => 'integer',
    ];

    /**
     * Get a preference value for a person, returning default if not set.
     */
    public static function getValueForPerson(int $personId, PersonPreferenceKey $key): mixed
    {
        $preference = self::where('person_id', $personId)
            ->where('key', $key->value)
            ->first();

        if ($preference) {
            return $preference->typed_value;
        }

        return $key->defaultValue();
    }

    /**
     * Set a preference value for a person.
     */
    public static function setValueForPerson(int $personId, PersonPreferenceKey $key, mixed $value): self
    {
        return self::updateOrCreate(
            [
                'person_id' => $personId,
                'key'       => $key->value,
            ],
            [
                'value'             => $value,
                'value_type'        => $key->valueType(),
                'is_system_managed' => $key->isSystemManaged(),
            ]
        );
    }

    /**
     * Get all preferences for a person, including defaults for unset keys.
     *
     * @return array<string, mixed>
     */
    public static function getAllForPerson(int $personId): array
    {
        $stored = self::where('person_id', $personId)
            ->get()
            ->keyBy('key');

        $result = [];

        foreach (PersonPreferenceKey::cases() as $key) {
            if ($stored->has($key->value)) {
                $preference = $stored->get($key->value);
                $result[$key->value] = [
                    'value'             => $preference->typed_value,
                    'is_system_managed' => $preference->is_system_managed,
                ];
            } else {
                $result[$key->value] = [
                    'value'             => $key->defaultValue(),
                    'is_system_managed' => $key->isSystemManaged(),
                ];
            }
        }

        return $result;
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(PersonProxy::modelClass(), 'person_id');
    }

    /**
     * Get the typed value based on value_type.
     */
    public function getTypedValueAttribute(): mixed
    {
        return match ($this->value_type) {
            'bool'   => (bool) $this->value,
            'int'    => (int) $this->value,
            'string' => (string) $this->value,
            'array', 'object' => $this->value,
            default  => $this->value,
        };
    }
}
