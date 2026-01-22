<?php

namespace App\Http\Controllers\Admin\Settings;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\Settings\Marketing\CampaignDatagrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\EmailTemplate\Repositories\EmailTemplateRepository;
use Webkul\Marketing\Repositories\CampaignRepository;
use Webkul\Marketing\Repositories\EventRepository;

class MarketingCampaignController extends Controller
{
    public function __construct(
        protected CampaignRepository $campaignRepository,
        protected EventRepository $eventRepository,
        protected EmailTemplateRepository $emailTemplateRepository,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson() || $request->isXmlHttpRequest()) {
            return datagrid(CampaignDatagrid::class)->process();
        }

        return view('adminc.marketing_campaigns.index');
    }

    public function create(): View
    {
        return view('adminc.marketing_campaigns.create', [
            'events'         => $this->eventRepository->get(['id', 'name']),
            'emailTemplates' => $this->emailTemplateRepository->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validatedData = $request->validate([
            'name'                  => 'required|string|max:255',
            'subject'               => 'required|string|max:255',
            'marketing_template_id' => 'nullable|exists:email_templates,id',
            'marketing_event_id'    => 'nullable|exists:marketing_events,id',
            'status'                => 'sometimes|boolean',
        ]);

        // HTML <select> posts empty string for "no selection"; normalize to NULL for FK columns.
        foreach (['marketing_template_id', 'marketing_event_id'] as $key) {
            if ($request->input($key) === '') {
                $validatedData[$key] = null;
            }
        }

        $validatedData['type'] ??= 'email';
        $validatedData['mail_to'] ??= 'persons';
        $validatedData['status'] = (int) ($validatedData['status'] ?? 0);

        Event::dispatch('settings.marketing.campaigns.create.before');

        $marketingCampaign = $this->campaignRepository->create($validatedData);

        Event::dispatch('settings.marketing.campaigns.create.after', $marketingCampaign);

        if ($request->ajax() || $request->wantsJson() || $request->isXmlHttpRequest()) {
            return response()->json([
                'data'    => $marketingCampaign,
                'message' => trans('admin::app.settings.marketing.campaigns.index.create-success'),
            ], 200);
        }

        return redirect()
            ->route('admin.settings.marketing.campaigns.index')
            ->with('success', trans('admin::app.settings.marketing.campaigns.index.create-success'));
    }

    public function edit(int $id): View
    {
        $campaign = $this->campaignRepository->findOrFail($id);

        return view('adminc.marketing_campaigns.edit', [
            'campaign'       => $campaign,
            'events'         => $this->eventRepository->get(['id', 'name']),
            'emailTemplates' => $this->emailTemplateRepository->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $validatedData = $request->validate([
            'name'                  => 'required|string|max:255',
            'subject'               => 'required|string|max:255',
            'marketing_template_id' => 'nullable|exists:email_templates,id',
            'marketing_event_id'    => 'nullable|exists:marketing_events,id',
            'status'                => 'sometimes|boolean',
        ]);

        // HTML <select> posts empty string for "no selection"; normalize to NULL for FK columns.
        foreach (['marketing_template_id', 'marketing_event_id'] as $key) {
            if ($request->input($key) === '') {
                $validatedData[$key] = null;
            }
        }

        $validatedData['type'] ??= 'email';
        $validatedData['mail_to'] ??= 'persons';
        $validatedData['status'] = (int) ($validatedData['status'] ?? 0);

        Event::dispatch('settings.marketing.campaigns.update.before', $id);

        $marketingCampaign = $this->campaignRepository->update($validatedData, $id);

        Event::dispatch('settings.marketing.campaigns.update.after', $marketingCampaign);

        if ($request->ajax() || $request->wantsJson() || $request->isXmlHttpRequest()) {
            return response()->json([
                'data'    => $marketingCampaign,
                'message' => trans('admin::app.settings.marketing.campaigns.index.update-success'),
            ], 200);
        }

        return redirect()
            ->route('admin.settings.marketing.campaigns.index')
            ->with('success', trans('admin::app.settings.marketing.campaigns.index.update-success'));
    }

    public function destroy(Request $request, int $id): RedirectResponse|JsonResponse
    {
        Event::dispatch('settings.marketing.campaigns.delete.before', $id);

        $this->campaignRepository->delete($id);

        Event::dispatch('settings.marketing.campaigns.delete.after', $id);

        if ($request->ajax() || $request->wantsJson() || $request->isXmlHttpRequest()) {
            return response()->json([
                'message' => trans('admin::app.settings.marketing.campaigns.index.delete-success'),
            ], 200);
        }

        return redirect()
            ->route('admin.settings.marketing.campaigns.index')
            ->with('success', trans('admin::app.settings.marketing.campaigns.index.delete-success'));
    }

    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        $campaigns = $this->campaignRepository->findWhereIn('id', $massDestroyRequest->input('indices'));

        foreach ($campaigns as $campaign) {
            Event::dispatch('settings.marketing.campaigns.delete.before', $campaign);

            $this->campaignRepository->delete($campaign->id);

            Event::dispatch('settings.marketing.campaigns.delete.after', $campaign);
        }

        return response()->json([
            'message' => trans('admin::app.settings.marketing.campaigns.index.mass-delete-success'),
        ]);
    }
}
