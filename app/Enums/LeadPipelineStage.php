<?php

namespace App\Enums;

enum LeadPipelineStage: string
{
    case NEW = 'nieuwe-aanvraag-kwalificeren';
    case MODIFY_CUSTOMER_DATA = 'klant-data-bijwerken';
    case ADVICE = 'klant-adviseren';
    case ORDER_SEND = 'wachten-op-orderbevestiging';
    case WON = 'gewonnen';
    case LOST = 'verloren';

    public function label(): string
    {
        return match($this) {
            self::NEW => 'Nieuwe aanvraag kwalificeren',
            self::MODIFY_CUSTOMER_DATA => 'Aanpassen klantgegevens',
            self::ADVICE => 'Klant adviseren',
            self::ORDER_SEND => 'wachten op orderbevestiging',
            self::WON => 'gewonnen',
            self::LOST => 'verloren',
        };
    }
}
