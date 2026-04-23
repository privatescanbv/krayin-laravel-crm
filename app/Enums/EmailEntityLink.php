<?php

namespace App\Enums;

/**
 * Columns on `emails` that represent a CRM entity link, and their datagrid labels.
 * Case order is display priority for SQL CASE WHEN (first match wins).
 */
enum EmailEntityLink: string
{
    case PERSON = 'person';
    case LEAD = 'lead';
    case ORDER = 'order';
    case SALES = 'sales';
    case CLINIC = 'clinic';
    case ACTIVITY = 'activity';

    public function getForeignKey(): string
    {
        return match ($this) {
            self::PERSON   => 'person_id',
            self::LEAD     => 'lead_id',
            self::ORDER    => 'order_id',
            self::SALES    => 'sales_lead_id',
            self::CLINIC   => 'clinic_id',
            self::ACTIVITY => 'activity_id',
        };
    }

    /**
     * @return list<string>
     */
    public static function foreignKeys(): array
    {
        return array_map(fn (self $link) => $link->getForeignKey(), self::cases());
    }
}
