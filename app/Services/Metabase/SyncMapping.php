<?php

namespace App\Services\Metabase;

use Illuminate\Support\Facades\Storage;

/**
 * Persists the source -> target id mapping for one (source, target) pair as a
 * JSON file under storage/app/metabase-sync/. This is what makes the sync
 * idempotent: a second run updates the mapped target objects instead of
 * creating duplicates.
 *
 * File shape (matches the story):
 *   {
 *     "dashboard:12": { "source": "dev", "target": "prod", "target_id": 8 },
 *     "card:34":      { "source": "dev", "target": "prod", "target_id": 21 }
 *   }
 */
class SyncMapping
{
    private const DISK = 'local';

    private const DIR = 'metabase-sync';

    /** @var array<string, array{source: string, target: string, target_id: int}> */
    private array $entries;

    private bool $dirty = false;

    public function __construct(
        private readonly string $source,
        private readonly string $target,
        private readonly bool $readOnly = false,
    ) {
        $this->entries = $this->load();
    }

    public function path(): string
    {
        return self::DIR.'/'.$this->source.'__'.$this->target.'.json';
    }

    public function get(string $type, int $sourceId): ?int
    {
        return $this->entries[$this->key($type, $sourceId)]['target_id'] ?? null;
    }

    public function put(string $type, int $sourceId, int $targetId): void
    {
        $this->entries[$this->key($type, $sourceId)] = [
            'source'    => $this->source,
            'target'    => $this->target,
            'target_id' => $targetId,
        ];

        $this->dirty = true;

        if (! $this->readOnly) {
            $this->save();
        }
    }

    /** @return array<string, array{source: string, target: string, target_id: int}> */
    public function all(): array
    {
        return $this->entries;
    }

    private function key(string $type, int $sourceId): string
    {
        return $type.':'.$sourceId;
    }

    /** @return array<string, array{source: string, target: string, target_id: int}> */
    private function load(): array
    {
        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($this->path())) {
            return [];
        }

        $decoded = json_decode((string) $disk->get($this->path()), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function save(): void
    {
        if (! $this->dirty) {
            return;
        }

        Storage::disk(self::DISK)->put(
            $this->path(),
            json_encode($this->entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

        $this->dirty = false;
    }
}
