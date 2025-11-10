<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Mail\EmailController;
use Webkul\Admin\Http\Controllers\Mail\TagController;

Route::prefix('mail')->group(function () {
    Route::controller(EmailController::class)->group(function () {
        // Specific routes must come FIRST, before any catch-all routes
        Route::get('templates', 'getTemplates')->name('admin.mail.templates');

        Route::get('template-content', 'getTemplateContent')->name('admin.mail.template_content');

        Route::post('create', 'store')->name('admin.mail.store');

        Route::put('edit/{id}', 'update')->name('admin.mail.update');

        Route::get('attachment-download/{id?}', 'download')->name('admin.mail.attachment_download');

        Route::post('mass-update', 'massUpdate')->name('admin.mail.mass_update');

        Route::post('mass-destroy', 'massDestroy')->name('admin.mail.mass_delete');

        Route::post('move/{id}', 'move')->name('admin.mail.move');

        Route::post('inbound-parse', 'inboundParse')->name('admin.mail.inbound_parse')->withoutMiddleware('user');

        // Catch-all routes must come last
        Route::get('{route}/{id}', 'view')->name('admin.mail.view');

        Route::delete('{id}', 'destroy')->name('admin.mail.delete');

        Route::get('{route?}', 'index')->name('admin.mail.index');
    });

    Route::controller(TagController::class)->prefix('{id}/tags')->group(function () {
        Route::post('', 'attach')->name('admin.mail.tags.attach');

        Route::delete('', 'detach')->name('admin.mail.tags.detach');
    });
});
