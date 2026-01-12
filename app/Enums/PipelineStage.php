<?php

namespace App\Enums;

enum PipelineStage: string
{
    // ============================================================
    // PRIVATESCAN LEAD PIPELINE
    // ============================================================

    case NIEUWE_AANVRAAG_KWALIFICEREN = 'nieuwe-aanvraag-kwalificeren';
    case KLANT_ADVISEREN_START = 'klant-adviseren-start';
    case KLANT_ADVISEREN_OPVOLGEN = 'klant-adviseren-opvolgen';
    case WON = 'won';
    case LOST = 'lost';

    // ============================================================
    // HERNIA LEAD PIPELINE
    // ============================================================

    case NIEUWE_AANVRAAG_KWALIFICEREN_HERNIA = 'nieuwe-aanvraag-kwalificeren-hernia';
    case KLANT_ADVISEREN_START_HERNIA = 'klant-adviseren-start-hernia';
    case KLANT_ADVISEREN_WILL_MRI_HERNIA = 'klant-adviseren-will-mri-hernia';
    case KLANT_ADVISEREN_WACHTEN_OP_MRI_HERNIA = 'klant-adviseren-wachten-op-mri-hernia';
    case KLANT_ADVISEREN_MRI_BINNEN_HERNIA = 'klant-adviseren-mri-binnen-hernia';
    case WON_HERNIA = 'won-hernia';
    case LOST_HERNIA = 'lost-hernia';

    // ============================================================
    // PRIVATESCAN SALES PIPELINE
    // ============================================================

    case BESTELLING_VOORBEREIDEN = 'bestelling-voorbereiden';
    case ORDER_VERZONDEN = 'order-verzonden';
    case ORDER_CONFIRMED = 'order-confirmed';
    case WAITING_FOR_EXECUTION = 'waiting-for-execution';
    case WAITING_REPORTS = 'waiting-reports';
    case REPORTS_RECEIVED = 'reports-received';
    case ORDER_WON = 'order-won';
    case ORDER_LOST = 'order-lost';

    // ============================================================
    // HERNIA SALES PIPELINE
    // ============================================================

    case BESTELLING_VOORBEREIDEN_HERNIA = 'bestelling-voorbereiden-hernia';
    case ORDER_VERZENDEN_HERNIA = 'order-verzenden-hernia';
    case ORDER_LOST_HERNIA = 'order-lost-hernia';
    case ORDER_WON_HERNIA = 'order-won-hernia';

    // ============================================================
    // TECH PIPELINE
    // ============================================================

    case NO_PIPELINE = 'no-pipeline';

    // ============================================================
    // ATTRIBUTE STRUCTURE FOR EACH ENUM VALUE
    // ============================================================

    public function id(): int
    {
        return match ($this) {
            self::NIEUWE_AANVRAAG_KWALIFICEREN => 1,
            self::KLANT_ADVISEREN_START        => 2,
            self::KLANT_ADVISEREN_OPVOLGEN     => 3,
            self::WON                          => 4,
            self::LOST                         => 5,

            self::NIEUWE_AANVRAAG_KWALIFICEREN_HERNIA   => 6,
            self::KLANT_ADVISEREN_START_HERNIA          => 7,
            self::KLANT_ADVISEREN_WILL_MRI_HERNIA       => 8,
            self::KLANT_ADVISEREN_WACHTEN_OP_MRI_HERNIA => 9,
            self::KLANT_ADVISEREN_MRI_BINNEN_HERNIA     => 10,
            self::WON_HERNIA                            => 11,
            self::LOST_HERNIA                           => 12,

            self::BESTELLING_VOORBEREIDEN => 13,
            self::ORDER_VERZONDEN         => 14,
            self::ORDER_CONFIRMED         => 15,
            self::WAITING_FOR_EXECUTION   => 16,
            self::WAITING_REPORTS         => 17,
            self::REPORTS_RECEIVED        => 18,
            self::ORDER_WON               => 19,
            self::ORDER_LOST              => 20,

            self::BESTELLING_VOORBEREIDEN_HERNIA => 21,
            self::ORDER_VERZENDEN_HERNIA         => 22,
            self::ORDER_LOST_HERNIA              => 23,
            self::ORDER_WON_HERNIA               => 24,

            self::NO_PIPELINE => 25,
        };
    }

    public function name(): string
    {
        return match ($this) {
            // Privatescan Lead
            self::NIEUWE_AANVRAAG_KWALIFICEREN => 'Nieuwe aanvraag, kwalificeren',
            self::KLANT_ADVISEREN_START        => 'Klant adviseren',
            self::KLANT_ADVISEREN_OPVOLGEN     => 'Klant adviseren opvolgen',
            self::WON                          => 'Gewonnen',
            self::LOST                         => 'Verloren',

            // Hernia Lead
            self::NIEUWE_AANVRAAG_KWALIFICEREN_HERNIA   => 'Nieuwe aanvraag, kwalificeren',
            self::KLANT_ADVISEREN_START_HERNIA          => 'Klant adviseren, geen MRI / Overige',
            self::KLANT_ADVISEREN_WILL_MRI_HERNIA       => 'Klant adviseren, wenst of heeft MRI',
            self::KLANT_ADVISEREN_WACHTEN_OP_MRI_HERNIA => 'Wachten op klant, MRI wordt opgestuurd',
            self::KLANT_ADVISEREN_MRI_BINNEN_HERNIA     => 'Klant adviseren, MRI is binnen',
            self::WON_HERNIA                            => 'Gewonnen',
            self::LOST_HERNIA                           => 'Verloren',

            // Privatescan Sales
            self::BESTELLING_VOORBEREIDEN => 'Geadviseerd, order bevestigen',
            self::ORDER_VERZONDEN         => 'Order verzonden',
            self::ORDER_CONFIRMED         => 'Order bevestigd',
            self::WAITING_FOR_EXECUTION   => 'Wachten op uitvoering',
            self::WAITING_REPORTS         => 'Uitgevoerd, wachten op rapporten',
            self::REPORTS_RECEIVED        => 'Rapporten ontvangen',
            self::ORDER_WON               => 'Klantproces beëindigd',
            self::ORDER_LOST              => 'Afgevoerd',

            // Hernia Sales
            self::BESTELLING_VOORBEREIDEN_HERNIA => 'Bestelling voorbereiden',
            self::ORDER_VERZENDEN_HERNIA         => 'Order is verzonden',
            self::ORDER_LOST_HERNIA              => 'Verloren',
            self::ORDER_WON_HERNIA               => 'Gewonnen',

            self::NO_PIPELINE => 'No pipeline',
        };
    }

    public function pipeline(): int
    {
        return match ($this) {
            // Privatescan lead
            self::NIEUWE_AANVRAAG_KWALIFICEREN,
            self::KLANT_ADVISEREN_START,
            self::KLANT_ADVISEREN_OPVOLGEN,
            self::WON,
            self::LOST => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value,

            // Hernia lead
            self::NIEUWE_AANVRAAG_KWALIFICEREN_HERNIA,
            self::KLANT_ADVISEREN_START_HERNIA,
            self::KLANT_ADVISEREN_WILL_MRI_HERNIA,
            self::KLANT_ADVISEREN_WACHTEN_OP_MRI_HERNIA,
            self::KLANT_ADVISEREN_MRI_BINNEN_HERNIA,
            self::WON_HERNIA,
            self::LOST_HERNIA => PipelineDefaultKeys::PIPELINE_HERNIA_ID->value,

            // Privatescan sales
            self::BESTELLING_VOORBEREIDEN,
            self::ORDER_VERZONDEN,
            self::ORDER_CONFIRMED,
            self::WAITING_FOR_EXECUTION,
            self::WAITING_REPORTS,
            self::REPORTS_RECEIVED,
            self::ORDER_WON,
            self::ORDER_LOST => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_SALES_ID->value,

            // Hernia sales
            self::BESTELLING_VOORBEREIDEN_HERNIA,
            self::ORDER_VERZENDEN_HERNIA,
            self::ORDER_LOST_HERNIA,
            self::ORDER_WON_HERNIA => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value,

            // Tech
            self::NO_PIPELINE => PipelineDefaultKeys::PIPELINE_TECHNICAL_ID->value,
        };
    }

    public function isWon(): bool
    {
        return match ($this) {
            self::WON,
            self::WON_HERNIA,
            self::ORDER_WON,
            self::ORDER_WON_HERNIA => true,
            default                => false,
        };
    }

    public function isLost(): bool
    {
        return match ($this) {
            self::LOST,
            self::LOST_HERNIA,
            self::ORDER_LOST,
            self::ORDER_LOST_HERNIA => true,
            default                 => false,
        };
    }

    /**
     * @return bool true if lead, otherwise sales
     */
    public function isLead(): bool
    {
        $pipeline = $this->pipeline();

        return $pipeline == PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value || $pipeline == PipelineDefaultKeys::PIPELINE_HERNIA_ID->value;
    }

    public function toArray(int $sortOrder): array
    {
        return [
            'id'               => $this->id(),
            'code'             => $this->value,
            'name'             => $this->name(),
            'probability'      => $this->isWon() ? 100 : ($this->isLost() ? 0 : 100),
            'sort_order'       => $sortOrder,
            'lead_pipeline_id' => $this->pipeline(),
            'is_won'           => $this->isWon(),
            'is_lost'          => $this->isLost(),
            'description'      => null,
        ];
    }
}
