<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\ImportRunDataGrid;
use App\Repositories\ImportRunRepository;
use Illuminate\Http\Request;

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

    protected function validateStore(Request $request): void
    {
        // Import runs are created programmatically, not via form
    }

    protected function validateUpdate(Request $request, int $id): void
    {
        // Import runs are not editable via UI
    }

    protected function getCreateSuccessMessage(): string
    {
        return 'Import run created successfully';
    }

    protected function getUpdateSuccessMessage(): string
    {
        return 'Import run updated successfully';
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
