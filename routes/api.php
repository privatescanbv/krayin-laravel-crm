<?php

use App\Http\Controllers\Api\EventWebhookController;
use App\Http\Controllers\Api\KeycloakUserController;
use App\Http\Controllers\Api\KeycloakWebhookController;
use App\Http\Controllers\Api\PatientAppointmentController;
use App\Http\Controllers\Api\PatientDocumentController;
use App\Http\Controllers\Api\PatientMessageController;
use App\Http\Controllers\Api\PersonActivityController;
use App\Http\Controllers\Api\SalesLeadController;
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
        Route::post('{id}/activities', [ActivityController::class, 'store'])->name('admin.leads.activities.store');
        Route::get('{id}/activities', [ActivityController::class, 'index']);
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
        ->name('api.keycloak.persons.findByKeycloakId');

    // Person patient messages, by keycloak user id
    Route::get('patient/{id}/messages', [PersonActivityController::class, 'index']);
    Route::post('patient/{id}/messages', [PersonActivityController::class, 'store']);
    Route::put('patient/{id}/messages/mark_as_read', [PersonActivityController::class, 'markAsRead']);
    Route::get('patient/{id}/activities/unread/count', [PatientMessageController::class, 'unreadCount']);

    // Patient appointments (derived from Orders), by keycloak user id
    Route::get('patient/{id}/appointments', [PatientAppointmentController::class, 'index']);

    // Patient documents (derived from Orders -> Activities type=file)
    Route::get('patient/{id}/documents', [PatientDocumentController::class, 'index'])
        ->name('api.patient.documents.index');
    Route::get('patient/{id}/documents/{documentId}/download', [PatientDocumentController::class, 'download'])
        ->name('api.patient.documents.download');
};

// All API routes are protected by ApiKeyAuth middleware, which supports:
// - X-API-KEY header validation
// - OR a valid Keycloak Bearer token in the Authorization header
Route::middleware('api.key')->group($registerAuthenticatedApiRoutes);
