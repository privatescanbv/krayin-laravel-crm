<?php

namespace Webkul\Admin\Http\Controllers\Mail;

use App\Models\Anamnesis;
use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Repositories\SalesLeadRepository;
use App\Services\Mail\EmailRenderingService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;
use Webkul\Admin\DataGrids\Mail\EmailDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Requests\MassUpdateRequest;
use Webkul\Admin\Http\Resources\EmailResource;
use Webkul\Email\InboundEmailProcessor\Contracts\InboundEmailProcessor;
use Webkul\Email\Mails\Email;
use Webkul\Email\Models\Folder;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\Email\Repositories\EmailRepository;
use Webkul\Email\Repositories\FolderRepository;
use Webkul\EmailTemplate\Models\EmailTemplate;
use Webkul\Lead\Repositories\LeadRepository;
use App\Models\SalesLead;
use App\Enums\EmailTemplateType;

class EmailController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected LeadRepository               $leadRepository,
        protected SalesLeadRepository          $salesRepository,
        protected EmailRepository              $emailRepository,
        protected AttachmentRepository         $attachmentRepository,
        protected FolderRepository             $folderRepository,
        protected PersonRepository             $personRepository,
        protected OrderRepository              $orderRepository,
        readonly private EmailRenderingService $emailRenderingService,
    )
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View|JsonResponse|RedirectResponse
    {
        if (!request('route')) {
            return redirect()->route('admin.mail.index', ['route' => 'inbox']);
        }

        if (!bouncer()->hasPermission('mail.' . request('route'))) {
            abort(401, 'This action is unauthorized');
        }

        switch (request('route')) {
            default:
                // Check if the route is a folder name
                $folder = Folder::where('name', request('route'))->first();
                if ($folder) {
                    if (request()->ajax()) {
                        return datagrid(EmailDataGrid::class)->process();
                    }

                    // Get emails for this specific folder
                    $emails = $folder->emails()->orderBy('created_at', 'desc')->get();
                    $hierarchicalFolders = $this->folderRepository->getHierarchicalFolders();

                    return view('admin::mail.index', compact('folder', 'hierarchicalFolders', 'emails'));
                }

                // Fallback to original behavior for backward compatibility
                if (request()->ajax()) {
                    return datagrid(EmailDataGrid::class)->process();
                }

                $hierarchicalFolders = $this->folderRepository->getHierarchicalFolders();
                return view('admin::mail.index', compact('hierarchicalFolders'));
        }
    }

    /**
     * Display a resource.
     *
     * @return \Illuminate\View\View
     */
    public function view()
    {
        try {
            $email = $this->emailRepository
                ->with(['emails', 'attachments', 'emails.attachments', 'tags', 'lead', 'lead.tags', 'lead.source', 'lead.type', 'person', 'activity', 'salesLead', 'clinic'])
                ->findOrFail(request('id'));

            if (request('route') == 'draft') {
                return response()->json([
                    'data' => new EmailResource($email),
                ]);
            }

            $hierarchicalFolders = $this->folderRepository->getHierarchicalFolders();

            return view('admin::mail.view', compact('email', 'hierarchicalFolders'));
        } catch (Exception $e) {
            Log::error('EmailController@view: Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store()
    {
        $this->validate(request(), [
            'reply_to' => 'required|array|min:1',
            'reply_to.*' => 'email',
            'reply' => 'required',
        ]);

        Event::dispatch('email.create.before');

        // Get all request data including activity_id if provided
        $data = request()->all();

        // Ensure activity_id is included if provided
        if (request()->has('activity_id')) {
            $data['activity_id'] = request()->input('activity_id');
        }

        $email = $this->emailRepository->create($data);

        if (!request('is_draft')) {
            try {
                Mail::send(new Email($email));

                // Get the 'sent' folder
                $sentFolder = Folder::where('name', 'sent')->first();
                if ($sentFolder) {
                    $this->emailRepository->update([
                        'folder_id' => $sentFolder->id,
                    ], $email->id);
                }
            } catch (Exception $e) {
            }
        }

        Event::dispatch('email.create.after', $email);

        if (request()->ajax()) {
            return response()->json([
                'data' => new EmailResource($email),
                'message' => trans('admin::app.mail.create-success'),
            ]);
        }

        if (request('is_draft')) {
            session()->flash('success', trans('admin::app.mail.saved-to-draft'));

            return redirect()->route('admin.mail.index', ['route' => 'draft']);
        }

        session()->flash('success', trans('admin::app.mail.create-success'));

        return redirect()->route('admin.mail.index', ['route' => 'sent']);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update($id)
    {
        Event::dispatch('email.update.before', $id);

        $data = request()->all();

        if (!is_null(request('is_draft'))) {
            $folderName = request('is_draft') ? 'draft' : 'outbox';
            $folder = Folder::where('name', $folderName)->first();
            if ($folder) {
                $data['folder_id'] = $folder->id;
            }
        }

        $email = $this->emailRepository->update($data, request('id') ?? $id);

        Event::dispatch('email.update.after', $email);

        if (!is_null(request('is_draft')) && !request('is_draft')) {
            try {
                Mail::send(new Email($email));

                // Get the 'inbox' folder
                $inboxFolder = Folder::where('name', 'inbox')->first();
                if ($inboxFolder) {
                    $this->emailRepository->update([
                        'folder_id' => $inboxFolder->id,
                    ], $email->id);
                }
            } catch (Exception $e) {
            }
        }

        if (!is_null(request('is_draft'))) {
            if (request('is_draft')) {
                session()->flash('success', trans('admin::app.mail.saved-to-draft'));

                return redirect()->route('admin.mail.index', ['route' => 'draft']);
            } else {
                session()->flash('success', trans('admin::app.mail.create-success'));

                return redirect()->route('admin.mail.index', ['route' => 'inbox']);
            }
        }

        if (request()->ajax()) {
            return response()->json([
                'data' => new EmailResource($email->refresh()),
                'message' => trans('admin::app.mail.update-success'),
            ]);
        }

        session()->flash('success', trans('admin::app.mail.update-success'));

        return redirect()->back();
    }

    /**
     * Run process inbound parse email.
     *
     * @return \Illuminate\Http\Response
     */
    public function inboundParse(InboundEmailProcessor $inboundEmailProcessor)
    {
        $inboundEmailProcessor->processMessage(request('email'));

        return response()->json([], 200);
    }

    /**
     * Download file from storage
     *
     * @param int $id
     * @return \Illuminate\View\View
     */
    public function download($id)
    {
        $attachment = $this->attachmentRepository->findOrFail($id);

        try {
            // Use the friendly name from the database for the download filename
            $downloadName = $attachment->name ?: basename($attachment->path);

            return Storage::download($attachment->path, $downloadName);
        } catch (Exception $e) {
            session()->flash('error', $e->getMessage());

            return redirect()->back();
        }
    }

    /**
     * Mass Update the specified resources.
     */
    public function massUpdate(MassUpdateRequest $massUpdateRequest): JsonResponse
    {
        $emails = $this->emailRepository->findWhereIn('id', $massUpdateRequest->input('indices'));

        try {
            foreach ($emails as $email) {
                Event::dispatch('email.update.before', $email->id);

                // Get the folder by name from the folders array
                $folderName = request('folders')[0] ?? 'inbox';
                $folder = Folder::where('name', $folderName)->first();
                if ($folder) {
                    $this->emailRepository->update([
                        'folder_id' => $folder->id,
                    ], $email->id);
                }

                Event::dispatch('email.update.after', $email->id);
            }

            return response()->json([
                'message' => trans('admin::app.mail.mass-update-success'),
            ]);
        } catch (Exception) {
            return response()->json([
                'message' => trans('admin::app.mail.mass-update-success'),
            ], 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse|RedirectResponse
    {
        $email = $this->emailRepository->findOrFail($id);

        try {
            Event::dispatch('email.' . request('type') . '.before', $id);

            $parentId = $email->parent_id;

            if (request('type') == 'trash') {
                $trashFolder = Folder::where('name', 'trash')->first();
                if ($trashFolder) {
                    $this->emailRepository->update([
                        'folder_id' => $trashFolder->id,
                    ], $id);
                }
            } else {
                $this->emailRepository->delete($id);
            }

            Event::dispatch('email.' . request('type') . '.after', $id);

            if (request()->ajax()) {
                return response()->json([
                    'message' => trans('admin::app.mail.delete-success'),
                ], 200);
            }

            session()->flash('success', trans('admin::app.mail.delete-success'));

            if ($parentId) {
                return redirect()->back();
            }

            return redirect()->route('admin.mail.index', ['route' => 'inbox']);
        } catch (Exception $exception) {
            if (request()->ajax()) {
                return response()->json([
                    'message' => trans('admin::app.mail.delete-failed'),
                ], 400);
            }

            session()->flash('error', trans('admin::app.mail.delete-failed'));

            return redirect()->back();
        }
    }

    /**
     * Move email to another folder.
     */
    public function move(int $id): JsonResponse
    {
        $this->validate(request(), [
            'folder_id' => 'required|integer|exists:folders,id',
        ]);

        $email = $this->emailRepository->findOrFail($id);

        try {
            Event::dispatch('email.move.before', $id);

            $folder = Folder::findOrFail(request('folder_id'));

            $this->emailRepository->update([
                'folder_id' => $folder->id,
            ], $id);

            Event::dispatch('email.move.after', $id);

            return response()->json([
                'message' => trans('admin::app.mail.move-success'),
                'data' => [
                    'folder_name' => $folder->name,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => trans('admin::app.mail.move-failed'),
            ], 400);
        }
    }

    /**
     * Mass Delete the specified resources.
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        $mails = $this->emailRepository->findWhereIn('id', $massDestroyRequest->input('indices'));

        try {
            foreach ($mails as $email) {
                Event::dispatch('email.' . $massDestroyRequest->input('type') . '.before', $email->id);

                if ($massDestroyRequest->input('type') == 'trash') {
                    $trashFolder = Folder::where('name', 'trash')->first();
                    if ($trashFolder) {
                        $this->emailRepository->update(['folder_id' => $trashFolder->id], $email->id);
                    }
                } else {
                    $this->emailRepository->delete($email->id);
                }

                Event::dispatch('email.' . $massDestroyRequest->input('type') . '.after', $email->id);
            }

            return response()->json([
                'message' => trans('admin::app.mail.delete-success'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => trans('admin::app.mail.delete-success'),
            ]);
        }
    }

    /**
     * Search entities (leads, sales_leads, persons) by email address.
     */
    // Removed: searchByEmail. Reuse existing search endpoints (leads/persons/sales-leads) from respective controllers.

    /**
     * Get list of available email templates with filtering support.
     *
     * Query Parameters:
     * - `entity_type` (string): Filter by entity type (lead, order, algemeen, gvl)
     *   - 'lead': Returns both 'lead' and 'algemeen' templates
     *   - 'order': Returns only 'order' templates
     *   - 'algemeen': Returns only 'algemeen' templates
     *   - 'gvl': Returns only 'gvl' templates
     * - `departments` (string|array): Filter by departments (comma-separated or array)
     *   - Templates with no departments are included (available to all)
     *   - Templates matching at least one department are included
     * - `type` (string|array): Direct filter on template type
     * - `language` (string|array): Filter by language (nl, de, en)
     * - `code` (string): Filter by template code
     * - `search` (string): Search in name and code fields
     *
     * @return JsonResponse
     */
    public function get(): JsonResponse
    {
        try {
            $query = EmailTemplate::query();

            // Apply entity_type filter (backward compatibility)
            $entityType = request()->query('entity_type');
            if ($entityType) {
                $this->applyEntityTypeFilter($query, $entityType);
            }

            // Apply direct type filter (more flexible)
            $typeFilter = request()->query('type');
            if ($typeFilter) {
                $types = is_array($typeFilter) ? $typeFilter : [$typeFilter];
                $query->whereIn('type', $types);
            }

            // Apply language filter
            $languageFilter = request()->query('language');
            if ($languageFilter) {
                $languages = is_array($languageFilter) ? $languageFilter : [$languageFilter];
                $query->whereIn('language', $languages);
            }

            // Apply code filter
            $codeFilter = request()->query('code');
            if ($codeFilter) {
                $query->where('code', $codeFilter);
            }

            // Apply search filter (name and code)
            $search = request()->query('search');
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            }

            // Apply departments filter
            $departmentsFilter = request()->query('departments');
            if ($departmentsFilter) {
                $this->applyDepartmentsFilter($query, $departmentsFilter);
            }

            // Get results
            $templates = $query->orderBy('name')->get();

            // Map to response format
            $data = $templates->map(function ($template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'code' => $template->code ?? $template->name,
                    'label' => $template->name,
                    'type' => $template->type,
                    'language' => $template->language,
                    'departments' => $template->departments ?? [],
                ];
            })->toArray();

            return response()->json([
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in email templates.get endpoint', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_params' => request()->all(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * Apply entity type filter to query.
     * This maintains backward compatibility with the old entity_type parameter.
     */
    private function applyEntityTypeFilter($query, string $entityType): void
    {
        switch ($entityType) {
            case EmailTemplateType::LEAD->value:
                // For leads, show 'lead' and 'algemeen' templates
                $query->whereIn('type', [EmailTemplateType::LEAD->value, EmailTemplateType::ALGEMEEN->value]);
                break;
            case EmailTemplateType::ORDER->value:
                // For orders, show only 'order' templates
                $query->where('type', EmailTemplateType::ORDER->value);
                break;
            case EmailTemplateType::ALGEMEEN->value:
                // For general, show only 'algemeen' templates
                $query->where('type', EmailTemplateType::ALGEMEEN->value);
                break;
            case EmailTemplateType::GVL->value:
                // For GVL, show only 'gvl' templates
                $query->where('type', EmailTemplateType::GVL->value);
                break;
        }
    }

    /**
     * Apply departments filter to query.
     * Templates with no departments are included (available to all).
     * Templates matching at least one department are included.
     */
    private function applyDepartmentsFilter($query, $departmentsFilter): void
    {
        $departmentsArray = [];
        if (is_string($departmentsFilter)) {
            $departmentsArray = array_filter(array_map('trim', explode(',', $departmentsFilter)));
        } elseif (is_array($departmentsFilter)) {
            $departmentsArray = array_filter(array_map('trim', $departmentsFilter));
        }

        if (!empty($departmentsArray)) {
            $query->where(function ($q) use ($departmentsArray) {
                // Templates with no departments (available to all)
                $q->whereNull('departments')
                    ->orWhereJsonLength('departments', 0);

                // Templates that contain at least one of the requested departments
                foreach ($departmentsArray as $dept) {
                    $q->orWhereJsonContains('departments', $dept);
                }
            });
        }
    }

    /**
     * Get list of available email templates.
     * @deprecated Use get() instead for more flexible filtering
     */
    public function getTemplates(): JsonResponse
    {
        return $this->get();
    }

    /**
     * Get rendered email template content.
     */
    public function getTemplateContent(): JsonResponse
    {
        $templateName = request()->query('template');
        $leadId = request()->query('lead_id');
        $personId = request()->query('person_id');
        $salesLeadId = request()->query('sales_lead_id');

        if (is_null($leadId) && is_null($personId) && is_null($salesLeadId)) {
            return response()->json([
                'error' => 'At least one of lead_id, person_id, or sales_lead_id is required',
            ], 400);
        }

        if (!$templateName) {
            return response()->json([
                'error' => 'Template name is required',
            ], 400);
        }

        try {
            // Search by code first, fallback to name for backward compatibility
            $template = EmailTemplate::where('code', $templateName)
                ->first();

            if (!$template) {
                return response()->json([
                    'error' => 'Template not found',
                    'message' => "Template with code '{$templateName}' does not exist in database",
                ], 404);
            }

            // Prepare variables for template (resolved server-side)
            $variables = $this->resolveTemplateVariables($leadId, $personId, $salesLeadId);

            // Interpolate template content with variables and wrap in layout
            $content = $this->renderTemplateToHTML($template, $variables);
            $subject = $this->interpolateTemplate($template->subject, $variables);

            return response()->json([
                'data' => [
                    'content' => $content,
                    'subject' => $subject,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Template rendering error: ' . $e->getMessage(), [
                'template' => $templateName ?? 'unknown',
                'lead_id' => $leadId ?? null,
                'person_id' => $personId ?? null,
                'exception' => $e,
            ]);

            return response()->json([
                'error' => 'Template not found or error rendering template',
                'message' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 404);
        }
    }

    /**
     * Get template content body.
     * Accepts entities array format: ['lead' => 123, 'person' => 456, 'sales_lead' => 789]
     */
    public function getTemplateContentBody(): JsonResponse
    {
        $request = request();
        $templateName = $request->input('email_template_identifier');
        $entities = $request->input('entities', []);

        if (!$templateName) {
            return response()->json([
                'error' => 'email_template_identifier is required',
            ], 400);
        }

        if (empty($entities)) {
            return response()->json([
                'error' => 'entities array is required',
            ], 400);
        }

        try {
            // Search by code first, fallback to name for backward compatibility
            $template = EmailTemplate::where('code', $templateName)
                ->orWhere('name', $templateName)
                ->first();

            if (!$template) {
                return response()->json([
                    'error' => 'Template not found',
                    'message' => "Template with code or name '{$templateName}' does not exist in database",
                ], 404);
            }

            $variables = $this->resolveTemplateVariablesFromEntities($entities);
            $content = $this->renderTemplateToHTML($template, $variables);

            return response()->json([
                'data' => [
                    'content' => $content,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Template body rendering error: ' . $e->getMessage(), [
                'template' => $templateName ?? 'unknown',
                'entities' => $entities,
                'exception' => $e,
            ]);

            return response()->json([
                'error' => 'Template not found or error rendering template',
                'message' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * Get template content subject.
     * Accepts entities array format: ['lead' => 123, 'person' => 456, 'sales_lead' => 789]
     */
    public function getTemplateContentSubject(): JsonResponse
    {
        $request = request();
        $templateName = $request->input('email_template_identifier');
        $entities = $request->input('entities', []);

        if (!$templateName) {
            return response()->json([
                'error' => 'email_template_identifier is required',
            ], 400);
        }

        if (empty($entities)) {
            return response()->json([
                'error' => 'entities array is required',
            ], 400);
        }

        try {
            // Search by code first, fallback to name for backward compatibility
            $template = EmailTemplate::where('code', $templateName)
                ->orWhere('name', $templateName)
                ->first();

            if (!$template) {
                return response()->json([
                    'error' => 'Template not found',
                    'message' => "Template with code or name '{$templateName}' does not exist in database",
                ], 404);
            }

            $variables = $this->resolveTemplateVariablesFromEntities($entities);
            $subject = $this->interpolateTemplate($template->subject, $variables);

            return response()->json([
                'data' => [
                    'subject' => $subject,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Template subject rendering error: ' . $e->getMessage(), [
                'template' => $templateName ?? 'unknown',
                'entities' => $entities,
                'exception' => $e,
            ]);

            return response()->json([
                'error' => 'Template not found or error rendering template',
                'message' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * Resolve template variables from entities array.
     * Entities format: ['lead' => 123, 'person' => 456, 'sales_lead' => 789]
     */
    public function resolveTemplateVariablesFromEntities(array $entities): array
    {
        $variables = [];
        $leadId = $entities['lead'] ?? null;
        $personId = $entities['person'] ?? null;
        $salesLeadId = $entities['sales_lead'] ?? null;
        $orderId = $entities['order'] ?? null;

        // Resolve order variables (highest priority for order templates)
        if ($orderId) {
            $orderVariables = $this->orderRepository->resolveEmailVariablesForOrder($orderId);

            if (!empty($orderVariables)) {
                $variables = array_merge($variables, $orderVariables);

                // Render order items table (needs order object)
                if (isset($variables['order'])) {
                    $variables['order_items_table'] = $this->renderOrderItemsTable($variables['order']);
                }

                // If order has sales lead, also resolve sales lead variables
                if (isset($variables['order']) && $variables['order']->salesLead) {
                    $salesLeadId = $variables['order']->salesLead->id;
                    if ($variables['order']->salesLead->lead) {
                        $leadId = $variables['order']->salesLead->lead->id;
                    }
                }
            }
        }

        // Resolve lead variables
        if ($leadId) {
            $leadVariables = $this->leadRepository->resolveEmailVariablesById($leadId);
            $variables = array_merge($variables, $leadVariables);

            // Load lead model for nested access
            $lead = $this->leadRepository->find($leadId);
            if ($lead) {
                $variables['lead'] = $lead;
            }
        }

        // Resolve person variables
        if ($personId) {
            $personVariables = $this->personRepository->resolveEmailVariablesById($personId);
            $variables = array_merge($variables, $personVariables);

            // Load person model for nested access
            $person = $this->personRepository->find($personId);
            if ($person) {
                $variables['person'] = $person;
            }
        }

        // Resolve sales lead variables
        if ($salesLeadId) {
            $salesVariables = $this->salesRepository->resolveEmailVariablesById($salesLeadId);
            $variables = array_merge($variables, $salesVariables);

            // Load sales lead with relations for nested access
            $salesLead = SalesLead::with(['lead', 'orders'])->find($salesLeadId);
            if ($salesLead) {
                $variables['sales_lead'] = $salesLead;
                if ($salesLead->lead) {
                    $variables['lead'] = $salesLead->lead;
                }
                // Only set order if not already set from direct order entity
                if (!isset($variables['order'])) {
                    $order = $salesLead->orders()->latest()->first();
                    if ($order) {
                        $variables['order'] = $order;
                    }
                }
            }
        }

        // Handle GVL form link - prioritize person with lead, fallback to latest anamnesis for person
        if ($personId) {
            $gvlFormLink = $this->resolveGvlFormLink($personId, $leadId);
            if ($gvlFormLink) {
                $variables['gvl_form_link'] = $gvlFormLink;
            }
        }

        if (empty($variables)) {
            throw new Exception('No valid entities provided for template variable resolution');
        }

        return $variables;
    }

    /**
     * Resolve and build template variables from provided entity identifiers.
     * @throws Exception if all arguments are null
     */
    private function resolveTemplateVariables($leadId = null, $personId = null, $salesLeadId = null): array
    {
        // Lead and related person
        if ($leadId) {
            $variables = $this->leadRepository->resolveEmailVariablesById($leadId);

            // If personId is also provided, try to get GVL link from anamnesis
            if ($personId) {
                $anamnesis = Anamnesis::where('lead_id', $leadId)
                    ->where('person_id', $personId)
                    ->first();

                if ($anamnesis && !empty($anamnesis->gvl_form_link)) {
                    $variables['gvl_form_link'] = $anamnesis->gvl_form_link;
                }
            }

            return $variables;
        } else if ($personId) {
            $variables = $this->personRepository->resolveEmailVariablesById($personId);

            // If leadId is also provided, try to get GVL link from anamnesis
            if ($leadId) {
                $anamnesis = Anamnesis::where('lead_id', $leadId)
                    ->where('person_id', $personId)
                    ->firstOrFail();

                if ($anamnesis && !empty($anamnesis->gvl_form_link)) {
                    $variables['gvl_form_link'] = $anamnesis->gvl_form_link;
                }
            }

            return $variables;
        } else if ($salesLeadId) {
            $variables = $this->salesRepository->resolveEmailVariablesById($salesLeadId);

            // Try to get order and lead from sales lead for order mail templates
            $salesLead = SalesLead::with(['lead', 'orders'])->find($salesLeadId);
            if ($salesLead) {
                if ($salesLead->lead) {
                    $variables['lead'] = $salesLead->lead;
                }
                // Get the most recent order if available
                $order = $salesLead->orders()->latest()->first();
                if ($order) {
                    $variables['order'] = $order;
                }
            }

            return $variables;
        } else {
            throw new Exception('No valid entity identifier provided for template variable resolution');
        }
    }

    public function renderTemplateToHTML(EmailTemplate $template, array $variables): string
    {
        // First interpolate variables in the template content
        $interpolatedContent = $this->interpolateTemplate($template->content, $variables);
        
        // Create a temporary template object with interpolated content for the Blade view
        $templateWithInterpolatedContent = clone $template;
        $templateWithInterpolatedContent->content = $interpolatedContent;
        
        // Render the Blade view with the interpolated content (not escaped)
        $htmlContent = \Illuminate\Support\Facades\View::make('adminc.emails.mail-template', [
            'template' => $templateWithInterpolatedContent,
        ])->render();
        
        // Apply CSS inlining
        return $this->emailRenderingService->rendInlineCss($htmlContent);
    }

    /**
     * Convert a value to string, handling enums properly.
     * Uses ValueNormalizer for general conversion, with special handling for enums.
     */
    private function convertValueToString($value): string
    {
        // Handle enum objects (PHP 8.1+)
        if (is_object($value)) {
            try {
                $reflection = new \ReflectionClass($value);
                if ($reflection->isEnum()) {
                    // Try label() method first (custom method like OrderStatus->label())
                    if (method_exists($value, 'label')) {
                        return $value->label();
                    }
                    // For backed enums, use the value property
                    if ($reflection->hasProperty('value')) {
                        return (string) $value->value;
                    }
                    // Fallback: try to get name
                    if (method_exists($value, 'name')) {
                        return $value->name;
                    }
                }
            } catch (\ReflectionException $e) {
                // Not an enum, continue to normal conversion
            }
        }

        // Use ValueNormalizer for general conversion
        return \App\Helpers\ValueNormalizer::toString($value);
    }

    /**
     * Interpolate template content with variables.
     * Supports both {{ variable }} and {% variable %} syntax.
     * Supports nested properties like {%lead.name%} or {{order.id}}.
     */
    protected function interpolateTemplate(string $template, array $variables): string
    {
        // Store reference to this for use in closure
        $self = $this;

        // Helper function to resolve nested property access
        $resolveValue = function ($key, $vars) use (&$resolveValue, $self) {
            // Check if key exists directly (highest priority)
            if (array_key_exists($key, $vars)) {
                return $self->convertValueToString($vars[$key]);
            }

            // Check for nested property (e.g., "lead.name" or "order.id")
            if (strpos($key, '.') !== false) {
                $parts = explode('.', $key, 2);
                $objectKey = $parts[0];
                $propertyKey = $parts[1];

                if (array_key_exists($objectKey, $vars)) {
                    $object = $vars[$objectKey];

                    if (is_object($object)) {
                        // For Eloquent models, use getAttribute which handles accessors
                        if (method_exists($object, 'getAttribute')) {
                            try {
                                $value = $object->getAttribute($propertyKey);
                                if ($value !== null) {
                                    return $self->convertValueToString($value);
                                }
                            } catch (Exception $e) {
                                // Continue to next method
                            }
                        }
                        // Try direct property access
                        if (property_exists($object, $propertyKey)) {
                            return $self->convertValueToString($object->$propertyKey);
                        }
                        // Try method call
                        if (method_exists($object, $propertyKey)) {
                            try {
                                return $self->convertValueToString($object->$propertyKey());
                            } catch (Exception $e) {
                                // Ignore
                            }
                        }
                        // Try magic getter
                        if (method_exists($object, '__get')) {
                            try {
                                return $self->convertValueToString($object->__get($propertyKey));
                            } catch (Exception $e) {
                                // Ignore
                            }
                        }
                    } elseif (is_array($object) && array_key_exists($propertyKey, $object)) {
                        return $self->convertValueToString($object[$propertyKey]);
                    }
                }
            }

            return null;
        };

        // Replace {{ variable }} syntax
        $template = preg_replace_callback('/\{\{\s*(.*?)\s*\}\}/', function ($matches) use ($variables, $resolveValue) {
            $key = trim($matches[1]);
            // Remove leading $ if present (for Blade-style variables like $lastname)
            $key = ltrim($key, '$');
            $value = $resolveValue($key, $variables);
            return $value !== null ? (string) $value : $matches[0];
        }, $template);

        // Replace {% variable %} syntax
        $template = preg_replace_callback('/\{\%\s*(.*?)\s*\%\}/', function ($matches) use ($variables, $resolveValue) {
            $key = trim($matches[1]);
            // Remove leading $ if present (for Blade-style variables like $lastname)
            $key = ltrim($key, '$');
            $value = $resolveValue($key, $variables);
            return $value !== null ? (string) $value : $matches[0];
        }, $template);

        return $template;
    }

    /**
     * Render order items table HTML using Blade template
     */
    private function renderOrderItemsTable(Order $order): string
    {
        return view('adminc.email_templates.order.order_items_table', [
            'order' => $order,
        ])->render();
    }

    /**
     * Resolve GVL form link for a person.
     * Prioritizes anamnesis with both lead_id and person_id, falls back to latest anamnesis for person.
     *
     * @param int $personId
     * @param int|null $leadId
     * @return string|null
     */
    private function resolveGvlFormLink(int $personId, ?int $leadId = null): ?string
    {
        $anamnesis = null;

        // If both lead and person are present, try to find specific anamnesis
        if ($leadId) {
            $anamnesis = Anamnesis::where('lead_id', $leadId)
                ->where('person_id', $personId)
                ->first();
        }

        // If not found, get the latest anamnesis for this person
        if (!$anamnesis) {
            $anamnesis = Anamnesis::where('person_id', $personId)
                ->whereNotNull('gvl_form_link')
                ->where('gvl_form_link', '!=', '')
                ->latest('updated_at')
                ->first();
        }

        if ($anamnesis && !empty($anamnesis->gvl_form_link)) {
            return $anamnesis->gvl_form_link;
        }

        return null;
    }
}

