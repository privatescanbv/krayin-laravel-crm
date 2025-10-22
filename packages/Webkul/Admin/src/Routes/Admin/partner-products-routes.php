<?php

use App\Http\Controllers\Admin\Settings\ClinicController;
use App\Http\Controllers\Admin\Settings\ImportLogController;
use App\Http\Controllers\Admin\Settings\ImportRunController;
use App\Http\Controllers\Admin\Settings\PartnerProductController;
use Webkul\Admin\Http\Controllers\Settings\PartnerProducts\ActivityController as PartnerProductActivityController;
use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Settings\AttributeController;
use Webkul\Admin\Http\Controllers\Settings\DataTransfer\ImportController;
use Webkul\Admin\Http\Controllers\Settings\EmailTemplateController;
use Webkul\Admin\Http\Controllers\Settings\GroupController;
use Webkul\Admin\Http\Controllers\Settings\LocationController;
use Webkul\Admin\Http\Controllers\Settings\Marketing\CampaignsController;
use Webkul\Admin\Http\Controllers\Settings\Marketing\EventController;
use Webkul\Admin\Http\Controllers\Settings\PipelineController;
use Webkul\Admin\Http\Controllers\Settings\RoleController;
use Webkul\Admin\Http\Controllers\Settings\SettingController;
use Webkul\Admin\Http\Controllers\Settings\SourceController;
use Webkul\Admin\Http\Controllers\Settings\TagController;
use Webkul\Admin\Http\Controllers\Settings\TypeController;
use Webkul\Admin\Http\Controllers\Settings\UserController;
use Webkul\Admin\Http\Controllers\Settings\Warehouse\ActivityController;
use Webkul\Admin\Http\Controllers\Settings\Warehouse\TagController as WarehouseTagController;
use Webkul\Admin\Http\Controllers\Settings\Warehouse\WarehouseController;
use Webkul\Admin\Http\Controllers\Settings\WebFormController;
use Webkul\Admin\Http\Controllers\Settings\WebhookController;
use Webkul\Admin\Http\Controllers\Settings\WorkflowController;
use App\Http\Controllers\Admin\Settings\ResourceController;
use App\Http\Controllers\Admin\Settings\ResourceTypeController;
use App\Http\Controllers\Admin\Settings\ShiftController;
use App\Http\Controllers\Admin\Settings\ProductTypeController;

/**
 * Settings routes.
 */
Route::prefix('partner-products')->group(function () {
    /**
     * Partner Products routes.
     */
    Route::controller(PartnerProductController::class)->group(function () {
        Route::get('', 'index')->name('admin.partner_products.index');
        Route::get('create', 'create')->name('admin.partner_products.create');
        Route::post('create', 'store')->name('admin.partner_products.store');
        Route::get('view/{id}', 'view')->name('admin.partner_products.view');
        Route::get('edit/{id}', 'edit')->name('admin.partner_products.edit');
        Route::put('edit/{id}', 'update')->name('admin.partner_products.update');
        Route::delete('', 'destroy')->name('admin.partner_products.delete');
        Route::delete('{id}', 'destroy')->name('admin.partner_products.delete');
        Route::get('search', 'search')->name('admin.partner_products.search');
        Route::get('template-products', 'getTemplateProducts')->name('admin.partner_products.template_products');
        Route::get('template-product/{id}', 'getTemplateProduct')->name('admin.partner_products.template_product');
        Route::get('template-products', 'getTemplateProducts')->name('admin.partner_products.template_products');
        Route::get('template-product/{id}', 'getTemplateProduct')->name('admin.partner_products.template_product');

        Route::controller(PartnerProductActivityController::class)->prefix('{id}/activities')->group(function () {
            Route::get('', 'index')->name('admin.partner_products.activities.index');
        });
    });
});
