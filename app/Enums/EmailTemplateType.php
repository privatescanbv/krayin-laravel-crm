<?php

namespace App\Enums;

enum EmailTemplateType: string
{
    case LEAD = 'lead';
    case ALGEMEEN = 'algemeen';
    case ORDER_ACKNOWLEDGEMENT = 'order-acknowledgement';
    case ORDER_APPOINTMENT_CONFIRMATION = 'order-appointment-confirmation';
    case GVL = 'gvl';
    case PATIENT = 'patient';

    /**
     * Get all values as array
     */
    public static function allValues(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    /**
     * Get all cases as array with labels
     */
    public static function allWithLabels(): array
    {
        return array_map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }

    /**
     * Resolve filter values for `GET .../mail/templates?entity_type=...`.
     *
     * @return list<string>|null null when $entityType is not a valid enum value (caller applies no type filter)
     */
    public static function tryResolveTemplateTypeFilter(string $entityType): ?array
    {
        $case = self::tryFrom($entityType);

        if ($case === null) {
            return null;
        }

        return $case->templateTypeFilterValues();
    }

    public function label(): string
    {
        return match ($this) {
            self::LEAD                           => 'Lead',
            self::ALGEMEEN                       => 'Algemeen',
            self::ORDER_ACKNOWLEDGEMENT          => 'Order',
            self::ORDER_APPOINTMENT_CONFIRMATION => 'Order afspraakbevestiging',
            self::GVL                            => 'GVL',
            self::PATIENT                        => 'Patient',
        };
    }

    /**
     * Which `email_templates.type` values belong to this case when filtering the mail template list.
     * New enum cases default to a single type; override the match only for special combinations (e.g. lead + algemeen).
     *
     * @return list<string>
     */
    public function templateTypeFilterValues(): array
    {
        return match ($this) {
            self::LEAD => [self::LEAD->value, self::ALGEMEEN->value],
            default    => [$this->value],
        };
    }
}
