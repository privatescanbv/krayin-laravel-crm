<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
            // 404s niet loggen — vervuilen de logs met browser-probes (favicon, logo.png, etc.)
            if ($e instanceof NotFoundHttpException) {
                return false;
            }

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

        // When APP_DEBUG is false, never expose exception class, SQL, paths, or stack traces in JSON.
        if ($this->shouldSanitizeJsonErrorResponse($request, $response)) {
            $status = $response->getStatusCode();

            return response()->json([
                'message' => $status === 503
                    ? 'Service temporarily unavailable.'
                    : 'Server Error',
            ], $status);
        }

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

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     */
    private function shouldSanitizeJsonErrorResponse($request, $response): bool
    {
        // When debug is enabled, keep Laravel's detailed JSON errors for local development.
        if (config('app.debug')) {
            return false;
        }

        if (! $request->expectsJson() && ! $request->is('api/*')) {
            return false;
        }

        if ($response->getStatusCode() < 500) {
            return false;
        }

        if (! $response instanceof JsonResponse) {
            return false;
        }

        $data = $response->getData(true);

        return is_array($data) && array_key_exists('exception', $data);
    }
}
