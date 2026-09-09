<?php

namespace App\Services\Metabase;

use RuntimeException;
use Throwable;

/**
 * Thrown when a Metabase HTTP call fails. The message carries enough context to
 * debug (environment label, method, path, status, a short body excerpt) but never
 * headers or the API key.
 */
class MetabaseApiException extends RuntimeException
{
    public static function fromResponse(
        string $environment,
        string $method,
        string $path,
        int $status,
        string $body,
        ?Throwable $previous = null,
    ): self {
        $excerpt = trim(mb_substr($body, 0, 500));

        return new self(
            sprintf(
                'Metabase [%s] %s %s failed with HTTP %d%s',
                $environment,
                strtoupper($method),
                $path,
                $status,
                $excerpt === '' ? '' : ': '.$excerpt,
            ),
            $status,
            $previous,
        );
    }

    public static function connection(string $environment, string $method, string $path, Throwable $previous): self
    {
        return new self(
            sprintf('Metabase [%s] %s %s could not be reached: %s', $environment, strtoupper($method), $path, $previous->getMessage()),
            0,
            $previous,
        );
    }
}
