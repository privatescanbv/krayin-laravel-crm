<?php

use Symfony\Component\Finder\Finder;

// A permission key that is not in acl.php can't be ticked in the role editor, and saving a role
// drops it again. So only 'all' roles ever pass the check.

test('every admin menu item checks a permission that exists in the acl', function () {
    $aclKeys = collect(config('acl'))->pluck('key');

    $missing = collect(config('menu.admin'))
        ->map(fn ($item) => $item['acl'] ?? $item['key'])
        ->reject(fn ($key) => $aclKeys->contains($key))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

test('every hasPermission literal in the code exists in the acl', function () {
    $aclKeys = collect(config('acl'))->pluck('key');

    $files = Finder::create()->files()->name('*.php')
        ->in([app_path(), base_path('packages/Webkul'), resource_path('views')])
        ->exclude(['node_modules', 'tests']);

    $missing = [];

    foreach ($files as $file) {
        preg_match_all("/hasPermission\(\s*['\"]([^'\"]+)['\"]/", $file->getContents(), $matches);

        foreach ($matches[1] as $key) {
            if (! $aclKeys->contains($key)) {
                $missing[] = "{$key} ({$file->getRelativePathname()})";
            }
        }
    }

    expect($missing)->toBe([]);
});
