<?php

namespace App\Enums;

/**
 *  Also define group in user and for filtering in activities
 */
enum Departments: string
{
    case HERNIA = 'Herniapoli';
    case PRIVATESCAN = 'Privatescan';

    public static function fromKey(string $key): self
    {
        return match ($key) {
            'hernia'      => self::HERNIA,
            'privatescan' => self::PRIVATESCAN,
            default       => throw new \ValueError("Unknown department key: $key"),
        };
    }

    public static function allValues(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    /** Lowercase workflow/view key ('hernia' | 'privatescan') */
    public function key(): string
    {
        return strtolower($this->name); // HERNIA→hernia, PRIVATESCAN→privatescan
    }
}
