<?php

namespace Webkul\Admin\Http\Controllers\Mail;

use App\Repositories\SalesLeadRepository;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
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
use Webkul\Lead\Repositories\LeadRepository;
use App\Models\SalesLead;
use Illuminate\Support\Facades\File;

class EmailController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected LeadRepository $leadRepository,
        protected SalesLeadRepository $salesRepository,
        protected EmailRepository $emailRepository,
        protected AttachmentRepository $attachmentRepository,
        protected FolderRepository $folderRepository,
        protected PersonRepository $personRepository
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): View|JsonResponse|RedirectResponse
    {
        if (! request('route')) {
            return redirect()->route('admin.mail.index', ['route' => 'inbox']);
        }

        if (! bouncer()->hasPermission('mail.'.request('route'))) {
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
                ->with(['emails', 'attachments', 'emails.attachments', 'lead', 'lead.tags', 'lead.source', 'lead.type', 'person', 'activity', 'salesLead'])
                ->findOrFail(request('id'));

            if (request('route') == 'draft') {
                Log::info('EmailController@view: Returning JSON for draft');
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
        ]);

        Event::dispatch('email.create.before');

        $email = $this->emailRepository->create(request()->all());

        $currentUserName = auth()->guard('user')->user()->name ?? 'Privatescan medewerker';
//        $currentUserNameemail['from'] = [$email['from'] => $currentUserName];
        logger()->info('Email created', [
            'email' => $email,
        ]);
        if (! request('is_draft')) {
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
                'data'    => new EmailResource($email),
                'message' => trans('admin::app.mail.create-success'),
            ]);
        }

        if (request('is_draft')) {
            session()->flash('success', trans('admin::app.mail.saved-to-draft'));

            return redirect()->route('admin.mail.index', ['route' => 'draft']);
        }

        session()->flash('success', trans('admin::app.mail.create-success'));

        return redirect()->route('admin.mail.index', ['route'   => 'sent']);
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

        Event::dispatch('email.update.after', $email);

        if (! is_null(request('is_draft')) && ! request('is_draft')) {
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
            return response()->json([
                'data'    => new EmailResource($email->refresh()),
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
            Event::dispatch('email.'.request('type').'.before', $id);

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
                Event::dispatch('email.'.$massDestroyRequest->input('type').'.before', $email->id);

                if ($massDestroyRequest->input('type') == 'trash') {
                    $trashFolder = Folder::where('name', 'trash')->first();
                    if ($trashFolder) {
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
     * Get list of available email templates.
     */
    public function getTemplates(): JsonResponse
    {
        $templatesPath = resource_path('views/adminc/email_templates');
        $templates = [];

        if (File::exists($templatesPath)) {
            $files = File::files($templatesPath);

            foreach ($files as $file) {
                $filename = $file->getFilename();
                if (str_ends_with($filename, '.blade.php')) {
                    $name = str_replace('.blade.php', '', $filename);
                    $templates[] = [
                        'name' => $name,
                        'label' => ucfirst(str_replace('_', ' ', $name)),
                    ];
                }
            }
        }

        return response()->json([
            'data' => $templates,
        ]);
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

        if(is_null($leadId) && is_null($personId) && is_null($salesLeadId)) {
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
            // Prepare variables for template (resolved server-side)
            $variables = $this->resolveTemplateVariables($leadId, $personId, $salesLeadId);

            $viewPath = 'adminc.email_templates.' . $templateName;

            // Check if view exists
            if (!view()->exists($viewPath)) {
                return response()->json([
                    'error' => 'Template not found',
                    'message' => "View {$viewPath} does not exist",
                ], 404);
            }

            $content = view($viewPath, $variables)->render();

            return response()->json([
                'data' => [
                    'content' => $content,
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
     * Resolve and build template variables from provided entity identifiers.
     * @throws Exception if all arguments are null
     */
    private function resolveTemplateVariables($leadId = null, $personId = null, $salesLeadId = null): array
    {
        // Lead and related person
        if ($leadId) {
            return $this->leadRepository->resolveEmailVariablesById($leadId);
        } else if ($personId) {
            return $this->personRepository->resolveEmailVariablesById($personId);
        }else if ($salesLeadId) {
            return $this->salesRepository->resolveEmailVariablesById($salesLeadId);
        }
        else {
            throw new Exception('No valid entity identifier provided for template variable resolution');
        }
    }
}

