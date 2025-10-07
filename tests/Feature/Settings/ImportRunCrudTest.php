<?php

namespace Tests\Feature;

use App\Models\ImportRun;
use Webkul\Installer\Http\Middleware\CanInstall;

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
    test()->withoutMiddleware(CanInstall::class);

    $user = makeUser();
    $this->actingAs($user, 'user');
});

test('import runs index returns datagrid json', function () {
    $run1 = ImportRun::factory()->create();
    $run2 = ImportRun::factory()->create();

    $response = $this->getJson(route('admin.settings.import-runs.index'));
    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($run1->id, $run2->id);
});

test('can view import run', function () {
    $run = ImportRun::factory()->create([
        'import_type'       => 'leads',
        'status'            => 'completed',
        'records_processed' => 100,
        'records_imported'  => 90,
        'records_skipped'   => 5,
        'records_errored'   => 5,
    ]);

    $response = $this->getJson(route('admin.settings.import-runs.view', ['id' => $run->id]));
    $response->assertOk();
});

test('can delete import run', function () {
    $run = ImportRun::factory()->create();

    $response = $this->deleteJson(route('admin.settings.import-runs.delete', ['id' => $run->id]));
    $response->assertOk();

    $this->assertDatabaseMissing('import_runs', [
        'id' => $run->id,
    ]);
});
