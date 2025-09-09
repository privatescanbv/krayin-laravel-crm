<?php

namespace App\Enums;

enum MRIStatus: string
{
    case NONE = 'None';
    case HAS_RECENT = 'HasRecent';
    case WANTS_VIA_US = 'WantsViaUs';
    case GETS_VIA_US = 'GetsViaUs';
    case SENDS_EXTERNAL = 'SendsExternal';
    case RECEIVED = 'Received';

    public function label(): string
    {
        return match ($this) {
            self::NONE           => __('Geen MRI-scan'),
            self::HAS_RECENT     => __('Heeft recente MRI (aangegeven in diagnose formulier)'),
            self::WANTS_VIA_US   => __('Wenst MRI via ons'),
            self::GETS_VIA_US    => __('Krijgt een MRI via ons'),
            self::SENDS_EXTERNAL => __('Stuurt extern gemaakte MRI op naar ons'),
            self::RECEIVED       => __('MRI beelden zijn binnen'),
        };
    }
}
