<?php

use App\Http\Controllers\Web\PatientForgotPasswordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect()->route('admin.session.create');
});

/*
|--------------------------------------------------------------------------
| Patient forgot-password flow
|--------------------------------------------------------------------------
|
| Used by the Keycloak login.ftl theme. The CRM owns the email + Keycloak
| Admin API password update (instead of Keycloak's built-in reset flow), so
| we can use the DB-backed email templates and Microsoft Graph mailer.
*/
Route::controller(PatientForgotPasswordController::class)
    ->prefix('patient')
    ->group(function () {
        Route::get('forgot-password', 'create')->name('patient.forgot-password.create');
        Route::post('forgot-password', 'store')->name('patient.forgot-password.store');

        Route::middleware('signed')->group(function () {
            Route::get('reset-password', 'showResetForm')->name('patient.reset-password');
            Route::post('reset-password', 'reset')->name('patient.reset-password.store');
        });
    });
