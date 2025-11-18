<?php

namespace Webkul\Admin\Http\Controllers\Settings;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\Settings\EmailTemplateDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Automation\Helpers\Entity;
use Webkul\EmailTemplate\Repositories\EmailTemplateRepository;
use App\Enums\EmailTemplateType;
use App\Enums\EmailTemplateLanguage;
use App\Models\Department;

class EmailTemplateController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected EmailTemplateRepository $emailTemplateRepository,
        protected Entity $workflowEntityHelper
    ) {}

    /**
     * Display a listing of the email template.
     */
    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(EmailTemplateDataGrid::class)->process();
        }

        return view('admin::settings.email-templates.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $placeholders = $this->workflowEntityHelper->getEmailTemplatePlaceholders();
        $templateTypes = EmailTemplateType::allWithLabels();
        $templateLanguages = EmailTemplateLanguage::allWithLabels();

        return view('admin::settings.email-templates.create', [
            'placeholders' => $placeholders,
            'templateTypes' => $templateTypes,
            'templateLanguages' => $templateLanguages,
        ]);
    }

    /**
     * Store a newly created email templates in storage.
     */
    public function store(): RedirectResponse
    {
        $this->validate(request(), [
            'name'        => 'required',
            'code'        => 'required|string|unique:email_templates,code',
            'type'        => 'required|in:'.implode(',', EmailTemplateType::allValues()),
            'language'    => 'required|in:'.implode(',', EmailTemplateLanguage::allValues()),
            'departments' => 'required|array|min:1',
            'departments.*' => 'required|exists:departments,id',
            'subject'     => 'required',
            'content'     => 'required',
        ]);

        Event::dispatch('settings.email_templates.create.before');

        $data = request()->all();
        $data['departments'] = $this->convertDepartmentIdsToNames($data['departments'] ?? []);

        $emailTemplate = $this->emailTemplateRepository->create($data);

        Event::dispatch('settings.email_templates.create.after', $emailTemplate);

        session()->flash('success', trans('admin::app.settings.email-template.index.create-success'));

        return redirect()->route('admin.settings.email_templates.index');
    }

    /**
     * Show the form for editing the specified email template.
     */
    public function edit(int $id): View
    {
        $emailTemplate = $this->emailTemplateRepository->findOrFail($id);

        $placeholders = $this->workflowEntityHelper->getEmailTemplatePlaceholders();
        $templateTypes = EmailTemplateType::allWithLabels();
        $templateLanguages = EmailTemplateLanguage::allWithLabels();
        
        // Get selected departments as Department models for entity selector
        $selectedDepartments = $this->getSelectedDepartmentsForEntitySelector($emailTemplate->departments ?? []);

        return view('admin::settings.email-templates.edit', [
            'emailTemplate' => $emailTemplate,
            'placeholders' => $placeholders,
            'templateTypes' => $templateTypes,
            'templateLanguages' => $templateLanguages,
            'selectedDepartments' => $selectedDepartments,
        ]);
    }

    /**
     * Update the specified email template in storage.
     */
    public function update(int $id): RedirectResponse
    {
        $this->validate(request(), [
            'name'        => 'required',
            'code'        => 'required|string|unique:email_templates,code,'.$id,
            'type'        => 'required|in:'.implode(',', EmailTemplateType::allValues()),
            'language'    => 'required|in:'.implode(',', EmailTemplateLanguage::allValues()),
            'departments' => 'required|array|min:1',
            'departments.*' => 'required|exists:departments,id',
            'subject'     => 'required',
            'content'     => 'required',
        ]);

        Event::dispatch('settings.email_templates.update.before', $id);

        $data = request()->all();
        $data['departments'] = $this->convertDepartmentIdsToNames($data['departments'] ?? []);

        $emailTemplate = $this->emailTemplateRepository->update($data, $id);

        Event::dispatch('settings.email_templates.update.after', $emailTemplate);

        session()->flash('success', trans('admin::app.settings.email-template.index.update-success'));

        return redirect()->route('admin.settings.email_templates.index');
    }

    /**
     * Remove the specified email template from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $emailTemplate = $this->emailTemplateRepository->findOrFail($id);

        try {
            Event::dispatch('settings.email_templates.delete.before', $id);

            $emailTemplate->delete($id);

            Event::dispatch('settings.email_templates.delete.after', $id);

            return response()->json([
                'message' => trans('admin::app.settings.email-template.index.delete-success'),
            ], 200);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => trans('admin::app.settings.email-template.index.delete-failed'),
            ], 400);
        }
    }

    /**
     * Convert department IDs to department names.
     */
    private function convertDepartmentIdsToNames($departmentIds): array
    {
        // Normalize to array
        if (!is_array($departmentIds)) {
            $departmentIds = $departmentIds ? [$departmentIds] : [];
        }
        
        // Filter empty values and convert to integers
        $departmentIds = array_values(array_filter(array_map('intval', $departmentIds)));
        
        if (empty($departmentIds)) {
            return [];
        }
        
        // Convert IDs to names
        return Department::whereIn('id', $departmentIds)->pluck('name')->toArray();
    }

    /**
     * Get selected departments formatted for entity selector.
     */
    private function getSelectedDepartmentsForEntitySelector(array $departmentNames): array
    {
        if (empty($departmentNames)) {
            return [];
        }
        
        return Department::whereIn('name', $departmentNames)
            ->get()
            ->map(fn ($dept) => [
                'id' => $dept->id,
                'name' => $dept->name,
            ])
            ->toArray();
    }
}
