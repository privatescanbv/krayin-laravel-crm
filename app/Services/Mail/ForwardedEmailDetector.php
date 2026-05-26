<?php

namespace App\Services\Mail;

class ForwardedEmailDetector
{
    /**
     * @var list<string>
     */
    private const BODY_MARKERS = [
        '-----Original Message-----',
        'Begin forwarded message',
        'Doorgestuurd bericht',
        'Oorspronkelijk bericht',
        'Van:',
        'From:',
    ];

    public static function looksLikeForward(string $subject, string $body): bool
    {
        if (preg_match('/^(fw|fwd|doorgestuurd|forward)\s*:/i', trim($subject))) {
            return true;
        }

        $normalizedBody = html_entity_decode(strip_tags($body));

        foreach (self::BODY_MARKERS as $marker) {
            if (str_contains($normalizedBody, $marker)) {
                return true;
            }
        }

        return false;
    }
}
