<?php

namespace App\Enums;

use InvalidArgumentException;

enum FormStatus: string
{
    case New = 'new';
    case Step1_completed = 'step1';
    case Step2_completed = 'step2';
    case Step3_completed = 'step3';
    case Completed = 'completed';

    public static function mapFrom(string $status)
    {
        foreach (self::cases() as $case) {
            if ($case->value === $status) {
                return $case;
            }
        }

        throw new InvalidArgumentException("Unknown form status: {$status}");
    }

    public function isCompleted(): string
    {
        return $this == FormStatus::Completed;
    }
}
