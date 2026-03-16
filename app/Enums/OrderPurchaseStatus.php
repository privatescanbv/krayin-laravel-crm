<?php

namespace App\Enums;

enum OrderPurchaseStatus: string
{
    /** Beide bedragen zijn 0 – item wordt niet getoond in afletteren-tab. */
    case HIDDEN = 'verberg';
    case FULLY_RECEIVED = 'geheel_ontvangen';
    case PARTIALLY_RECEIVED = 'gedeeltelijk_ontvangen';
    case NOT_RECEIVED = 'niet_ontvangen';
    case INVOICE_WITHOUT_PURCHASE = 'invoice_zonder_inkoopprijs';
    case NO_PURCHASE_PRICE = 'geen_inkoopprijs';
    case UNKNOWN = 'onbekend';

    /**
     * Status per order-item (beide 0 → HIDDEN zodat het item overgeslagen wordt).
     */
    public static function forItem(float $purchaseTotal, float $invoiceTotal): self
    {
        $p = round($purchaseTotal, 2);
        $i = round($invoiceTotal, 2);

        if ($p <= 0 && $i <= 0) {
            return self::HIDDEN;
        }

        return self::resolveNonEmpty($p, $i);
    }

    /**
     * Geaggregeerde status voor een hele order (beide 0 → NO_PURCHASE_PRICE).
     */
    public static function forOrder(float $purchaseTotal, float $invoiceTotal): self
    {
        $p = round($purchaseTotal, 2);
        $i = round($invoiceTotal, 2);

        if ($p <= 0 && $i <= 0) {
            return self::NO_PURCHASE_PRICE;
        }

        return self::resolveNonEmpty($p, $i);
    }

    private static function resolveNonEmpty(float $p, float $i): self
    {
        if ($i > 0 && $p > 0) {
            return $i === $p ? self::FULLY_RECEIVED : self::PARTIALLY_RECEIVED;
        }
        if ($i <= 0 && $p > 0) {
            return self::NOT_RECEIVED;
        }
        if ($i > 0 && $p <= 0) {
            return self::INVOICE_WITHOUT_PURCHASE;
        }

        return self::UNKNOWN;
    }

    public function label(): string
    {
        return match ($this) {
            self::HIDDEN                   => '',
            self::FULLY_RECEIVED           => 'Geheel ontvangen',
            self::PARTIALLY_RECEIVED       => 'Gedeeltelijk ontvangen',
            self::NOT_RECEIVED             => 'Niet ontvangen',
            self::INVOICE_WITHOUT_PURCHASE => 'Invoice zonder inkoopprijs',
            self::NO_PURCHASE_PRICE        => 'Geen inkoopprijs',
            self::UNKNOWN                  => 'Onbekend',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::FULLY_RECEIVED           => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200',
            self::PARTIALLY_RECEIVED       => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200',
            self::NOT_RECEIVED             => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-200',
            self::INVOICE_WITHOUT_PURCHASE => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-200',
            default                        => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
        };
    }
}
