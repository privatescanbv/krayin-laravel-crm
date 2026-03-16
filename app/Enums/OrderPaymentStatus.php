<?php

namespace App\Enums;

enum OrderPaymentStatus: string
{
    case NOT_APPLICABLE = 'niet_van_toepassing';
    case NOT_PAID = 'niet_betaald';
    case PARTIALLY_PAID = 'gedeeltelijk_betaald';
    case FULLY_PAID = 'volledig_betaald';

    public static function forOrder(float $total, float $paid): self
    {
        if ($total <= 0) {
            return self::NOT_APPLICABLE;
        }
        if ($paid <= 0) {
            return self::NOT_PAID;
        }
        if ($paid >= $total) {
            return self::FULLY_PAID;
        }

        return self::PARTIALLY_PAID;
    }

    public function label(): string
    {
        return match ($this) {
            self::NOT_APPLICABLE => 'Niet van toepassing',
            self::NOT_PAID       => 'Niet betaald',
            self::PARTIALLY_PAID => 'Gedeeltelijk betaald',
            self::FULLY_PAID     => 'Volledig betaald',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::FULLY_PAID     => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            self::PARTIALLY_PAID => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
            self::NOT_PAID       => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            self::NOT_APPLICABLE => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
        };
    }
}
