<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Contact\OrganizationController;
use Webkul\Admin\Http\Controllers\Contact\Persons\ActivityController;
use Webkul\Admin\Http\Controllers\Contact\Persons\DuplicateController;
use Webkul\Admin\Http\Controllers\Contact\Persons\PersonController;
use Webkul\Admin\Http\Controllers\Contact\Persons\TagController;

Route::prefix('contacts')->group(function () {
    /**
     * Persons routes.
     */
    Route::controller(PersonController::class)->prefix('persons')->group(function () {
        Route::get('', 'index')->name('admin.contacts.persons.index');

        Route::get('create', 'create')->name('admin.contacts.persons.create');

        Route::post('create', 'store')->name('admin.contacts.persons.store');

        Route::get('view/{id}', 'show')->name('admin.contacts.persons.view');

        Route::get('edit/{id}', 'edit')->name('admin.contacts.persons.edit');

        Route::put('edit/{id}', 'update')->name('admin.contacts.persons.update');

        Route::get('search', 'search')->name('admin.contacts.persons.search');

        Route::get('searchByLead/{lead}', 'searchByLead')->name('admin.contacts.persons.searchbylead');
        // Single-person variant, query params lead_id & person_id
        Route::get('searchByLead', 'searchByLeadSingle')->name('admin.contacts.persons.searchbylead_single');

        Route::get('sync-lead-to-person/{leadId}/{personId}', 'syncLeadToPerson')->name('admin.contacts.persons.sync_lead_to_person');

        Route::post('sync-lead-to-person/{leadId}/{personId}', 'syncLeadToPersonUpdate')->name('admin.contacts.persons.sync_lead_to_person_update');

        // Match score breakdown endpoint for UI tooltips
        Route::get('match-breakdown/{personId}/{leadId}', 'matchScoreBreakdown')->name('admin.contacts.persons.match_breakdown');

        Route::middleware(['throttle:100,60'])->delete('{id}', 'destroy')->name('admin.contacts.persons.delete');

        Route::post('mass-destroy', 'massDestroy')->name('admin.contacts.persons.mass_delete');

        /**
         * Tag routes.
         */
        Route::controller(TagController::class)->prefix('{id}/tags')->group(function () {
            Route::post('', 'attach')->name('admin.contacts.persons.tags.attach');

            Route::delete('', 'detach')->name('admin.contacts.persons.tags.detach');
        });

        /**
         * Activity routes.
         */
        Route::controller(ActivityController::class)->prefix('{id}/activities')->group(function () {
            Route::get('', 'index')->name('admin.contacts.persons.activities.index');
        });

        /**
         * Duplicate routes.
         */
        Route::controller(DuplicateController::class)->prefix('{id}/duplicates')->group(function () {
            Route::get('', 'index')->name('admin.contacts.persons.duplicates.index');

            Route::get('check', 'checkDuplicates')->name('admin.contacts.persons.duplicates.check');

            Route::get('get', 'getDuplicates')->name('admin.contacts.persons.duplicates.get');

            Route::post('merge', 'merge')->name('admin.contacts.persons.duplicates.merge');
        });
    });

    /**
     * Organization routes.
     */
    Route::controller(OrganizationController::class)->prefix('organizations')->group(function () {
        Route::get('', 'index')->name('admin.contacts.organizations.index');

        Route::get('search', 'search')->name('admin.contacts.organizations.search');

        Route::get('create', 'create')->name('admin.contacts.organizations.create');

        Route::post('create', 'store')->name('admin.contacts.organizations.store');

        Route::get('view/{id}', 'show')->name('admin.contacts.organizations.view');

        Route::get('edit/{id?}', 'edit')->name('admin.contacts.organizations.edit');

        Route::put('edit/{id}', 'update')->name('admin.contacts.organizations.update');

        Route::delete('{id}', 'destroy')->name('admin.contacts.organizations.delete');

        Route::put('mass-destroy', 'massDestroy')->name('admin.contacts.organizations.mass_delete');
    });
});
