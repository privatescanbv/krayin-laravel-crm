<?php

use App\Http\Controllers\Api\EventWebhookController;
use App\Http\Controllers\Api\FailedJobsAlertController;
use App\Http\Controllers\Api\KeycloakUserController;
use App\Http\Controllers\Api\KeycloakWebhookController;
use App\Http\Controllers\Api\LeadFormController;
use App\Http\Controllers\Api\SalesLeadController;
use App\Http\Controllers\Api\TwoFactorCodeController;
use App\Http\Controllers\LeadNoteController;
use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Lead\ActivityController;
use Webkul\Admin\Http\Controllers\Settings\GroupController;
use Webkul\Lead\Http\Controllers\Api\LeadController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Shared API route registration used by both API-key and Keycloak-authenticated clients
$registerAuthenticatedApiRoutes = function () {
    // Lead routes
    Route::prefix('leads')->group(function () {
        // Removed index route - too heavy, use /admin/leads/get for kanban instead
        Route::post('hernia', [LeadController::class, 'storeHernia']);
        Route::post('privatescan', [LeadController::class, 'storePrivatescan']);
        Route::post('/', [LeadController::class, 'store']);
        Route::get('{id}', [LeadController::class, 'show']);
        Route::put('{id}', [LeadController::class, 'update']);
        Route::patch('{id}/stage', [LeadController::class, 'updateStage']);
        Route::patch('{id}/next_stage', [LeadController::class, 'nextStage']);
        Route::delete('{id}', [LeadController::class, 'destroy']);
        // notes
        Route::post('{leadId}/notes', [LeadNoteController::class, 'store']);

        // Lead activities
        Route::post('{id}/activities', [ActivityController::class, 'store'])->name('api.leads.activities.store');
        Route::get('{id}/activities', [ActivityController::class, 'index']);

        // Lead forms
        Route::post('{leadId}/forms', [LeadFormController::class, 'store'])->name('api.leads.forms.store');
    });

    // Existing routes
    Route::get('groups/byDepartment/{departmentName}', [GroupController::class, 'findByDepartment']);

    // Workflow Leads API
    Route::prefix('sales-leads')->group(function () {
        Route::post('/', [SalesLeadController::class, 'store']);
        // Sales activities
        Route::get('{id}/activities', [SalesLeadController::class, 'activities']);
        Route::post('{id}/activities', [SalesLeadController::class, 'storeActivity']);
    });

    // Backward-compatible singular prefix for n8n (sales-lead)
    Route::prefix('sales-leads')->group(function () {
        Route::get('{id}/activities', [SalesLeadController::class, 'activities']);
        Route::post('{id}/activities', [SalesLeadController::class, 'storeActivity']);
    });

    // Generic application webhooks
    Route::put('webhooks/event', EventWebhookController::class)
        ->name('api.webhooks.event');

    // Keycloak webhooks
    Route::post('keycloak/webhooks', KeycloakWebhookController::class)
        ->name('api.keycloak.webhooks');

    // Keycloak mapping: get person id for a given Keycloak user id
    Route::get('keycloak/persons/{keycloakUserId}', [KeycloakUserController::class, 'findPersonByKeycloakId'])
        ->middleware('patient.self:keycloakUserId')
        ->name('api.keycloak.persons.findByKeycloakId');

    // Clinic-portal routes (own file for overview; same middleware group)
    require __DIR__.'/api/clinic.php';

    // 2FA verification code mail (API-key only; used by the forms/patient portal for all user types)
    Route::post('two-factor/code', TwoFactorCodeController::class)
        ->middleware('throttle:two-factor-code')
        ->name('api.two-factor.code');

    // Failed-jobs alert mail for other apps (API-key only; recipients come from this app's FAILED_JOBS_ALERT_EMAIL)
    Route::post('failed-jobs/alert', FailedJobsAlertController::class)
        ->name('api.failed-jobs.alert');

    // Patient-portal routes (own file for overview; same middleware group)
    require __DIR__.'/api/patient.php';
};

// All API routes are protected by ApiKeyAuth middleware, which supports:
// - X-API-KEY header validation
// - OR a valid Keycloak Bearer token in the Authorization header
Route::middleware(['log.api.http', 'api.key'])->group($registerAuthenticatedApiRoutes);
