<?php

namespace App\Enums;

use LogicException;

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

    /**
     * Centralized stage metadata.
     *
     * If you add a new enum case, add it here as well.
     *
     * @var array<string, array{
     *   id:int,
     *   pipeline:int,
     *   label:string,
     *   entity:'lead'|'sales'|'tech',
     *   status:'won'|'lost'|null
     * }>
     */
    private const META = [
        // Privatescan Lead
        self::NIEUWE_AANVRAAG_KWALIFICEREN->value => [
            'id'       => 1,
            'pipeline' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value,
            'label'    => 'Nieuwe aanvraag, kwalificeren',
            'entity'   => 'lead',
            'status'   => null,
        ],
        self::KLANT_ADVISEREN_START->value => [
            'id'       => 2,
            'pipeline' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value,
            'label'    => 'Klant adviseren',
            'entity'   => 'lead',
            'status'   => null,
        ],
        self::KLANT_ADVISEREN_OPVOLGEN->value => [
            'id'       => 3,
            'pipeline' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value,
            'label'    => 'Klant adviseren opvolgen',
            'entity'   => 'lead',
            'status'   => null,
        ],
        self::WON->value => [
            'id'       => 4,
            'pipeline' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value,
            'label'    => 'Gewonnen',
            'entity'   => 'lead',
            'status'   => 'won',
        ],
        self::LOST->value => [
            'id'       => 5,
            'pipeline' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value,
            'label'    => 'Verloren',
            'entity'   => 'lead',
            'status'   => 'lost',
        ],

        // Hernia Lead
        self::NIEUWE_AANVRAAG_KWALIFICEREN_HERNIA->value => [
            'id'       => 6,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_ID->value,
            'label'    => 'Nieuwe aanvraag, kwalificeren',
            'entity'   => 'lead',
            'status'   => null,
        ],
        self::KLANT_ADVISEREN_START_HERNIA->value => [
            'id'       => 7,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_ID->value,
            'label'    => 'Klant adviseren, geen MRI / Overige',
            'entity'   => 'lead',
            'status'   => null,
        ],
        self::KLANT_ADVISEREN_WILL_MRI_HERNIA->value => [
            'id'       => 8,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_ID->value,
            'label'    => 'Klant adviseren, wenst of heeft MRI',
            'entity'   => 'lead',
            'status'   => null,
        ],
        self::KLANT_ADVISEREN_WACHTEN_OP_MRI_HERNIA->value => [
            'id'       => 9,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_ID->value,
            'label'    => 'Wachten op klant, MRI wordt opgestuurd',
            'entity'   => 'lead',
            'status'   => null,
        ],
        self::KLANT_ADVISEREN_MRI_BINNEN_HERNIA->value => [
            'id'       => 10,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_ID->value,
            'label'    => 'Klant adviseren, MRI is binnen',
            'entity'   => 'lead',
            'status'   => null,
        ],
        self::WON_HERNIA->value => [
            'id'       => 11,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_ID->value,
            'label'    => 'Gewonnen',
            'entity'   => 'lead',
            'status'   => 'won',
        ],
        self::LOST_HERNIA->value => [
            'id'       => 12,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_ID->value,
            'label'    => 'Verloren',
            'entity'   => 'lead',
            'status'   => 'lost',
        ],

        // Privatescan Sales
        self::BESTELLING_VOORBEREIDEN->value => [
            'id'       => 13,
            'pipeline' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_SALES_ID->value,
            'label'    => 'Geadviseerd, order bevestigen',
            'entity'   => 'sales',
            'status'   => null,
        ],
        self::ORDER_VERZONDEN->value => [
            'id'       => 14,
            'pipeline' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_SALES_ID->value,
            'label'    => 'Order verzonden',
            'entity'   => 'sales',
            'status'   => null,
        ],
        self::ORDER_CONFIRMED->value => [
            'id'       => 15,
            'pipeline' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_SALES_ID->value,
            'label'    => 'Order bevestigd',
            'entity'   => 'sales',
            'status'   => null,
        ],
        self::WAITING_FOR_EXECUTION->value => [
            'id'       => 16,
            'pipeline' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_SALES_ID->value,
            'label'    => 'Wachten op uitvoering',
            'entity'   => 'sales',
            'status'   => null,
        ],
        self::WAITING_REPORTS->value => [
            'id'       => 17,
            'pipeline' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_SALES_ID->value,
            'label'    => 'Uitgevoerd, wachten op rapporten',
            'entity'   => 'sales',
            'status'   => null,
        ],
        self::REPORTS_RECEIVED->value => [
            'id'       => 18,
            'pipeline' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_SALES_ID->value,
            'label'    => 'Rapporten ontvangen',
            'entity'   => 'sales',
            'status'   => null,
        ],
        self::ORDER_WON->value => [
            'id'       => 19,
            'pipeline' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_SALES_ID->value,
            'label'    => 'Klantproces beëindigd',
            'entity'   => 'sales',
            'status'   => 'won',
        ],
        self::ORDER_LOST->value => [
            'id'       => 20,
            'pipeline' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_SALES_ID->value,
            'label'    => 'Afgevoerd',
            'entity'   => 'sales',
            'status'   => 'lost',
        ],

        // Hernia Sales
        self::BESTELLING_VOORBEREIDEN_HERNIA->value => [
            'id'       => 21,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value,
            'label'    => 'Bestelling voorbereiden',
            'entity'   => 'sales',
            'status'   => null,
        ],
        self::ORDER_VERZENDEN_HERNIA->value => [
            'id'       => 22,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value,
            'label'    => 'Order is verzonden',
            'entity'   => 'sales',
            'status'   => null,
        ],
        self::ORDER_LOST_HERNIA->value => [
            'id'       => 23,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value,
            'label'    => 'Verloren',
            'entity'   => 'sales',
            'status'   => 'lost',
        ],
        self::ORDER_WON_HERNIA->value => [
            'id'       => 24,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value,
            'label'    => 'Gewonnen',
            'entity'   => 'sales',
            'status'   => 'won',
        ],

        // Tech
        self::NO_PIPELINE->value => [
            'id'       => 25,
            'pipeline' => PipelineDefaultKeys::PIPELINE_TECHNICAL_ID->value,
            'label'    => 'No pipeline',
            'entity'   => 'tech',
            'status'   => null,
        ],
    ];

    public static function allStageAfterNewExcludingLost(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $stage) => ! in_array($stage, [
                self::NIEUWE_AANVRAAG_KWALIFICEREN,
                self::NIEUWE_AANVRAAG_KWALIFICEREN_HERNIA,
            ], true)
                && ! $stage->isLost()
        ));
    }

    public static function allStageWon(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $stage) => $stage->isWon()
        ));
    }

    public static function allStageExcludingWon(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $stage) => ! $stage->isWon()
        ));
    }

    // ============================================================
    // ATTRIBUTE STRUCTURE FOR EACH ENUM VALUE
    // ============================================================

    public function id(): int
    {
        return $this->meta()['id'];
    }

    /**
     * Human readable label.
     *
     * (Alias for `name()` to keep existing API working.)
     */
    public function label(): string
    {
        return $this->meta()['label'];
    }

    public function name(): string
    {
        return $this->label();
    }

    public function pipeline(): int
    {
        return $this->meta()['pipeline'];
    }

    public function isWon(): bool
    {
        return $this->meta()['status'] === 'won';
    }

    public function isLost(): bool
    {
        return $this->meta()['status'] === 'lost';
    }

    /**
     * @return bool true if lead, otherwise sales
     */
    public function isLead(): bool
    {
        return $this->meta()['entity'] === 'lead';
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

    /**
     * @return array{
     *   id:int,
     *   pipeline:int,
     *   label:string,
     *   entity:'lead'|'sales'|'tech',
     *   status:'won'|'lost'|null
     * }
     */
    private function meta(): array
    {
        return self::META[$this->value]
            ?? throw new LogicException("Missing PipelineStage metadata for: {$this->value}");
    }
}
