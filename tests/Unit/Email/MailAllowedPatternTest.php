<?php

use App\Services\Mail\MicrosoftGraphMailTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('email allowed pattern testing', function () {

    /*
     |--------------------------------------------------------------------------
     | Exact match
     |--------------------------------------------------------------------------
     */
    expect(
        MicrosoftGraphMailTransport::matchesAnyPattern(
            'mbulthuis@gmail.com',
            ['mbulthuis@gmail.com']
        )
    )->toBeTrue()
        ->and(
            MicrosoftGraphMailTransport::matchesAnyPattern(
                'jan@privatescan.nl',
                ['*privatescan.nl']
            )
        )->toBeTrue()
        ->and(
            MicrosoftGraphMailTransport::matchesAnyPattern(
                'jan@other.nl',
                ['*privatescan.nl']
            )
        )->toBeFalse()
        ->and(
            MicrosoftGraphMailTransport::matchesAnyPattern(
                'jan@gmail.com',
                ['jan*@gmail.com']
            )
        )->toBeTrue()
        ->and(
            MicrosoftGraphMailTransport::matchesAnyPattern(
                'jan+priv@gmail.com',
                ['jan*@gmail.com']
            )
        )->toBeTrue()
        ->and(
            MicrosoftGraphMailTransport::matchesAnyPattern(
                'jan+test123@gmail.com',
                ['jan*@gmail.com']
            )
        )->toBeTrue()
        ->and(
            MicrosoftGraphMailTransport::matchesAnyPattern(
                'piet@gmail.com',
                ['jan*@gmail.com']
            )
        )->toBeFalse()
        ->and(
            MicrosoftGraphMailTransport::matchesAnyPattern(
                'jan+priv@outlook.com',
                ['jan*@gmail.com']
            )
        )->toBeFalse();

    /*
     |--------------------------------------------------------------------------
     | Wildcard domain match
     |--------------------------------------------------------------------------
     */

    /*
     |--------------------------------------------------------------------------
     | Plus addressing with wildcard local part
     |--------------------------------------------------------------------------
     */

    /*
     |--------------------------------------------------------------------------
     | Negative cases for wildcard local part
     |--------------------------------------------------------------------------
     */

    /*
     |--------------------------------------------------------------------------
     | Multiple patterns
     |--------------------------------------------------------------------------
     */
    $allowedPatterns = [
        'mbulthuis@gmail.com',
        'jan*@gmail.com',
        '*privatescan.nl',
    ];

    expect(
        MicrosoftGraphMailTransport::matchesAnyPattern(
            'jan+priv@gmail.com',
            $allowedPatterns
        )
    )->toBeTrue()
        ->and(
            MicrosoftGraphMailTransport::matchesAnyPattern(
                'user@privatescan.nl',
                $allowedPatterns
            )
        )->toBeTrue()
        ->and(
            MicrosoftGraphMailTransport::matchesAnyPattern(
                'hacker@evil.com',
                $allowedPatterns
            )
        )->toBeFalse();

});
