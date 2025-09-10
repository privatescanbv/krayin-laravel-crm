<?php

namespace App\Services;

use Webkul\User\Models\UserDefaultValue;
use Illuminate\Support\Collection;

class UserDefaultValueService
{
    /**
     * Get default values for a user by key pattern
     *
     * @param int $userId
     * @param string $keyPattern
     * @return Collection
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
     *
     * @param int $userId
     * @return array
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
     *
     * @param int $userId
     * @param string $key
     * @param string $value
     * @return UserDefaultValue
     */
    public function setDefault(int $userId, string $key, string $value): UserDefaultValue
    {
        return UserDefaultValue::updateOrCreate(
            [
                'user_id' => $userId,
                'key' => $key,
            ],
            [
                'value' => $value,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );
    }

    /**
     * Get a specific default value for a user
     *
     * @param int $userId
     * @param string $key
     * @return string|null
     */
    public function getDefault(int $userId, string $key): ?string
    {
        $default = UserDefaultValue::where('user_id', $userId)
            ->where('key', $key)
            ->first();
            
        return $default ? $default->value : null;
    }
}