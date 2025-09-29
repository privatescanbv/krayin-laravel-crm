<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class Period
{
    public function __construct(
        public readonly ?CarbonImmutable $start,
        public readonly ?CarbonImmutable $end,
    ) {
        if ($this->start && $this->end && $this->end->lt($this->start)) {
            throw new \InvalidArgumentException('End date must be after start date');
        }
    }

    public static function fromArray(array $data): self
    {
        $start = isset($data['period_start']) && $data['period_start'] !== ''
            ? CarbonImmutable::parse($data['period_start'])->startOfDay()
            : null;

        $end = isset($data['period_end']) && $data['period_end'] !== ''
            ? CarbonImmutable::parse($data['period_end'])->endOfDay()
            : null;

        return new self($start, $end);
    }

    public function isInfinite(): bool
    {
        return $this->end === null;
    }
}
