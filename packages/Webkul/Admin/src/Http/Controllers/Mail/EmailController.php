<?php

namespace Webkul\Admin\Http\Controllers\Mail;

use App\Enums\EmailTemplateType;
use App\Services\Mail\EmailTemplateRenderingService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\Mail\EmailDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Requests\MassUpdateRequest;
use Webkul\Admin\Http\Resources\ActivityResource;
use Webkul\Admin\Http\Resources\EmailResource;
use Webkul\Email\Enums\EmailFolderEnum;
use Webkul\Email\InboundEmailProcessor\Contracts\InboundEmailProcessor;
use Webkul\Email\Models\Folder;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\Email\Repositories\EmailRepository;
use Webkul\Email\Repositories\FolderRepository;
use Webkul\EmailTemplate\Models\EmailTemplate;

class EmailController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected EmailRepository $emailRepository,
        protected AttachmentRepository $attachmentRepository,
        protected FolderRepository $folderRepository,
        readonly private EmailTemplateRenderingService $emailTemplateRenderingService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): View|JsonResponse|RedirectResponse
    {
        if (! request('route')) {
            return redirect()->route('admin.mail.index', ['route' => 'inbox']);
        }

        if (! bouncer()->hasPermission('mail')) {
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
                ->with(['emails', 'attachments', 'emails.attachments', 'tags', 'lead', 'lead.tags', 'lead.source', 'lead.type', 'person', 'salesLead', 'clinic', 'order'])
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
            'reply_to'   => 'required|array|min:1',
            'reply_to.*' => 'email',
            'reply'      => 'required',
            'subject'    => 'required',
            'order_id'   => 'nullable|integer|exists:orders,id',
        ]);

        Event::dispatch('email.create.before');

        // Get all request data including activity_id if provided
        $data = request()->all();

        // Centralized mail flow (store + send) via App service.
        $crmMailService = app(\App\Services\Mail\CrmMailService::class);
        $email = $crmMailService->createAndMaybeSend($data, (bool) request('is_draft'), EmailFolderEnum::SENT);

        Event::dispatch('email.create.after', $email);

        if (request()->ajax()) {
            return response()->json([
                'data'    => new EmailResource($email),
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
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update($id)
    {
        Event::dispatch('email.update.before', $id);

        $data = request()->all();

        if (! is_null(request('is_draft'))) {
            $folderName = request('is_draft') ? 'draft' : 'outbox';
            $folder = Folder::where('name', $folderName)->first();
            if ($folder) {
                $data['folder_id'] = $folder->id;
            }
        }

        $email = $this->emailRepository->update($data, request('id') ?? $id);

        $this->moveToProcessedIfLinked($email, $data);

        Event::dispatch('email.update.after', $email);

        if (! is_null(request('is_draft')) && ! request('is_draft')) {
            try {
                // Centralized send logic (folder behavior kept as-is: move to inbox).
                $crmMailService = app(\App\Services\Mail\CrmMailService::class);
                $crmMailService->sendEmail($email, EmailFolderEnum::INBOX);
            } catch (Exception $e) {
            }
        }

        if (! is_null(request('is_draft'))) {
            if (request('is_draft')) {
                session()->flash('success', trans('admin::app.mail.saved-to-draft'));

                return redirect()->route('admin.mail.index', ['route' => 'draft']);
            } else {
                session()->flash('success', trans('admin::app.mail.create-success'));

                return redirect()->route('admin.mail.index', ['route' => 'inbox']);
            }
        }

        if (request()->ajax()) {
            /** @var mixed $emailForResource */
            $emailForResource = $email;
            if ($email instanceof \Illuminate\Database\Eloquent\Model) {
                $emailForResource = $email->refresh();
            }

            return response()->json([
                'data'    => new EmailResource($emailForResource),
                'message' => trans('admin::app.mail.update-success'),
            ]);
        }

        session()->flash('success', trans('admin::app.mail.update-success'));

        return redirect()->back();
    }

    /**
     * Update the email status.
     */
    public function updateStatus(int $id): JsonResponse
    {
        $this->validate(request(), [
            'is_read' => 'required|boolean',
        ]);

        $email = $this->emailRepository->findOrFail($id);

        $this->emailRepository->update([
            'is_read' => request('is_read'),
        ], $id);

        return response()->json([
            'message' => trans('admin::app.mail.update-success'),
        ]);
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
     * @param  int  $id
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
            Log::error('EmailController@download: Error downloading attachment', [
                'attachment_id' => $id,
                'error' => $e->getMessage(),
            ]);
            session()->flash('error', trans('admin::app.mail.download-failed'));

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
            Event::dispatch('email.'.request('type').'.before', $id);

            $parentId = $email->parent_id;

            if (request('type') == 'trash') {
                $trashFolder = Folder::where('name', EmailFolderEnum::TRASH->getFolderName())->first();
                $alreadyInTrash = $trashFolder && $email->folder_id === $trashFolder->id;

                if ($alreadyInTrash) {
                    $this->emailRepository->delete($id);
                } elseif ($trashFolder) {
                    $this->emailRepository->update([
                        'folder_id' => $trashFolder->id,
                    ], $id);
                }
            } else {
                $this->emailRepository->delete($id);
            }

            Event::dispatch('email.'.request('type').'.after', $id);

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
     * Move email to "Verwerkt" folder when it is linked to an entity and currently in an inbox-type folder.
     */
    private function moveToProcessedIfLinked($email, array $data): void
    {
        $entityFields = ['lead_id', 'sales_lead_id', 'person_id', 'clinic_id', 'order_id'];
        $hasEntityLink = collect($entityFields)->contains(fn ($field) => ! empty($data[$field]));

        if ($hasEntityLink) {
            $this->emailRepository->moveToProcessedIfInbox($email->id);
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
                'data'    => [
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
                Event::dispatch('email.'.$massDestroyRequest->input('type').'.before', $email->id);

                if ($massDestroyRequest->input('type') == 'trash') {
                    $trashFolder = Folder::where('name', EmailFolderEnum::TRASH->getFolderName())->first();
                    $alreadyInTrash = $trashFolder && $email->folder_id === $trashFolder->id;

                    if ($alreadyInTrash) {
                        $this->emailRepository->delete($email->id);
                    } elseif ($trashFolder) {
                        $this->emailRepository->update(['folder_id' => $trashFolder->id], $email->id);
                    }
                } else {
                    $this->emailRepository->delete($email->id);
                }

                Event::dispatch('email.'.$massDestroyRequest->input('type').'.after', $email->id);
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
                    'id'          => $template->id,
                    'name'        => $template->name,
                    'code'        => $template->code ?? $template->name,
                    'label'       => $template->name,
                    'type'        => $template->type,
                    'language'    => $template->language,
                    'departments' => $template->departments ?? [],
                ];
            })->toArray();

            return response()->json([
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error('Error in email templates.get endpoint', [
                'error'          => $e->getMessage(),
                'trace'          => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error'   => __('messages.email.server_error'),

            ], 500);
        }
    }

    /**
     * Get list of available email templates.
     *
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
        $templateCode = request()->query('template');
        $leadId = request()->query('lead_id');
        $personId = request()->query('person_id');
        $salesLeadId = request()->query('sales_lead_id');

        if (! $templateCode) {
            return response()->json([
                'error' => 'email_template_identifier is required',
            ], 400);
        }

        if (is_null($leadId) && is_null($personId) && is_null($salesLeadId)) {
            return response()->json([
                'error' => __('messages.email.entity_required'),
            ], 400);
        }

        try {
            $template = EmailTemplate::byCode($templateCode)->first();

            if (! $template) {
                return response()->json([
                    'error'   => __('messages.email.template_not_found'),
                    'message' => "Template with code '{$templateCode}' does not exist in database",
                ], 404);
            }

            $entities = array_filter([
                'lead'       => $leadId,
                'person'     => $personId,
                'sales_lead' => $salesLeadId,
            ]);

            $variables = $this->emailTemplateRenderingService->resolveVariablesFromEntities($entities);
            $content = $this->emailTemplateRenderingService->renderTemplateToHTML($template, $variables);
            $subject = $this->emailTemplateRenderingService->interpolateTemplate($template->subject, $variables);

            return response()->json([
                'data' => [
                    'content' => $content,
                    'subject' => $subject,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Template rendering error: '.$e->getMessage(), [
                'template'  => $templateCode,
                'lead_id'   => $leadId ?? null,
                'person_id' => $personId ?? null,
                'exception' => $e,
            ]);

            return response()->json([
                'error'   => __('messages.email.template_render_error'),
                ], 500);
        }
    }

    /**
     * Get template content body.
     * Accepts entities array format: ['lead' => 123, 'person' => 456, 'sales_lead' => 789]
     */
    public function getTemplateContentBody(): JsonResponse
    {
        $request = request();
        $templateCode = $request->input('email_template_identifier');
        $entities = $request->input('entities', []);

        if (! $templateCode) {
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
            $template = EmailTemplate::byCode($templateCode)->first();

            if (! $template) {
                return response()->json([
                    'error'   => __('messages.email.template_not_found'),
                    'message' => "Template with code '{$templateCode}' does not exist in database",
                ], 404);
            }

            $variables = $this->emailTemplateRenderingService->resolveVariablesFromEntities($entities);
            $content = $this->emailTemplateRenderingService->renderTemplateToHTML($template, $variables);

            return response()->json([
                'data' => [
                    'content' => $content,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Template body rendering error: '.$e->getMessage(), [
                'template'  => $templateCode,
                'entities'  => $entities,
                'exception' => $e,
            ]);

            return response()->json([
                'error'   => __('messages.email.template_render_error'),

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
        $templateCode = $request->input('email_template_identifier');
        $entities = $request->input('entities', []);

        if (! $templateCode) {
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
            $template = EmailTemplate::byCode($templateCode)->first();

            if (! $template) {
                return response()->json([
                    'error'   => __('messages.email.template_not_found'),
                    'message' => "Template with code '{$templateCode}' does not exist in database",
                ], 404);
            }

            $variables = $this->emailTemplateRenderingService->resolveVariablesFromEntities($entities);
            $subject = $this->emailTemplateRenderingService->interpolateTemplate($template->subject, $variables);

            return response()->json([
                'data' => [
                    'subject' => $subject,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Template subject rendering error: '.$e->getMessage(), [
                'template'  => $templateCode,
                'entities'  => $entities,
                'exception' => $e,
            ]);

            return response()->json([
                'error'   => __('messages.email.template_render_error'),

            ], 500);
        }
    }

    /**
     * Transform the email data to activity resource.
     *
     * @param  array  $data
     * @return ActivityResource
     */
    public function transformToActivity($data)
    {
        return new ActivityResource((object) [
            'id'            => $data['id'],
            'parent_id'     => $data['parent_id'],
            'title'         => $data['subject'],
            'type'          => 'email',
            'is_done'       => 1,
            'is_read'       => $data['is_read'] ?? 0,
            'comment'       => $data['reply'],
            'schedule_from' => null,
            'schedule_to'   => null,
            'user'          => auth()->guard('user')->user(),
            'user_id'       => auth()->guard('user')->id(),
            'group'         => null,
            'participants'  => [],
            'location'      => null,
            'additional'    => json_encode([
                'folders' => $data['folders'],
                'from'    => $data['from'],
                'to'      => $data['reply_to'],
                'cc'      => $data['cc'],
                'bcc'     => $data['bcc'],
            ]),
            'files'         => array_map(function ($attachment) {
                return (object) $attachment;
            }, $data['attachments']),
            'created_at'    => $data['created_at'],
            'updated_at'    => $data['updated_at'],
        ]);
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

        if (! empty($departmentsArray)) {
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
}
