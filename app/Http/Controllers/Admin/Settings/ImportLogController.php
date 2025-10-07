<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\ImportLogDataGrid;
use App\Repositories\ImportLogRepository;

class ImportLogController extends SimpleEntityController
{
    public function __construct(protected ImportLogRepository $importLogRepository)
    {
        parent::__construct($importLogRepository);

        $this->entityName = 'import_log';
        $this->datagridClass = ImportLogDataGrid::class;
        $this->indexView = 'admin::settings.import-logs.index';
        $this->indexRoute = 'admin.settings.import-logs.index';
        $this->permissionPrefix = 'settings.import-logs';
    }

    public function view(int $id)
    {
        $importLog = $this->importLogRepository->with(['importRun', 'creator', 'updater'])->findOrFail($id);

        return view('admin::settings.import-logs.view', ['importLog' => $importLog]);
    }

    protected function getDestroySuccessMessage(): string
    {
        return 'Import log deleted successfully';
    }

    protected function getDeleteFailedMessage(): string
    {
        return 'Failed to delete import log';
    }
}