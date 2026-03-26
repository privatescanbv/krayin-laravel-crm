<?php

use App\Http\Controllers\Admin\ClinicGuideController;
use Illuminate\Support\Facades\Route;

Route::controller(ClinicGuideController::class)->prefix('clinic-guide')->group(function () {
    Route::get('', 'index')->name('admin.clinic-guide.index');
    Route::get('get', 'get')->name('admin.clinic-guide.get');
    Route::get('afb-pdf/{personDocumentId}', 'viewAfbPdf')->name('admin.clinic-guide.afb-pdf.view');
});
