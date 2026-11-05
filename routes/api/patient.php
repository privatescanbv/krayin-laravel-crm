<?php

use App\Http\Controllers\Api\PatientAppointmentController;
use App\Http\Controllers\Api\PatientCounterController;
use App\Http\Controllers\Api\PatientDocumentController;
use App\Http\Controllers\Api\PatientFileUploadController;
use App\Http\Controllers\Api\PatientForgotPasswordController;
use App\Http\Controllers\Api\PatientNawController;
use App\Http\Controllers\Api\PatientNotificationController;
use App\Http\Controllers\Api\PatientPasswordController;
use App\Http\Controllers\Api\PatientPreferenceController;
use App\Http\Controllers\Api\PersonActivityController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Patient-portal API routes
|--------------------------------------------------------------------------
|
| Loaded from routes/api.php inside the shared `log.api.http` + `api.key` group
| (X-API-KEY, or a Keycloak Bearer token that must match {id}).
|
*/

// Patient unauthenticated-flow routes (API-key only; no patient token available yet)
Route::post('patient/forgot-password', [PatientForgotPasswordController::class, 'store'])
    ->middleware('throttle:patient-forgot-password')
    ->name('api.patient.forgot-password');

// Patient routes (Keycloak Bearer token must match {id}; API key callers are service-to-service)
Route::prefix('patient/{id}')
    ->middleware('patient.self:id')
    ->group(function () {
        // Person patient messages, by keycloak user id
        Route::get('messages', [PersonActivityController::class, 'index']);
        Route::post('messages', [PersonActivityController::class, 'store']);
        Route::put('messages/mark_as_read', [PersonActivityController::class, 'markAsRead']);

        // Patient counters (menu badges): unread messages + future appointments
        Route::get('counters', PatientCounterController::class)
            ->name('api.patient.counters');

        // Patient appointments (derived from Orders), by keycloak user id
        Route::get('appointments', [PatientAppointmentController::class, 'index']);

        // Patient documents (derived from Orders -> Activities type=file)
        Route::get('documents', [PatientDocumentController::class, 'index'])
            ->name('api.patient.documents.index');
        Route::post('documents', [PatientFileUploadController::class, 'store'])
            ->name('api.patient.documents.store');
        Route::get('documents/{documentId}/download', [PatientDocumentController::class, 'download'])
            ->name('api.patient.documents.download');

        // Patient notifications
        Route::get('notifications', [PatientNotificationController::class, 'index'])
            ->name('api.patient.notifications.index');
        Route::post('notifications/{notificationId}/read', [PatientNotificationController::class, 'markAsRead'])
            ->name('api.patient.notifications.read');

        // Patient NAW (personal details + address)
        Route::get('naw', [PatientNawController::class, 'show'])
            ->name('api.patient.naw.show');
        Route::put('naw', [PatientNawController::class, 'update'])
            ->name('api.patient.naw.update');

        // Patient password
        Route::put('password', [PatientPasswordController::class, 'update'])
            ->name('api.patient.password.update');
        Route::post('password/reset', [PatientPasswordController::class, 'reset'])
            ->name('api.patient.password.reset');

        // Patient preferences
        Route::get('preferences', [PatientPreferenceController::class, 'index'])
            ->name('api.patient.preferences.index');
        Route::put('preferences', [PatientPreferenceController::class, 'update'])
            ->name('api.patient.preferences.update');
    });
