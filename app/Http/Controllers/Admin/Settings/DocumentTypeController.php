<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\DocumentTypeDataGrid;
use App\Models\DocumentType;
use App\Repositories\DocumentTypeRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DocumentTypeController extends SimpleEntityController
{
    public function __construct(protected DocumentTypeRepository $documentTypeRepository)
    {
        parent::__construct($documentTypeRepository);

        $this->entityName = 'document_type';
        $this->datagridClass = DocumentTypeDataGrid::class;
        $this->indexView = 'adminc.document_types.index';
        $this->createView = 'adminc.document_types.create';
        $this->editView = 'adminc.document_types.edit';
        $this->indexRoute = 'admin.settings.document_types.index';
        $this->permissionPrefix = 'settings.document_types';
    }

    /**
     * Types with documents attached, or the system types, cannot be deleted.
     */
    public function destroy(Request $request, ?int $id = null): RedirectResponse|JsonResponse
    {
        $id ??= (int) ($request['indices'][0] ?? 0) ?: null;

        $documentType = $id ? DocumentType::find($id) : null;

        if ($documentType && ($documentType->isSystemType() || $documentType->isInUse())) {
            $message = trans('admin::app.settings.document_types.index.in-use');

            return $request->ajax() || $request->wantsJson()
                ? response()->json(['message' => $message], 400)
                : redirect()->route($this->indexRoute)->with('error', $message);
        }

        return parent::destroy($request, $id);
    }

    protected function validateStore(Request $request): void
    {
        $request->validate([
            'name'                    => ['required', 'string', 'max:100', 'unique:document_types,name'],
            'description'             => ['nullable', 'string'],
            'show_in_clinic_portal'   => ['boolean'],
            'show_in_patient_portal'  => ['boolean'],
            'sort_order'              => ['nullable', 'integer'],
        ]);
    }

    protected function validateUpdate(Request $request, int $id): void
    {
        $request->validate([
            'name'                    => ['required', 'string', 'max:100', 'unique:document_types,name,'.$id],
            'description'             => ['nullable', 'string'],
            'show_in_clinic_portal'   => ['boolean'],
            'show_in_patient_portal'  => ['boolean'],
            'sort_order'              => ['nullable', 'integer'],
        ]);
    }

    /**
     * `key` is never accepted from the request: new types always get key = null,
     * and system types keep the key they were created with.
     */
    protected function transformPayload(array $payload, ?int $id = null): array
    {
        $data = [
            'name'                    => $payload['name'],
            'description'             => $payload['description'] ?? null,
            'show_in_clinic_portal'   => (bool) ($payload['show_in_clinic_portal'] ?? false),
            'show_in_patient_portal'  => (bool) ($payload['show_in_patient_portal'] ?? false),
            'sort_order'              => isset($payload['sort_order']) && $payload['sort_order'] !== ''
                ? (int) $payload['sort_order']
                : 0,
        ];

        return $data;
    }

    protected function getCreateSuccessMessage(): string
    {
        return trans('admin::app.settings.document_types.index.create-success');
    }

    protected function getUpdateSuccessMessage(): string
    {
        return trans('admin::app.settings.document_types.index.update-success');
    }

    protected function getDestroySuccessMessage(): string
    {
        return trans('admin::app.settings.document_types.index.destroy-success');
    }

    protected function getDeleteFailedMessage(): string
    {
        return trans('admin::app.settings.document_types.index.delete-failed');
    }
}
