<?php

use App\Services\Metabase\MetabaseDashboardRegistry;
use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Dashboard\MetabaseDashboardController;

$registry = app(MetabaseDashboardRegistry::class);

foreach ($registry->pages() as $page) {
    if ($page['route'] === 'admin.dashboard.index') {
        continue;
    }

    Route::get($page['path'], [MetabaseDashboardController::class, 'show'])
        ->defaults('key', $page['key'])
        ->name($page['route']);
}

Route::get('dashboards/{slug}', [MetabaseDashboardController::class, 'show'])
    ->name('admin.metabase-dashboards.show');
