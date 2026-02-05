<?php

namespace App\Enums;

enum OrderStatus: string
{
    case NEW = 'new';
    case PLANNED = 'planned';
    case SENT = 'sent';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public static function completedState(): OrderStatus
    {
        return self::APPROVED;
    }

    public static function getCloseStatuses(): array
    {
        return [
            self::APPROVED->value,
            self::REJECTED->value,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::NEW       => 'Nieuw',
            self::PLANNED   => 'Ingepland',
            self::SENT      => 'Verstuurd',
            self::APPROVED  => 'Akkoord',
            self::REJECTED  => 'Niet akkoord',
        };
    }

    public function getStatusClass(): string
    {
        return match ($this) {
            self::NEW       => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
            self::PLANNED   => 'bg-blue-100 text-activity-task-text dark:bg-blue-900 dark:text-blue-300',
            self::SENT      => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
            self::APPROVED  => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
            self::REJECTED  => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        };
    }
}
