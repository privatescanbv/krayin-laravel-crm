<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Stub replacing the removed Webkul\Installer\Http\Middleware\CanInstall.
 * Always passes through — the installation check is no longer needed.
 */
class CanInstall
{
    public function handle(Request $request, Closure $next): mixed
    {
        return $next($request);
    }
}
