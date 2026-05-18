<?php

namespace App\Support;

/**
 * Normalizes filenames for Laravel/Flysystem storage paths.
 *
 * Flysystem rejects paths containing Unicode characters in general category "C"
 * (Other), including invisible format marks like U+00AD (soft hyphen) used in some
 * localized macOS screenshot names.
 */
final class SafeStorageFilename
{
    /** Safe maximum for a single path segment on common filesystems. */
    private const MAX_BASE_LENGTH = 200;

    public static function forPathSegment(string $filename): string
    {
        $basename = basename(str_replace('\\', '/', $filename));

        $cleaned = preg_replace('/\p{C}+/u', '', $basename);
        $cleaned = str_replace(["\0"], '', $cleaned ?? '');
        $cleaned = trim($cleaned);

        if ($cleaned === '' || $cleaned === '.' || $cleaned === '..') {
            return 'attachment';
        }

        if (mb_strlen($cleaned) > self::MAX_BASE_LENGTH) {
            return self::truncatePreservingExtension($cleaned);
        }

        return $cleaned;
    }

    private static function truncatePreservingExtension(string $filename): string
    {
        $ext = '';
        $base = $filename;

        if (preg_match('/^(.+)\.([^.]{1,20})$/u', $filename, $matches)) {
            $base = $matches[1];
            $ext = '.'.$matches[2];
        }

        $budget = max(1, self::MAX_BASE_LENGTH - mb_strlen($ext));
        $base = mb_substr($base, 0, $budget);

        $merged = trim($base).$ext;

        return $merged !== '' ? $merged : 'attachment';
    }
}
