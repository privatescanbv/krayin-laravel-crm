<?php

namespace App\Services\Storage;

interface DocumentStorage
{
    public function put(
        string $path,
        string $contents,
        array $meta = []
    ): StoredDocument;

    public function read(string $path): string;

    public function delete(string $path): void;

    public function exists(string $path): bool;
}
