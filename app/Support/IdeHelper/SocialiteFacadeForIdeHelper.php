<?php

namespace App\Support\IdeHelper;

use Illuminate\Support\Facades\Facade;

/**
 * Shim facade used only during ide-helper generation.
 *
 * `barryvdh/laravel-ide-helper` calls `::fake()` on facades with a `fake()` method
 * to generate better docblocks. Laravel Socialite's `fake()` requires arguments,
 * which causes `ide-helper:generate` to crash.
 *
 * This shim provides a zero-argument compatible `fake()` to keep generation working.
 */
class SocialiteFacadeForIdeHelper extends Facade
{
    public static function fake($callback = null): object
    {
        // Minimal object; ide-helper only needs something reflectable.
        return new class {};
    }

    protected static function getFacadeAccessor(): string
    {
        return 'socialite';
    }
}
