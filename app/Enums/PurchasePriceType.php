<?php

namespace App\Enums;

/**
 * Discriminator voor rijen in `purchase_prices`: welke prijs-set het betreft op hetzelfde polymorfe `priceable`.
 *
 * Waarden komen één-op-één overeen met de databasekolom `type`. Het model {@see \App\Models\PurchasePrice}
 * cast dit attribuut naar deze enum.
 */
enum PurchasePriceType: string
{
    /** Standaard inkoopprijs op een orderregel of partnerproduct (o.a. cascade van product → partner). */
    case MAIN = 'main';

    /** Factuur-inkoop per orderregel; gebruikt naast de gewone inkoop (bijv. afletteren). */
    case INVOICE = 'invoice';

    /** Inkoopprijs voor het gerelateerde product op een partnerproduct, naast de hoofd-`MAIN`-rij. */
    case RELATED = 'related';
}
