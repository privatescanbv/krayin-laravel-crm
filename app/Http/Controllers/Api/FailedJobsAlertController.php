<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\FailedJobsCriticalAlert;
use App\Mail\FailedJobsWarningAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class FailedJobsAlertController extends Controller
{
    /**
     * Mail a failed-jobs alert on behalf of another app (e.g. privateforms, which has no mailer).
     *
     * Recipients come from this app's FAILED_JOBS_ALERT_EMAIL, so callers cannot choose
     * addresses or content.
     *
     * Protected by X-API-KEY (service-to-service).
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'level' => ['required', 'in:warning,critical'],
            'count' => ['required', 'integer', 'min:0'],
            'app'   => ['required', 'string', 'max:100'],
        ]);

        $recipients = array_values(array_filter(array_map('trim', explode(',', (string) config('failed_jobs.alert_email', '')))));

        if ($recipients === []) {
            return response()->json(['message' => 'Failed jobs alerting is uitgeschakeld.']);
        }

        try {
            foreach ($recipients as $recipient) {
                Mail::to($recipient)->send($validated['level'] === 'critical'
                    ? new FailedJobsCriticalAlert($validated['count'], $validated['app'])
                    : new FailedJobsWarningAlert($validated['count'], $validated['app']));
            }
        } catch (Throwable $e) {
            Log::error('Failed jobs alert mail failed', [
                'app'   => $validated['app'],
                'level' => $validated['level'],
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Verzenden van de failed jobs alert is mislukt.'], 500);
        }

        return response()->json(['message' => 'Failed jobs alert verzonden.']);
    }
}
