<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EntityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveAiFeedbackRequest;
use App\Jobs\GenerateAiSummaryJob;
use App\Models\AiFeedback;
use App\Models\AiSummary;
use App\Services\Ai\AiSubjectDefinition;
use App\Services\Ai\AiSubjectRegistry;
use App\Services\Ai\AiSummaryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

/**
 * Reads and refreshes the AI summary of any subject registered in
 * config/ai_summaries.php. Access is checked against that subject's ACL keys.
 */
class AiSummaryController extends Controller
{
    /** Resolved once: the same for every ownership check in one request. */
    private ?array $authorizedUserIds = null;

    private bool $authorizedUserIdsResolved = false;

    public function __construct(
        private readonly AiSubjectRegistry $registry,
        private readonly AiSummaryService $summaryService,
    ) {}

    public function show(string $subject, int $id): JsonResponse
    {
        [$definition, $model] = $this->resolve($subject, $id, 'view');

        $summary = $model->aiSummary;

        if ($this->shouldGenerateOnView($definition, $summary)) {
            $summary = $this->summaryService->summaryFor($model, $definition);
            $summary->update(['status' => 'queued', 'last_error' => null]);

            GenerateAiSummaryJob::dispatch($definition->key, $id, 'view');
        }

        return response()->json([
            'data' => [
                'summary'  => $summary ? [
                    'id'                 => $summary->id,
                    'summary'            => $summary->summary,
                    'next_action_title'  => $summary->next_action_title,
                    'next_action_reason' => $summary->next_action_reason,
                    'priority'           => $summary->priority,
                    'highlights'         => $summary->highlights ?? [],
                    'attention_points'   => $this->attentionPointsData($summary),
                    'generated_at'       => $summary->generated_at?->toIso8601String(),
                    'status'             => $summary->status,
                ] : null,
                'feedback' => $model->aiFeedback()
                    ->with('user')
                    ->where('is_active', true)
                    ->oldest('created_at')
                    ->get()
                    ->map(fn (AiFeedback $feedback) => $this->feedbackData($feedback))
                    ->values(),
            ],
        ]);
    }

    public function generate(string $subject, int $id): JsonResponse
    {
        [$definition, $model] = $this->resolve($subject, $id, 'edit');

        if (! $this->registry->isEnabled($definition)) {
            return response()->json([
                'message' => 'AI-samenvattingen zijn momenteel uitgeschakeld.',
            ], 503);
        }

        $summary = $this->summaryService->summaryFor($model, $definition);

        // A job for this subject is already queued, running, or waiting on a retry
        // (GenerateAiSummaryJob is unique per subject); dispatching another one
        // would silently no-op, so tell the user instead of a false "started" message.
        //
        // Only while that state is fresh, though: a worker killed mid-run leaves the
        // status pending forever, and refusing on that would strand the button.
        if (! $summary->wasRecentlyCreated && $summary->isPending() && ! $summary->pendingSinceLooksStale()) {
            return response()->json([
                'message' => 'Er loopt al een verversing voor deze '.$definition->label.'.',
            ], 409);
        }

        $summary->update([
            'status'     => 'queued',
            'last_error' => null,
        ]);

        GenerateAiSummaryJob::dispatch($definition->key, $id, 'manual');

        return response()->json([
            'message' => 'De AI-samenvatting wordt opnieuw gegenereerd.',
        ], 202);
    }

    public function storeFeedback(SaveAiFeedbackRequest $request, string $subject, int $id): JsonResponse
    {
        [, $model] = $this->resolve($subject, $id, 'edit');

        $feedback = $model->aiFeedback()->create([
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
        SaveAiFeedbackRequest $request,
        string $subject,
        int $id,
        AiFeedback $feedback,
    ): JsonResponse {
        [, $model] = $this->resolve($subject, $id, 'edit');
        $this->assertFeedbackBelongsTo($feedback, $model);

        $feedback->update([
            'feedback'                  => trim($request->validated('feedback')),
            'included_in_generation_at' => null,
        ]);

        return response()->json([
            'message' => 'AI-correctie bijgewerkt.',
            'data'    => $this->feedbackData($feedback->load('user')),
        ]);
    }

    public function destroyFeedback(string $subject, int $id, AiFeedback $feedback): JsonResponse
    {
        [, $model] = $this->resolve($subject, $id, 'edit');
        $this->assertFeedbackBelongsTo($feedback, $model);

        $feedback->update(['is_active' => false]);
        $feedback->delete();

        return response()->json([
            'message' => 'AI-correctie verwijderd.',
        ]);
    }

    /**
     * @param  'view'|'edit'  $ability
     * @return array{0: AiSubjectDefinition, 1: Model}
     */
    private function resolve(string $subject, int $id, string $ability): array
    {
        $definition = $this->registry->find($subject);

        abort_if($definition === null, 404);

        $permission = $ability === 'edit' ? $definition->editPermission : $definition->viewPermission;

        abort_unless(bouncer()->hasPermission($permission), 403);

        $model = $definition->findOrFail($id);

        if ($definition->ownerScoped) {
            abort_unless($this->ownerIsAccessible($model), 403);
        }

        return [$definition, $model];
    }

    /**
     * Users limited to their own (or their group's) records must not read a summary
     * about somebody else's record either.
     */
    private function ownerIsAccessible(Model $model): bool
    {
        $ownerId = $model->getAttribute('user_id');

        return ! $this->authorizedUserIds() || ! $ownerId || in_array($ownerId, $this->authorizedUserIds());
    }

    /**
     * Null means "may see everything"; resolving it can hit the database for group-scoped
     * users, and the panel polls this endpoint while a generation runs.
     *
     * @return array<int, int>|null
     */
    private function authorizedUserIds(): ?array
    {
        if (! $this->authorizedUserIdsResolved) {
            $this->authorizedUserIds = bouncer()->getAuthorizedUserIds();
            $this->authorizedUserIdsResolved = true;
        }

        return $this->authorizedUserIds;
    }

    private function assertFeedbackBelongsTo(AiFeedback $feedback, Model $model): void
    {
        abort_unless(
            $feedback->subject_type === $model->getMorphClass()
                && (int) $feedback->subject_id === (int) $model->getKey()
                && $feedback->is_active,
            404,
        );
    }

    private function shouldGenerateOnView(AiSubjectDefinition $definition, ?AiSummary $summary): bool
    {
        if (! $definition->generateOnView || ! $this->registry->isEnabled($definition)) {
            return false;
        }

        if ($summary === null) {
            return true;
        }

        if ($summary->isPending() || $summary->status === 'failed') {
            return false;
        }

        return $definition->staleAfterHours > 0
            && $summary->generated_at !== null
            && $summary->generated_at->lt(now()->subHours($definition->staleAfterHours));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function attentionPointsData(AiSummary $summary): array
    {
        return collect($summary->attention_points ?? [])
            ->filter(fn (mixed $point) => is_array($point)
                && is_string($point['text'] ?? null)
                && is_array($point['source'] ?? null)
                && is_string($point['source']['date'] ?? null))
            ->map(function (array $point) {
                $source = $point['source'];
                $source['url'] = $this->sourceUrl($source);

                return [
                    'text'   => $point['text'],
                    'source' => $source,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Deep link for an attention point's source. Only entities that are registered as
     * AI subjects get one, so route, permission and owner scoping all come from the
     * same config the summary itself was generated from.
     *
     * @param  array<string, mixed>  $source
     */
    private function sourceUrl(array $source): ?string
    {
        $entityType = EntityType::tryFrom((string) ($source['type'] ?? ''));

        if ($entityType === null || ! is_numeric($source['entity_id'] ?? null)) {
            return null;
        }

        /** @var Model $prototype */
        $prototype = new ($entityType->getModel());
        $definition = $this->registry->findForModel($prototype);

        if ($definition === null || ! bouncer()->hasPermission($definition->viewPermission)) {
            return null;
        }

        $routeName = $definition->route ?? $entityType->getRoute();

        if (! Route::has($routeName)) {
            return null;
        }

        $target = $prototype->newQuery()->find((int) $source['entity_id']);

        if ($target === null) {
            return null;
        }

        if ($definition->ownerScoped && ! $this->ownerIsAccessible($target)) {
            return null;
        }

        return route($routeName, (int) $source['entity_id']);
    }

    /**
     * @return array<string, mixed>
     */
    private function feedbackData(AiFeedback $feedback): array
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
