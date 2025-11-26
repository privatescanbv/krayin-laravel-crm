<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        if (app()->runningInConsole()) {
            // Tijdens composer install/update geen logging uitvoeren
            return;
        }
        $this->reportable(function (Throwable $e) {
            try {
                // Log all exceptions that are reported
                Log::error('Exception reported', [
                    'exception' => get_class($e),
                    'message'   => $e->getMessage(),
                    'file'      => $e->getFile(),
                    'line'      => $e->getLine(),
                    'trace'     => $e->getTraceAsString(),
                    'url'       => optional(request())->fullUrl(),
                    'method'    => optional(request())->method(),
                    'user_id'   => optional(optional(auth()->guard('user'))->user())->id,
                ]);
            } catch (Exception $e) {
                Log::error('Could not log error for 500 status code', ['exception' => $e->getMessage()]);
            }
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        $response = parent::render($request, $exception);

        // Log all 500 errors with full stack trace
        if ($response->getStatusCode() === 500) {
            try {
                Log::error('500 Internal Server Error', [
                    'exception'    => get_class($exception),
                    'message'      => $exception->getMessage(),
                    'file'         => $exception->getFile(),
                    'line'         => $exception->getLine(),
                    'trace'        => $exception->getTraceAsString(),
                    'url'          => optional($request)->fullUrl(),
                    'method'       => optional($request)->method(),
                    'ip'           => optional($request)->ip(),
                    'user_id'      => optional(optional(auth()->guard('user'))->user())->id,
                    'request_data' => optional($request)->all(),
                    'headers'      => optional(optional($request)->headers)->all(),
                    'session_id'   => optional(session())->getId(),
                ]);
            } catch (Exception $e) {
                Log::error('Could not log error for 500 status code', ['exception' => $e->getMessage()]);
            }
        }

        return $response;
    }
}
