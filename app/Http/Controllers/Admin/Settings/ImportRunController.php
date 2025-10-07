<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\ImportRunDataGrid;
use App\Repositories\ImportRunRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ImportRunController extends SimpleEntityController
{
    public function __construct(protected ImportRunRepository $importRunRepository)
    {
        parent::__construct($importRunRepository);

        $this->entityName = 'import_run';
        $this->datagridClass = ImportRunDataGrid::class;
        $this->indexView = 'admin::settings.import-runs.index';
        $this->indexRoute = 'admin.settings.import-runs.index';
        $this->permissionPrefix = 'settings.import-runs';
    }

    public function view(int $id)
    {
        $importRun = $this->importRunRepository->with(['importLogs', 'creator', 'updater'])->findOrFail($id);

        return view('admin::settings.import-runs.view', ['importRun' => $importRun]);
    }

    protected function getDestroySuccessMessage(): string
    {
        return 'Import run deleted successfully';
    }

    protected function getDeleteFailedMessage(): string
    {
        return 'Failed to delete import run';
    }
}