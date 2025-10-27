<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class Period
{
    public function __construct(
        public readonly ?CarbonImmutable $start,
        public readonly ?CarbonImmutable $end,
    ) {
        if ($this->start && $this->end && $this->end->lt($this->start)) {
            throw new InvalidArgumentException('End date must be after start date');
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

    /**
     * Check if a given date falls within this period.
     */
    public function contains(Carbon|\DateTime $date): bool
    {
        // Convert DateTime to Carbon for comparison
        $carbonDate = $date instanceof Carbon ? $date : Carbon::parse($date);

        // If period is infinite (no end date), check if date is on or after start date
        if ($this->isInfinite()) {
            return $this->start === null || $carbonDate->gte($this->start);
        }

        // For finite periods, check if date is within the range
        if ($this->start && $carbonDate->lt($this->start)) {
            return false;
        }

        if ($this->end && $carbonDate->gt($this->end)) {
            return false;
        }

        return true;
    }

    /**
     * Check if the period is active on a given date.
     */
    public function isActiveOn(Carbon $date): bool
    {
        return $this->contains($date);
    }
}
