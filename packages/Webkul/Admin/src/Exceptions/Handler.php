<?php

namespace Webkul\Admin\Exceptions;

use App\Exceptions\Handler as AppExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends AppExceptionHandler
{
    /**
     * Json error messages.
     *
     * @var array
     */
    protected $jsonErrorMessages = [];

    /**
     * Create handler instance.
     *
     * @return void
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->jsonErrorMessages = [
            '404' => trans('admin::app.common.resource-not-found'),
            '403' => trans('admin::app.common.forbidden-error'),
            '401' => trans('admin::app.common.unauthenticated'),
            '500' => trans('admin::app.common.internal-server-error'),
        ];
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function render($request, Throwable $exception)
    {
        // Skip logging for routine HTTP 404s (favicon, missing assets, etc.)
        if ($exception instanceof HttpException && $exception->getStatusCode() === 404) {
            return $this->renderCustomResponse($exception);
        }

        // A missing/deleted record (stale link, direct URL guess) is a routine 404, not an application error
        if ($exception instanceof ModelNotFoundException) {
            Log::warning('Admin: model not found', [
                'model'   => $exception->getModel(),
                'ids'     => $exception->getIds(),
                'url'     => $request->fullUrl(),
                'user_id' => auth()->guard('user')->id(),
            ]);

            return $this->renderCustomResponse($exception);
        }

        // No Log::error here: report() already sends the exception to Sentry (grouped per exception)
        // and logs its context (bootstrap/app.php). A fixed-message log on top lumped every admin
        // error into one Bugsink issue ("Admin exception occurred", PRIVATESCAN_CRM_PROD-3).
        if (! config('app.debug')) {
            return $this->renderCustomResponse($exception);
        }
            return parent::render($request, $exception);
    }

    /**
     * Convert an authentication exception into a response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->jsonErrorMessages[401]], 401);
        }

        return redirect()->guest(route('customer.session.index'));
    }

    /**
     * Render custom HTTP response.
     *
     * @return \Illuminate\Http\Response|null
     */
    private function renderCustomResponse(Throwable $exception)
    {
        if ($exception instanceof HttpException) {
            $statusCode = in_array($exception->getStatusCode(), [401, 403, 404, 503])
                ? $exception->getStatusCode()
                : 500;

            return $this->response($statusCode);
        }

        if ($exception instanceof ValidationException) {
            return parent::render(request(), $exception);
        }

        if ($exception instanceof ModelNotFoundException) {
            // Already logged as a warning in render() above.
            return $this->response(404);
        }

        // Already reported (Sentry + log) by report(); see render().
        return $this->response(500);
    }

    /**
     * Return custom response.
     *
     * @param  string  $path
     * @param  string  $errorCode
     * @return mixed
     */
    private function response($errorCode)
    {
        if (request()->expectsJson()) {
            return response()->json([
                'message' => isset($this->jsonErrorMessages[$errorCode])
                    ? $this->jsonErrorMessages[$errorCode]
                    : trans('admin::app.common.something-went-wrong'),
            ], $errorCode);
        }

        return response()->view('admin::errors.index', ['errorCode'=>'errorCode'], $errorCode);
    }
}
