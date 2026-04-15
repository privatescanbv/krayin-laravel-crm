<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

/**
 * Base exception handler. Shared defaults are registered in bootstrap/app.php (withExceptions).
 * The admin UI uses Webkul\Admin\Exceptions\Handler (bound in AdminServiceProvider).
 */
class Handler extends ExceptionHandler
{
    //
}
