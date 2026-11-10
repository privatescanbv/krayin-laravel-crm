<?php

use App\Http\Controllers\Api\ClinicPasswordResetController;
use App\Http\Controllers\Api\ClinicProfileController;
use App\Http\Controllers\Api\ClinicSalesLeadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Clinic-portal API routes
|--------------------------------------------------------------------------
|
| Loaded from routes/api.php inside the shared `log.api.http` + `api.key` group
| (X-API-KEY, or a Keycloak Bearer token on the routes listed in
| ApiKeyAuth::allowsKeycloakBearerFallback()).
|
*/

// Forgot-password flow (API-key only; called by the clinic portal)
Route::post('clinic/forgot-password', [ClinicPasswordResetController::class, 'store'])
    ->middleware('throttle:clinic-forgot-password')
    ->name('api.clinic.forgot-password');
Route::post('clinic/{keycloakUserId}/password/reset', [ClinicPasswordResetController::class, 'reset'])
    ->middleware('throttle:clinic-forgot-password')
    ->name('api.clinic.password.reset');

// Routes of the logged-in clinic user (Keycloak Bearer token must match {keycloakUserId}; API key callers are service-to-service)
Route::prefix('clinic/{keycloakUserId}')
    ->middleware('patient.self:keycloakUserId')
    ->group(function () {
        // Profile
        Route::get('naw', [ClinicProfileController::class, 'show'])->name('api.clinic.naw.show');
        Route::put('naw', [ClinicProfileController::class, 'update'])->name('api.clinic.naw.update');
        Route::put('password', [ClinicProfileController::class, 'updatePassword'])->name('api.clinic.password.update');

        // Sales kanban of a doctor (role Arts)
        Route::get('sales-stages', [ClinicSalesLeadController::class, 'stages'])->name('api.clinic.sales-stages');
        Route::get('sales-leads', [ClinicSalesLeadController::class, 'index'])->name('api.clinic.sales-leads');
        Route::put('sales-leads/{saleId}/stage', [ClinicSalesLeadController::class, 'updateStage'])->whereNumber('saleId')->name('api.clinic.sales-leads.stage');
        Route::get('sales-leads/{saleId}', [ClinicSalesLeadController::class, 'show'])->whereNumber('saleId')->name('api.clinic.sales-leads.show');
        Route::get('sales-leads/{saleId}/notes', [ClinicSalesLeadController::class, 'notes'])->whereNumber('saleId')->name('api.clinic.sales-leads.notes');
        Route::post('sales-leads/{saleId}/notes', [ClinicSalesLeadController::class, 'storeNote'])->whereNumber('saleId')->name('api.clinic.sales-leads.notes.store');
    });
