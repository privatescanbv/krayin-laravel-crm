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
}
