<?php

use App\Http\Controllers\Admin\AfletterenController;
use Illuminate\Support\Facades\Route;

Route::controller(AfletterenController::class)->prefix('afletteren')->group(function () {
    Route::get('', 'index')->name('admin.afletteren.index');
    Route::get('payments', 'payments')->name('admin.afletteren.payments');
});
