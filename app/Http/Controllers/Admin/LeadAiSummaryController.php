<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveLeadAiFeedbackRequest;
use App\Jobs\GenerateLeadAiSummaryJob;
use App\Models\LeadAiFeedback;
use App\Models\LeadAiSummary;
use App\Models\Order;
use App\Services\Ai\AiPromptConfig;
use Illuminate\Http\JsonResponse;
use Webkul\Lead\Models\Lead;

class LeadAiSummaryController extends Controller
{
    public function show(int $leadId): JsonResponse
    {
        $this->requirePermission('leads.view');
        $lead = $this->findAccessibleLead($leadId);
        $summary = $lead->aiSummary;

        return response()->json([
            'data' => [
                'summary'  => $summary ? [
                    'id'                 => $summary->id,
                    'summary'            => $summary->summary,
                    'next_action_title'  => $summary->next_action_title,
                    'next_action_reason' => $summary->next_action_reason,
                    'priority'           => $summary->priority,
                    'highlights'         => $summary->highlights ?? [],
                    'attention_points'   => $this->attentionPointsData($summary, $lead),
                    'generated_at'       => $summary->generated_at?->toIso8601String(),
                    'status'             => $summary->status,
                ] : null,
                'feedback' => $lead->aiFeedback()
                    ->with('user')
                    ->where('is_active', true)
                    ->oldest('created_at')
                    ->get()
                    ->map(fn (LeadAiFeedback $feedback) => $this->feedbackData($feedback))
                    ->values(),
            ],
        ]);
    }

    public function generate(int $leadId): JsonResponse
    {
        $this->requirePermission('leads.edit');
        $lead = $this->findAccessibleLead($leadId);

        if (! config('services.llm.lead_summary.enabled', true)) {
            return response()->json([
                'message' => 'AI-samenvattingen zijn momenteel uitgeschakeld.',
            ], 503);
        }

        $summary = LeadAiSummary::query()->firstOrCreate(
            ['lead_id' => $lead->id],
            [
                'model'          => AiPromptConfig::model('lead_summary'),
                'prompt_version' => config('services.llm.lead_summary.prompt_version', 'v3'),
                'status'         => 'queued',
            ],
        );

        // A job for this lead is already queued, running, or waiting on a retry
        // (GenerateLeadAiSummaryJob is unique per lead); dispatching another one
        // would silently no-op, so tell the user instead of a false "started" message.
        if (! $summary->wasRecentlyCreated && in_array($summary->status, ['queued', 'processing', 'retrying'], true)) {
            return response()->json([
                'message' => 'Er loopt al een verversing voor deze lead.',
            ], 409);
        }

        $summary->update([
            'status'     => 'queued',
            'last_error' => null,
        ]);

        GenerateLeadAiSummaryJob::dispatch($lead->id, 'manual');

        return response()->json([
            'message' => 'De AI-samenvatting wordt opnieuw gegenereerd.',
        ], 202);
    }

    public function storeFeedback(SaveLeadAiFeedbackRequest $request, int $leadId): JsonResponse
    {
        $this->requirePermission('leads.edit');
        $lead = $this->findAccessibleLead($leadId);

        $feedback = $lead->aiFeedback()->create([
            'user_id'   => $request->user('user')->id,
            'feedback'  => trim($request->validated('feedback')),
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'AI-correctie toegevoegd.',
            'data'    => $this->feedbackData($feedback->load('user')),
        ], 201);
    }

    public function updateFeedback(
        SaveLeadAiFeedbackRequest $request,
        int $leadId,
        LeadAiFeedback $feedback,
    ): JsonResponse {
        $this->requirePermission('leads.edit');
        $this->findAccessibleLead($leadId);
        abort_unless($feedback->lead_id === $leadId && $feedback->is_active, 404);

        $feedback->update([
            'feedback'                   => trim($request->validated('feedback')),
            'included_in_generation_at'  => null,
        ]);

        return response()->json([
            'message' => 'AI-correctie bijgewerkt.',
            'data'    => $this->feedbackData($feedback->load('user')),
        ]);
    }

    public function destroyFeedback(int $leadId, LeadAiFeedback $feedback): JsonResponse
    {
        $this->requirePermission('leads.edit');
        $this->findAccessibleLead($leadId);
        abort_unless($feedback->lead_id === $leadId && $feedback->is_active, 404);

        $feedback->update(['is_active' => false]);
        $feedback->delete();

        return response()->json([
            'message' => 'AI-correctie verwijderd.',
        ]);
    }

    private function findAccessibleLead(int $leadId): Lead
    {
        $lead = Lead::findOrFail($leadId);
        $authorizedUserIds = bouncer()->getAuthorizedUserIds();

        if ($authorizedUserIds && ! in_array($lead->user_id, $authorizedUserIds)) {
            abort(403);
        }

        return $lead;
    }

    private function requirePermission(string $permission): void
    {
        abort_unless(bouncer()->hasPermission($permission), 403);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function attentionPointsData(LeadAiSummary $summary, Lead $lead): array
    {
        return collect($summary->attention_points ?? [])
            ->filter(fn (mixed $point) => is_array($point)
                && is_string($point['text'] ?? null)
                && is_array($point['source'] ?? null)
                && is_string($point['source']['date'] ?? null))
            ->map(function (array $point) use ($lead) {
                $source = $point['source'];
                $source['url'] = $this->sourceUrl($source, $lead);

                return [
                    'text'   => $point['text'],
                    'source' => $source,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function sourceUrl(array $source, Lead $lead): ?string
    {
        if (
            ($source['type'] ?? null) !== 'order'
            || ! is_numeric($source['entity_id'] ?? null)
            || ! bouncer()->hasPermission('orders.view')
        ) {
            return null;
        }

        $orderId = (int) $source['entity_id'];
        $belongsToAccessibleOwner = Order::query()
            ->whereKey($orderId)
            ->whereHas('salesLead.lead', function ($query) use ($lead) {
                $query->whereKey($lead->id);

                if ($lead->user_id) {
                    $query->orWhere('user_id', $lead->user_id);
                }
            })
            ->exists();

        return $belongsToAccessibleOwner
            ? route('admin.orders.view', $orderId)
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function feedbackData(LeadAiFeedback $feedback): array
    {
        return [
            'id'         => $feedback->id,
            'feedback'   => $feedback->feedback,
            'author'     => $feedback->user?->name,
            'created_at' => $feedback->created_at?->toIso8601String(),
            'updated_at' => $feedback->updated_at?->toIso8601String(),
        ];
    }
}
