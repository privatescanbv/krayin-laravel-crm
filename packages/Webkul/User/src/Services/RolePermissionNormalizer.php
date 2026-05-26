<?php

namespace Webkul\User\Services;

class RolePermissionNormalizer
{
    /**
     * Ensure parent ACL keys are included when a child permission is selected.
     *
     * @param  array<int, string>|null  $permissions
     * @return array<int, string>|null
     */
    public function normalize(?array $permissions): ?array
    {
        if ($permissions === null || $permissions === []) {
            return $permissions;
        }

        $knownKeys = array_flip(
            collect(config('acl', []))->pluck('key')->filter()->all()
        );

        $expanded = $permissions;

        foreach ($permissions as $permission) {
            if (! is_string($permission) || ! str_contains($permission, '.')) {
                continue;
            }

            $parts = explode('.', $permission);

            for ($i = 1; $i < count($parts); $i++) {
                $parent = implode('.', array_slice($parts, 0, $i));

                if (isset($knownKeys[$parent])) {
                    $expanded[] = $parent;
                }
            }
        }

        return array_values(array_unique($expanded));
    }
}
