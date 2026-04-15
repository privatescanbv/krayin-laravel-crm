<?php

use App\Http\Controllers\Admin\Planning\OrderItemPlanningController;
use App\Http\Controllers\Admin\Planning\ResourcePlanningMonitorController;
use App\Http\Controllers\Admin\Settings\OrderController;
use App\Http\Controllers\Admin\Settings\OrderItemController;
use App\Http\Controllers\Admin\Settings\OrderPaymentController;
use Illuminate\Support\Facades\Route;

/**
 * Orders routes (top-level, not under settings).
 */
Route::controller(OrderController::class)->prefix('orders')->group(function () {
    Route::get('', 'index')->name('admin.orders.index');
    Route::get('get', 'get')->name('admin.orders.get');
    Route::get('payment-overview', 'paymentOverview')->name('admin.orders.payment-overview');
    Route::post('payment-overview', 'savePaymentOverview')->name('admin.orders.payment-overview.save');
    Route::get('create', 'create')->name('admin.orders.create');
    Route::post('create', 'store')->name('admin.orders.store');
    Route::get('view/{id}', 'view')->name('admin.orders.view');
    Route::get('edit/{id}', 'edit')->name('admin.orders.edit');
    Route::put('edit/{id}', 'update')->name('admin.orders.update');
    Route::put('{id}/stage', 'updateStage')->name('admin.orders.stage.update');
    Route::delete('', 'destroy')->name('admin.orders.bulk_delete');
    Route::delete('{id}', 'destroy')->name('admin.orders.delete');
    Route::post('{orderId}/gvl-form', 'attachGvlForm')->name('admin.orders.gvl-form.attach');
    Route::delete('{orderId}/gvl-form', 'detachGvlForm')->name('admin.orders.gvl-form.detach');
    Route::get('{orderId}/gvl-form/status', 'getGvlFormStatus')->name('admin.orders.gvl-form.status');
    Route::get('persons/{salesLeadId}', 'getPersonsForSalesLead')->name('admin.orders.persons');
    Route::get('{orderId}/mail/preview', 'mailPreview')->name('admin.orders.mail.preview');
    Route::post('{orderId}/status/sent', 'markAsSent')->name('admin.orders.status.sent');
    Route::get('{orderId}/confirm', 'confirm')->name('admin.orders.confirm');
    Route::post('{id}/send-afb', 'sendAfb')->name('admin.orders.send_afb');

    // Order confirmation letter routes
    Route::get('confirmation/templates', 'getConfirmationTemplates')->name('admin.orders.confirmation.templates');
    Route::get('{orderId}/confirmation/template-content', 'getConfirmationTemplateContent')->name('admin.orders.confirmation.template-content');
    Route::post('{orderId}/confirmation/save', 'saveConfirmationLetter')->name('admin.orders.confirmation.save');
    Route::post('{orderId}/confirmation/preview-pdf', 'previewConfirmationLetterPdf')->name('admin.orders.confirmation.preview-pdf');
    Route::get('{orderId}/confirmation/export-pdf', 'exportConfirmationLetterPDF')->name('admin.orders.confirmation.export-pdf');

    // Per-person confirmation routes (combine_order = false)
    Route::get('{orderId}/confirmation/persons-status', 'personsConfirmationStatus')->name('admin.orders.confirmation.persons-status');
    Route::get('{orderId}/confirmation/person/{personId}/template-content', 'getPersonConfirmationTemplateContent')->name('admin.orders.confirmation.person.template-content');
    Route::post('{orderId}/confirmation/person/{personId}/save', 'savePersonConfirmationLetter')->name('admin.orders.confirmation.person.save');
    Route::post('{orderId}/confirmation/person/{personId}/preview-pdf', 'previewPersonConfirmationPdf')->name('admin.orders.confirmation.person.preview-pdf');
    Route::post('{orderId}/confirmation/person/{personId}/sent', 'markPersonAsSent')->name('admin.orders.confirmation.person.sent');
    Route::get('{orderId}/confirmation/person/{personId}/mail-preview', 'personMailPreview')->name('admin.orders.confirmation.person.mail-preview');

    // Order checks routes
    Route::post('{orderId}/checks', 'storeCheck')->name('admin.orders.checks.store');
    Route::put('{orderId}/checks/{checkId}', 'updateCheck')->name('admin.orders.checks.update');
    Route::delete('{orderId}/checks/{checkId}', 'destroyCheck')->name('admin.orders.checks.destroy');

    // Order activities routes
    Route::get('{id}/activities', 'activities')->name('admin.orders.activities.index');
    Route::get('{id}/emails/detach', 'emailsDetach')->name('admin.orders.emails.detach');
});

/**
 * Order payment routes.
 */
Route::controller(OrderPaymentController::class)
    ->prefix('orders/{orderId}/payments')
    ->group(function () {
        Route::post('', 'store')->name('admin.orders.payments.store');
        Route::put('{paymentId}', 'update')->name('admin.orders.payments.update');
        Route::delete('{paymentId}', 'destroy')->name('admin.orders.payments.destroy');
    });

/**
 * Order items routes (top-level, not under settings).
 */
Route::controller(OrderItemController::class)->prefix('order-items')->group(function () {
    Route::get('', 'index')->name('admin.order_items.index');
    Route::get('create', 'create')->name('admin.order_items.create');
    Route::post('create', 'store')->name('admin.order_items.store');
    Route::get('edit/{id}', 'edit')->name('admin.order_items.edit');
    Route::put('edit/{id}', 'update')->name('admin.order_items.update');
    Route::delete('', 'destroy')->name('admin.order_items.bulk_delete');
    Route::delete('{id}', 'destroy')->name('admin.order_items.delete');
    Route::get('partner-purchase-prices/{productId}', 'getPartnerPurchasePrices')
        ->name('admin.order_items.partner_purchase_prices');
});

/**
 * Planning routes.
 */
Route::middleware(['user'])->controller(OrderItemPlanningController::class)->prefix('planning')->group(function () {
    Route::get('order-item/{orderItemId}/availability', 'availability')->name('admin.planning.order_item.availability');
    Route::post('order-item/{orderItemId}/book', 'book')->name('admin.planning.order_item.book');
});

/**
 * Planning Monitor routes.
 */
Route::middleware(['user'])->controller(ResourcePlanningMonitorController::class)->prefix('planning/monitor')->group(function () {
    Route::get('', 'index')->name('admin.planning.monitor.index');
    Route::get('availability', 'availability')->name('admin.planning.monitor.availability');
    Route::get('order/{orderId}', 'orderPlanning')->name('admin.planning.monitor.order');
    Route::get('order/{orderId}/availability', 'orderAvailability')->name('admin.planning.monitor.order.availability');
    Route::get('order/{orderId}/resource-types', 'orderResourceTypes')->name('admin.planning.monitor.order.resource_types');
    Route::post('order-item/{orderItemId}/book', 'bookOrderItem')->name('admin.planning.monitor.order_item.book');
});
