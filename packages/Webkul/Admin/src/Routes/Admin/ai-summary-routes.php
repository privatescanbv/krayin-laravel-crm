<?php

use App\Http\Controllers\Admin\AiSummaryController;
use Illuminate\Support\Facades\Route;

/**
 * AI summary routes, shared by every subject in config/ai_summaries.php.
 *
 * {subject} is the subject key from that config (leads, persons, orders,
 * sales_leads). Permissions differ per subject, so they are enforced inside the
 * controller rather than through the route-based ACL map.
 */
Route::controller(AiSummaryController::class)
    ->prefix('ai-summary/{subject}/{id}')
    ->whereNumber('id')
    ->group(function () {
        Route::get('', 'show')->name('admin.ai-summary.show');
        Route::post('generate', 'generate')->name('admin.ai-summary.generate');
        Route::post('feedback', 'storeFeedback')->name('admin.ai-feedback.store');
        Route::put('feedback/{feedback}', 'updateFeedback')->name('admin.ai-feedback.update');
        Route::delete('feedback/{feedback}', 'destroyFeedback')->name('admin.ai-feedback.destroy');
    });
