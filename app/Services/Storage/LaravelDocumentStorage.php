<?php

namespace App\Services\Storage;

use Illuminate\Support\Facades\Storage;

class LaravelDocumentStorage implements DocumentStorage
{
    /**
     * Store readonly also in public, because we mount only public folder to the actual file system in docker compose.
     */
    public function __construct(
        private readonly string $disk = 'public'
    ) {}

    public function put(string $path, string $contents, array $meta = []): StoredDocument
    {
        Storage::disk($this->disk)->put($path, $contents);

        return new StoredDocument(
            path: $path,
            disk: $this->disk,
            size: strlen($contents)
        );
    }

    public function read(string $path): string
    {
        return Storage::disk($this->disk)->get($path);
    }

    public function delete(string $path): void
    {
        Storage::disk($this->disk)->delete($path);
    }

    public function exists(string $path): bool
    {
        return Storage::disk($this->disk)->exists($path);
    }
}
