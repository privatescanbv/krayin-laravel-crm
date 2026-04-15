<?php

use App\Console\Kernel as ConsoleKernel;
use App\Http\Kernel as HttpKernel;
use App\Http\Middleware\ApiKeyAuth;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\BouncerPermissionMiddleware;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\EnsureKeycloakPatientMatchesRoute;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @var Application $app */
$app = Application::configure(basePath: dirname(__DIR__))
    ->withEvents(false)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn () => route('admin.session.create'));

        $middleware->replace(
            \Illuminate\Http\Middleware\TrustProxies::class,
            TrustProxies::class,
        );

        $middleware->replace(
            \Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class,
            PreventRequestsDuringMaintenance::class,
        );

        $middleware->replace(
            \Illuminate\Foundation\Http\Middleware\TrimStrings::class,
            TrimStrings::class,
        );

        $middleware->remove(ConvertEmptyStringsToNull::class);

        $middleware->replaceInGroup(
            'web',
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            EncryptCookies::class,
        );

        $middleware->replaceInGroup(
            'web',
            PreventRequestForgery::class,
            VerifyCsrfToken::class,
        );

        $middleware->statefulApi();
        $middleware->throttleApi('api');

        $middleware->alias([
            'auth'               => Authenticate::class,
            'guest'              => RedirectIfAuthenticated::class,
            'api.key'            => ApiKeyAuth::class,
            'patient.self'       => EnsureKeycloakPatientMatchesRoute::class,
            'bouncer.permission' => BouncerPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
        ]);

        $exceptions->dontReport([
            NotFoundHttpException::class,
        ]);

        $exceptions->reportable(function (Throwable $e) {
            if (app()->runningInConsole()) {
                return;
            }

            try {
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
            } catch (Exception $logException) {
                Log::error('Could not log reported exception', ['exception' => $logException->getMessage()]);
            }

            return false;
        });

        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($response->getStatusCode() !== 500) {
                return $response;
            }

            try {
                Log::error('500 Internal Server Error', [
                    'exception'    => get_class($e),
                    'message'      => $e->getMessage(),
                    'file'         => $e->getFile(),
                    'line'         => $e->getLine(),
                    'trace'        => $e->getTraceAsString(),
                    'url'          => optional($request)->fullUrl(),
                    'method'       => optional($request)->method(),
                    'ip'           => optional($request)->ip(),
                    'user_id'      => optional(optional(auth()->guard('user'))->user())->id,
                    'request_data' => optional($request)->all(),
                    'headers'      => optional(optional($request)->headers)->all(),
                    'session_id'   => optional(session())->getId(),
                ]);
            } catch (Exception $logException) {
                Log::error('Could not log 500 response', ['exception' => $logException->getMessage()]);
            }

            return $response;
        });
    })
    ->create();

$app->singleton(HttpKernelContract::class, HttpKernel::class);
$app->singleton(ConsoleKernelContract::class, ConsoleKernel::class);

return $app;
