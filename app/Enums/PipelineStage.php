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

    case SALES_IN_BEHANDELING = 'sales-in-behandeling';
    case SALES_MET_SUCCES_AFGEROND = 'sales-met-succes-afgerond';
    case SALES_NIET_SUCCESVOL_AFGEROND = 'sales-niet-succesvol-afgerond';

    // ============================================================
    // HERNIA SALES PIPELINE
    // ============================================================

    case SALES_DOCTOR_ASSESSMENT_HERNIA = 'sales-intake-hernia';
    case SALES_ASSESSMENT_DONE_HERNIA = 'sales-mri-beoordeling-hernia';
    case SALES_PATIENT_REFLECTION_TIME_HERNIA = 'sales-casus-bij-arts-hernia';
    case SALES_PLANNED_FOR_ADDITIONAL_RESEARCH_HERNIA = 'sales-beoordeling-gereed-hernia';
    case SALES_CONFIRM_TREATMENT_HERNIA = 'sales-wachten-op-planning-hernia';
    case SALES_DOC_COMPLETE_HERNIA = 'sales-ingepland-hernia';
    case SALES_TREATMENT_PLANNED_HERNIA = 'sales-pre-operatief-hernia';
    case SALES_AFTER_TREATMENT_HERNIA = 'sales-opname-hernia';
    case SALES_AFTERCARE1_HERNIA = 'sales-operatie-hernia';
    case SALES_AFTERCARE2_HERNIA = 'sales-herstel-hernia';
    case SALES_PHYSICAL_CONSULTATION_HERNIA = 'sales-nacontrole-hernia';
    case SALES_COMPLETE_HERNIA = 'sales-met-succes-afgerond-hernia';
    case SALES_COMPLETE_NOT_SUCCESSFULLY_HERNIA = 'sales-niet-succesvol-afgerond-hernia';

    // ============================================================
    // PRIVATESCAN ORDER PIPELINE
    // ============================================================

    case ORDER_CONFIRM = 'order-bevestigen';
    case ORDER_BEVESTIGD = 'order-bevestigd';
    case ORDER_INGEPLAND = 'order-ingepland';
    case ORDER_WACHTEN_UITVOERING = 'order-wachten-uitvoering';
    case ORDER_UITGEVOERD = 'order-uitgevoerd';
    case ORDER_RAPPORTEN_ONTVANGEN = 'order-rapporten-ontvangen';
    case ORDER_GEWONNEN = 'order-gewonnen';
    case ORDER_VERLOREN = 'order-verloren';

    // ============================================================
    // HERNIA ORDER PIPELINE
    // ============================================================

    case ORDER_VOORBEREIDEN_HERNIA = 'order-voorbereiden-hernia';
    case ORDER_BEVESTIGD_HERNIA = 'order-bevestigd-hernia';
    case ORDER_INGEPLAND_HERNIA = 'order-ingepland-hernia';
    case ORDER_WACHTEN_UITVOERING_HERNIA = 'order-wachten-uitvoering-hernia';
    case ORDER_UITGEVOERD_HERNIA = 'order-uitgevoerd-hernia';
    case ORDER_RAPPORTEN_ONTVANGEN_HERNIA = 'order-rapporten-ontvangen-hernia';
    case ORDER_GEWONNEN_HERNIA = 'order-gewonnen-hernia';
    case ORDER_VERLOREN_HERNIA = 'order-verloren-hernia';

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
     *   entity:'lead'|'sales'|'order'|'tech',
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
        self::SALES_IN_BEHANDELING->value => [
            'id'       => 13,
            'pipeline' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_SALES_ID->value,
            'label'    => 'In behandeling',
            'entity'   => 'sales',
            'status'   => null,
        ],
        self::SALES_MET_SUCCES_AFGEROND->value => [
            'id'       => 14,
            'pipeline' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_SALES_ID->value,
            'label'    => 'Met succes afgerond',
            'entity'   => 'sales',
            'status'   => 'won',
        ],
        self::SALES_NIET_SUCCESVOL_AFGEROND->value => [
            'id'       => 15,
            'pipeline' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_SALES_ID->value,
            'label'    => 'Niet succesvol afgerond',
            'entity'   => 'sales',
            'status'   => 'lost',
        ],

        // Hernia Sales
        self::SALES_DOCTOR_ASSESSMENT_HERNIA->value => [
            'id'       => 16,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value,
            'label'    => 'Casus bij arts ter beoordeling',
            'entity'   => 'sales',
            'status'   => null,
        ],
        self::SALES_ASSESSMENT_DONE_HERNIA->value => [
            'id'       => 17,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value,
            'label'    => 'Beoordeling gereed',
            'entity'   => 'sales',
            'status'   => null,
        ],
        self::SALES_PATIENT_REFLECTION_TIME_HERNIA->value => [
            'id'       => 18,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value,
            'label'    => 'Patient bedenktijd',
            'entity'   => 'sales',
            'status'   => null,
        ],
        self::SALES_PLANNED_FOR_ADDITIONAL_RESEARCH_HERNIA->value => [
            'id'       => 19,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value,
            'label'    => 'Gepland voor aanvullend onderzoek',
            'entity'   => 'sales',
            'status'   => null,
        ],
        self::SALES_CONFIRM_TREATMENT_HERNIA->value => [
            'id'       => 20,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value,
            'label'    => 'Behandeling bevestigen',
            'entity'   => 'sales',
            'status'   => null,
        ],
        self::SALES_DOC_COMPLETE_HERNIA->value => [
            'id'       => 21,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value,
            'label'    => 'Documentatie volledig / ontvangen',
            'entity'   => 'sales',
            'status'   => null,
        ],
        self::SALES_TREATMENT_PLANNED_HERNIA->value => [
            'id'       => 22,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value,
            'label'    => 'Behandeling gepland',
            'entity'   => 'sales',
            'status'   => null,
        ],
        self::SALES_AFTER_TREATMENT_HERNIA->value => [
            'id'       => 23,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value,
            'label'    => 'Na-behandeling',
            'entity'   => 'sales',
            'status'   => null,
        ],
        self::SALES_AFTERCARE1_HERNIA->value => [
            'id'       => 24,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value,
            'label'    => 'Nazorg 1',
            'entity'   => 'sales',
            'status'   => null,
        ],
        self::SALES_AFTERCARE2_HERNIA->value => [
            'id'       => 25,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value,
            'label'    => 'Nazorg 2',
            'entity'   => 'sales',
            'status'   => null,
        ],
        self::SALES_PHYSICAL_CONSULTATION_HERNIA->value => [
            'id'       => 26,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value,
            'label'    => 'Fysiek consult',
            'entity'   => 'sales',
            'status'   => null,
        ],
        self::SALES_COMPLETE_HERNIA->value => [
            'id'       => 27,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value,
            'label'    => 'Met succes afgerond',
            'entity'   => 'sales',
            'status'   => 'won',
        ],
        self::SALES_COMPLETE_NOT_SUCCESSFULLY_HERNIA->value => [
            'id'       => 28,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value,
            'label'    => 'Niet succesvol afgerond',
            'entity'   => 'sales',
            'status'   => 'lost',
        ],

        // Privatescan Orders
        self::ORDER_CONFIRM->value => [
            'id'       => 30,
            'pipeline' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ORDERS_ID->value,
            'label'    => 'Order bevestigen',
            'entity'   => 'order',
            'status'   => null,
        ],
        self::ORDER_INGEPLAND->value => [
            'id'       => 31,
            'pipeline' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ORDERS_ID->value,
            'label'    => 'Ingepland',
            'entity'   => 'order',
            'status'   => null,
        ],
        // Wachten op order akkoord
        self::ORDER_BEVESTIGD->value => [
            'id'       => 33,
            'pipeline' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ORDERS_ID->value,
            'label'    => 'Order akkoord?',
            'entity'   => 'order',
            'status'   => null,
        ],
        self::ORDER_WACHTEN_UITVOERING->value => [
            'id'       => 34,
            'pipeline' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ORDERS_ID->value,
            'label'    => 'Wachten op uitvoering',
            'entity'   => 'order',
            'status'   => null,
        ],
        self::ORDER_UITGEVOERD->value => [
            'id'       => 35,
            'pipeline' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ORDERS_ID->value,
            'label'    => 'Uitgevoerd, wachten op rapporten',
            'entity'   => 'order',
            'status'   => null,
        ],
        self::ORDER_RAPPORTEN_ONTVANGEN->value => [
            'id'       => 36,
            'pipeline' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ORDERS_ID->value,
            'label'    => 'Rapporten vertalen',
            'entity'   => 'order',
            'status'   => null,
        ],
        self::ORDER_GEWONNEN->value => [
            'id'       => 37,
            'pipeline' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ORDERS_ID->value,
            'label'    => 'Gewonnen',
            'entity'   => 'order',
            'status'   => 'won',
        ],
        self::ORDER_VERLOREN->value => [
            'id'       => 38,
            'pipeline' => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ORDERS_ID->value,
            'label'    => 'Verloren',
            'entity'   => 'order',
            'status'   => 'lost',
        ],

        // Hernia Orders
        self::ORDER_VOORBEREIDEN_HERNIA->value => [
            'id'       => 39,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_ORDERS_ID->value,
            'label'    => 'Order voorbereiden',
            'entity'   => 'order',
            'status'   => null,
        ],
        self::ORDER_INGEPLAND_HERNIA->value => [
            'id'       => 40,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_ORDERS_ID->value,
            'label'    => 'Ingepland',
            'entity'   => 'order',
            'status'   => null,
        ],
        self::ORDER_BEVESTIGD_HERNIA->value => [
            'id'       => 42,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_ORDERS_ID->value,
            'label'    => 'Order bevestigd',
            'entity'   => 'order',
            'status'   => null,
        ],
        self::ORDER_WACHTEN_UITVOERING_HERNIA->value => [
            'id'       => 43,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_ORDERS_ID->value,
            'label'    => 'Wachten op uitvoering',
            'entity'   => 'order',
            'status'   => null,
        ],
        self::ORDER_UITGEVOERD_HERNIA->value => [
            'id'       => 44,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_ORDERS_ID->value,
            'label'    => 'Uitgevoerd, wachten op rapporten',
            'entity'   => 'order',
            'status'   => null,
        ],
        self::ORDER_RAPPORTEN_ONTVANGEN_HERNIA->value => [
            'id'       => 45,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_ORDERS_ID->value,
            'label'    => 'Rapporten vertalen',
            'entity'   => 'order',
            'status'   => null,
        ],
        self::ORDER_GEWONNEN_HERNIA->value => [
            'id'       => 46,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_ORDERS_ID->value,
            'label'    => 'Gewonnen',
            'entity'   => 'order',
            'status'   => 'won',
        ],
        self::ORDER_VERLOREN_HERNIA->value => [
            'id'       => 47,
            'pipeline' => PipelineDefaultKeys::PIPELINE_HERNIA_ORDERS_ID->value,
            'label'    => 'Verloren',
            'entity'   => 'order',
            'status'   => 'lost',
        ],

        // Tech
        self::NO_PIPELINE->value => [
            'id'       => 48,
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

    public static function getOrderStagesIdsBeforeConfirmed(): array
    {
        return [
            self::ORDER_CONFIRM->id(), self::ORDER_INGEPLAND->id(),
            self::ORDER_VOORBEREIDEN_HERNIA->id(), self::ORDER_INGEPLAND_HERNIA->id(),
        ];
    }

    public static function getOrderStagesIdsBeforePlanned(): array
    {
        return [
            self::ORDER_CONFIRM->id(),
            self::ORDER_VOORBEREIDEN_HERNIA->id(),
        ];
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
     * @return bool true if lead, otherwise sales/order/tech
     */
    public function isLead(): bool
    {
        return $this->meta()['entity'] === 'lead';
    }

    public function isOrder(): bool
    {
        return $this->meta()['entity'] === 'order';
    }

    public function toArray(): array
    {
        return [
            'id'               => $this->id(),
            'code'             => $this->value,
            'name'             => $this->name(),
            'probability'      => $this->isWon() ? 100 : ($this->isLost() ? 0 : 100),
            'sort_order'       => $this->id(),
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
     *   entity:'lead'|'sales'|'order'|'tech',
     *   status:'won'|'lost'|null
     * }
     */
    private function meta(): array
    {
        return self::META[$this->value]
            ?? throw new LogicException("Missing PipelineStage metadata for: {$this->value}");
    }
}
