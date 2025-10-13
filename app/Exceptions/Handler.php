<?php

namespace App\Exceptions;

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
        $this->reportable(function (Throwable $e) {
            // Log all exceptions that are reported
            Log::error('Exception reported', [
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => $e->getTraceAsString(),
                'url'       => request()->fullUrl(),
                'method'    => request()->method(),
                'user_id'   => auth()->guard('user')->id(),
            ]);
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
            Log::error('500 Internal Server Error', [
                'exception'    => get_class($exception),
                'message'      => $exception->getMessage(),
                'file'         => $exception->getFile(),
                'line'         => $exception->getLine(),
                'trace'        => $exception->getTraceAsString(),
                'url'          => $request->fullUrl(),
                'method'       => $request->method(),
                'ip'           => $request->ip(),
                'user_id'      => auth()->guard('user')->id(),
                'request_data' => $request->all(),
                'headers'      => $request->headers->all(),
                'session_id'   => session()->getId(),
            ]);
        }

        return $response;
    }
}
