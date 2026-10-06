<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Models\AssessmentOutcome;
use App\Repositories\AssessmentOutcomeRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AssessmentOutcomeController extends SimpleEntityController
{
    public function __construct(protected AssessmentOutcomeRepository $assessmentOutcomeRepository)
    {
        parent::__construct($assessmentOutcomeRepository);

        $this->entityName = 'assessment_outcome';
        $this->indexView = 'adminc.assessment_outcomes.index';
        $this->createView = 'adminc.assessment_outcomes.create';
        $this->editView = 'adminc.assessment_outcomes.edit';
        $this->indexRoute = 'admin.settings.assessment_outcomes.index';
        $this->permissionPrefix = 'settings.lead.assessment_outcomes';
    }

    /**
     * Drag-and-drop list instead of a datagrid: the order is what users manage here.
     */
    public function index(Request $request): View|JsonResponse
    {
        return view($this->indexView, ['outcomes' => AssessmentOutcome::ordered()->get()]);
    }

    /**
     * Persist the dragged order: position in `ids` becomes sort_order.
     */
    public function reorder(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer|exists:assessment_outcomes,id',
        ])['ids'];

        DB::transaction(function () use ($ids) {
            foreach (array_values($ids) as $position => $id) {
                AssessmentOutcome::whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });

        return response()->json(['message' => trans('admin::app.settings.assessment_outcomes.index.reorder-success')]);
    }

    /**
     * Outcomes still referenced by a sales lead cannot be deleted: the stored code would dangle.
     */
    public function destroy(Request $request, ?int $id = null): RedirectResponse|JsonResponse
    {
        $id ??= (int) ($request['indices'][0] ?? 0) ?: null;

        if ($id && AssessmentOutcome::find($id)?->isInUse()) {
            $message = trans('admin::app.settings.assessment_outcomes.index.in-use');

            return $request->ajax() || $request->wantsJson()
                ? response()->json(['message' => $message], 400)
                : redirect()->route($this->indexRoute)->with('error', $message);
        }

        return parent::destroy($request, $id);
    }

    protected function validateStore(Request $request): void
    {
        $request->merge(['code' => Str::slug((string) $request->input('label'), '_')]);

        $request->validate([
            'label'             => 'required|string|max:100|unique:assessment_outcomes,label',
            'code'              => 'required|max:50|unique:assessment_outcomes,code',
            'is_surgery_advice' => 'boolean',
        ]);
    }

    protected function validateUpdate(Request $request, int $id): void
    {
        $request->validate([
            'label'             => 'required|string|max:100|unique:assessment_outcomes,label,'.$id,
            'is_surgery_advice' => 'boolean',
        ]);
    }

    protected function transformPayload(array $payload, ?int $id = null): array
    {
        $data = [
            'label'             => $payload['label'],
            'is_surgery_advice' => (bool) ($payload['is_surgery_advice'] ?? false),
        ];

        // Code is immutable: it is stored on sales leads and used by analytics. New outcomes go last.
        if ($id === null) {
            $data['code'] = $payload['code'];
            $data['sort_order'] = (int) AssessmentOutcome::max('sort_order') + 1;
        }

        return $data;
    }

    protected function getCreateSuccessMessage(): string
    {
        return trans('admin::app.settings.assessment_outcomes.index.create-success');
    }

    protected function getUpdateSuccessMessage(): string
    {
        return trans('admin::app.settings.assessment_outcomes.index.update-success');
    }

    protected function getDestroySuccessMessage(): string
    {
        return trans('admin::app.settings.assessment_outcomes.index.destroy-success');
    }

    protected function getDeleteFailedMessage(): string
    {
        return trans('admin::app.settings.assessment_outcomes.index.delete-failed');
    }
}
