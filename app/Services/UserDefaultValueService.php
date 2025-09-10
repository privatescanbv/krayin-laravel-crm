<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Webkul\User\Models\UserDefaultValue;

class UserDefaultValueService
{
    /**
     * Get default values for a user by key pattern
     */
    public function getDefaultsForUser(int $userId, string $keyPattern = 'lead.%'): Collection
    {
        return UserDefaultValue::where('user_id', $userId)
            ->where('key', 'like', $keyPattern)
            ->get()
            ->pluck('value', 'key');
    }

    /**
     * Get default values for lead fields
     */
    public function getLeadDefaults(int $userId): array
    {
        $defaults = $this->getDefaultsForUser($userId, 'lead.%');

        $leadDefaults = [];

        foreach ($defaults as $key => $value) {
            // Remove 'lead.' prefix to get the field name
            $fieldName = str_replace('lead.', '', $key);
            $leadDefaults[$fieldName] = $value;
        }

        return $leadDefaults;
    }

    /**
     * Set a default value for a user
     */
    public function setDefault(int $userId, string $key, string $value): UserDefaultValue
    {
        return UserDefaultValue::updateOrCreate(
            [
                'user_id' => $userId,
                'key'     => $key,
            ],
            [
                'value'      => $value,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );
    }

    /**
     * Get a specific default value for a user
     */
    public function getDefault(int $userId, string $key): ?string
    {
        $default = UserDefaultValue::where('user_id', $userId)
            ->where('key', $key)
            ->first();

        return $default ? $default->value : null;
    }
}
