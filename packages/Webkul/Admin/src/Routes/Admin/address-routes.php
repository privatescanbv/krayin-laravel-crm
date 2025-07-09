<?php

use App\Http\Controllers\Admin\AddressLookupController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['user']], function () {
    Route::get('address/lookup', [AddressLookupController::class, 'addressLookup']);
});
