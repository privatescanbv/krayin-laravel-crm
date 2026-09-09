<?php

namespace App\Services\Metabase;

/**
 * Collects what the sync did (or, in dry-run, would do) so the command can print
 * a clear summary of created / updated / skipped items.
 */
class SyncReport
{
    /** @var list<array{action: string, type: string, label: string}> */
    private array $items = [];

    /** @var list<string> */
    private array $warnings = [];

    public function __construct(public readonly bool $dryRun) {}

    public function created(string $type, string $label): void
    {
        $this->items[] = ['action' => 'created', 'type' => $type, 'label' => $label];
    }

    public function updated(string $type, string $label): void
    {
        $this->items[] = ['action' => 'updated', 'type' => $type, 'label' => $label];
    }

    public function skipped(string $type, string $label): void
    {
        $this->items[] = ['action' => 'skipped', 'type' => $type, 'label' => $label];
    }

    public function warn(string $message): void
    {
        if (! in_array($message, $this->warnings, true)) {
            $this->warnings[] = $message;
        }
    }

    /** @return list<array{action: string, type: string, label: string}> */
    public function items(): array
    {
        return $this->items;
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /** @return array{created: int, updated: int, skipped: int} */
    public function totals(): array
    {
        return [
            'created' => count(array_filter($this->items, fn ($i) => $i['action'] === 'created')),
            'updated' => count(array_filter($this->items, fn ($i) => $i['action'] === 'updated')),
            'skipped' => count(array_filter($this->items, fn ($i) => $i['action'] === 'skipped')),
        ];
    }
}
