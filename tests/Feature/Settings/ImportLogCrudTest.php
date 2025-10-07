<?php

namespace Tests\Feature;

use App\Models\ImportLog;
use App\Models\ImportRun;
use Webkul\Installer\Http\Middleware\CanInstall;

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123', 'another-valid-key']]);
    test()->withoutMiddleware(CanInstall::class);

    $user = makeUser();
    $this->actingAs($user, 'user');
});

test('import logs index returns datagrid json', function () {
    $run = ImportRun::factory()->create();
    $log1 = ImportLog::factory()->create(['import_run_id' => $run->id]);
    $log2 = ImportLog::factory()->create(['import_run_id' => $run->id]);

    $response = $this->getJson(route('admin.settings.import-logs.index'));
    $response->assertOk();

    $ids = getDatagridIds($response);
    expect($ids)->toContain($log1->id, $log2->id);
});

test('can view import log', function () {
    $run = ImportRun::factory()->create();
    $log = ImportLog::factory()->create([
        'import_run_id' => $run->id,
        'level'         => 'error',
        'message'       => 'Test error message',
        'record_id'     => 'test-123',
    ]);

    $response = $this->getJson(route('admin.settings.import-logs.view', ['id' => $log->id]));
    $response->assertOk();
});

test('can delete import log', function () {
    $run = ImportRun::factory()->create();
    $log = ImportLog::factory()->create(['import_run_id' => $run->id]);

    $response = $this->deleteJson(route('admin.settings.import-logs.delete', ['id' => $log->id]));
    $response->assertOk();

    $this->assertDatabaseMissing('import_logs', [
        'id' => $log->id,
    ]);
});