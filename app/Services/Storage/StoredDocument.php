<?php

namespace App\Services\Storage;

final class StoredDocument
{
    public function __construct(
        public readonly string $path,
        public readonly string $disk,
        public readonly int $size,
        public readonly ?string $checksum = null,
    ) {}
}
