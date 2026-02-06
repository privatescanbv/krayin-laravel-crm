<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Encrypts a string for storage and decrypts on read.
 *
 * Notes:
 * - If decryption fails (e.g. legacy plaintext), we return the raw value to avoid breaking reads.
 * - Use a one-time command/migration to re-encrypt legacy plaintext at rest.
 *
 * @implements CastsAttributes<?string, ?string>
 */
class EncryptedString implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            // Legacy plaintext or wrong key/corrupt payload.
            // Returning raw keeps the app functional; encrypt legacy rows via a one-time job.
            return is_string($value) ? $value : null;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [$key => null];
        }

        // If it already looks decryptable, keep as-is to avoid double-encryption.
        if (is_string($value)) {
            try {
                Crypt::decryptString($value);

                return [$key => $value];
            } catch (Throwable) {
                // Not decryptable → treat as plaintext, encrypt below.
            }
        }

        return [$key => Crypt::encryptString((string) $value)];
    }
}
