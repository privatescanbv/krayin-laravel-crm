<?php

namespace App\Services\Ai;

use App\Enums\EntityType;
use App\Models\AiFeedback;
use App\Models\AiSummary;
use App\Models\AiSummaryGeneration;
use App\Models\Order;
use App\Models\SalesLead;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;
use Webkul\Activity\Models\Activity;
use Webkul\Email\Models\Email;
use Webkul\Lead\Models\Lead;

/**
 * Generates the AI summary for any registered subject. The subject-specific part
 * (which records matter, which prompt to use) comes from AiSubjectRegistry, so
 * this pipeline is identical for leads, persons, orders and sales leads.
 */
class AiSummaryService
{
    public function __construct(
        private readonly LlmService $llmService,
        private readonly AiSubjectRegistry $registry,
    ) {}

    public function generate(Model $subject, string $trigger = 'automatic'): AiSummary
    {
        $definition = $this->registry->forModel($subject);
        $builder = $this->registry->builder($definition);
        $model = AiPromptConfig::model($definition->useCase);
        $startedAt = now();
        $startedTimestamp = microtime(true);

        $summary = $this->summaryFor($subject, $definition);

        $summary->update([
            'status'         => 'processing',
            'last_error'     => null,
            'prompt_version' => $definition->promptVersion,
            'model'          => $model,
        ]);

        $generation = AiSummaryGeneration::query()->create([
            'subject_type'   => $subject->getMorphClass(),
            'subject_id'     => $subject->getKey(),
            'ai_summary_id'  => $summary->id,
            'status'         => 'processing',
            'model'          => $model,
            'prompt_version' => $definition->promptVersion,
            'started_at'     => $startedAt,
        ]);

        $rawResponse = null;

        try {
            $context = $builder->build($subject);
            $systemPrompt = (string) AiPromptConfig::prompt($definition->useCase);
            $payload = json_encode(
                $builder->forLlm($context),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
            $systemPromptBytes = strlen($systemPrompt);
            $userPayloadBytes = strlen($payload);

            $generation->update([
                'input_hash'       => hash('sha256', $payload),
                'context_snapshot' => array_merge(
                    $builder->auditSnapshot($context),
                    [
                        'trigger'             => $trigger,
                        'payload_bytes'       => $userPayloadBytes,
                        'system_prompt_bytes' => $systemPromptBytes,
                        'user_payload_bytes'  => $userPayloadBytes,
                    ],
                ),
            ]);

            $logContext = [
                'subject_type'        => $definition->key,
                'subject_id'          => $subject->getKey(),
                'generation_id'       => $generation->id,
                'trigger'             => $trigger,
                'system_prompt_bytes' => $systemPromptBytes,
                'user_payload_bytes'  => $userPayloadBytes,
            ];

            $usage = null;

            $rawResponse = $this->llmService->chat(
                useCase: $definition->useCase,
                userContent: $payload,
                context: $logContext,
                logContent: false,
                usage: $usage,
            );

            $response = $this->llmService->parseJsonResponse(
                $rawResponse,
                $logContext,
                $definition->useCase,
                false,
            );

            $validated = $this->validateResponse($response, $context, $logContext);
            $completedAt = now();
            $durationMs = (int) round((microtime(true) - $startedTimestamp) * 1000);

            DB::transaction(function () use (
                $summary,
                $generation,
                $validated,
                $rawResponse,
                $model,
                $definition,
                $builder,
                $completedAt,
                $durationMs,
                $context,
                $usage,
                $logContext,
            ) {
                // Rebuild under the lock so citations cannot be persisted from stale,
                // deleted or reassigned records.
                $subject = $definition->findOrFail((int) $summary->subject_id);

                $this->lockCitationSources($validated, $subject);

                $validated = $this->dropStaleCitations(
                    $validated,
                    $builder->build($subject),
                    $logContext,
                );

                $summary->update([
                    'summary'            => $validated['summary'],
                    'next_action_title'  => $validated['next_action']['title'] ?: null,
                    'next_action_reason' => $validated['next_action']['reason'] ?: null,
                    'priority'           => $validated['next_action']['priority'],
                    'highlights'         => $validated['highlights'],
                    'attention_points'   => $validated['attention_points'],
                    'generated_at'       => $completedAt,
                    'model'              => $model,
                    'prompt_version'     => $definition->promptVersion,
                    'status'             => 'completed',
                    'last_error'         => null,
                ]);

                $generation->update([
                    'status'          => 'completed',
                    'raw_response'    => $rawResponse,
                    'parsed_response' => $validated,
                    'tokens_input'    => $usage['prompt_tokens'] ?? null,
                    'tokens_output'   => $usage['completion_tokens'] ?? null,
                    'duration_ms'     => $durationMs,
                    'completed_at'    => $completedAt,
                ]);

                foreach ($context['active_feedback'] ?? [] as $includedFeedback) {
                    AiFeedback::query()
                        ->where('subject_type', $summary->subject_type)
                        ->where('subject_id', $summary->subject_id)
                        ->where('is_active', true)
                        ->where('id', $includedFeedback['id'])
                        ->where('updated_at', $includedFeedback['version'])
                        ->toBase()
                        ->update(['included_in_generation_at' => $completedAt]);
                }

                // Only the generation behind the current summary needs to stay around;
                // older attempts (including past failures) are no longer relevant once
                // a new one succeeds.
                AiSummaryGeneration::query()
                    ->where('subject_type', $summary->subject_type)
                    ->where('subject_id', $summary->subject_id)
                    ->where('id', '!=', $generation->id)
                    ->delete();
            });

            Log::info('AI summary generated', $logContext + [
                'duration_ms' => $durationMs,
                'model'       => $model,
            ]);
        } catch (ConnectionException $exception) {
            $durationMs = (int) round((microtime(true) - $startedTimestamp) * 1000);
            $error = Str::limit($exception->getMessage(), 2000, '');

            $this->recordFailure($generation, $summary, $error, $durationMs, $rawResponse);

            Log::error('AI summary generation failed to reach the LLM; job will be retried from the queue', [
                'subject_type'    => $definition->key,
                'subject_id'      => $subject->getKey(),
                'generation_id'   => $generation->id,
                'exception_class' => $exception::class,
                'error'           => $error,
            ]);

            // Rethrow so the queue job fails and is retried, instead of being
            // silently marked as processed while the LLM is unreachable.
            throw $exception;
        } catch (Throwable $exception) {
            $durationMs = (int) round((microtime(true) - $startedTimestamp) * 1000);
            $error = Str::limit($exception->getMessage(), 2000, '');

            $this->recordFailure(
                $generation,
                $summary,
                $error,
                $durationMs,
                $exception instanceof LlmJsonParseException ? $exception->rawContent : $rawResponse,
            );

            Log::error('AI summary generation failed', [
                'subject_type'    => $definition->key,
                'subject_id'      => $subject->getKey(),
                'generation_id'   => $generation->id,
                'exception_class' => $exception::class,
                'error'           => $error,
            ]);
        }

        return $summary->refresh();
    }

    /**
     * The summary row for a subject, created in "queued" state when it does not exist yet.
     */
    public function summaryFor(Model $subject, ?AiSubjectDefinition $definition = null): AiSummary
    {
        $definition ??= $this->registry->forModel($subject);

        return AiSummary::query()->firstOrCreate(
            [
                'subject_type' => $subject->getMorphClass(),
                'subject_id'   => $subject->getKey(),
            ],
            [
                'status'         => 'queued',
                'prompt_version' => $definition->promptVersion,
                'model'          => AiPromptConfig::model($definition->useCase),
            ],
        );
    }

    private function recordFailure(
        AiSummaryGeneration $generation,
        AiSummary $summary,
        string $error,
        int $durationMs,
        ?string $rawResponse,
    ): void {
        $generation->update([
            'status'        => 'failed',
            'raw_response'  => $rawResponse,
            'duration_ms'   => $durationMs,
            'error_message' => $error,
            'completed_at'  => now(),
        ]);

        $summary->update([
            'status'     => 'failed',
            'last_error' => $error,
        ]);
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $requestContext
     * @param  array<string, mixed>  $logContext
     * @return array{
     *     summary: string,
     *     next_action: array{title: string, reason: string, priority: string|null},
     *     highlights: list<array{label: string, value: string}>,
     *     attention_points: list<array{text: string, source: array<string, mixed>}>
     * }
     */
    private function validateResponse(
        array $response,
        array $requestContext,
        array $logContext,
    ): array {
        $validated = Validator::make($response, [
            'summary'                       => ['required', 'string', 'max:400'],
            'next_action'                   => ['present', 'array'],
            'next_action.title'             => ['nullable', 'string', 'max:80'],
            'next_action.reason'            => ['nullable', 'string', 'max:180'],
            'next_action.priority'          => ['nullable', 'in:low,medium,high'],
            'highlights'                    => ['present', 'array', 'max:3'],
            'highlights.*'                  => ['required', 'array'],
            'highlights.*.label'            => ['required', 'string', 'max:50'],
            'highlights.*.value'            => ['required', 'string', 'max:120'],
            'attention_points'              => ['present', 'array', 'max:3'],
            'attention_points.*'            => ['required', 'array'],
            'attention_points.*.text'       => ['required', 'string', 'max:160'],
            'attention_points.*.source_ref' => ['required', 'string', 'max:64'],
        ])->validate();

        $requestSources = collect($requestContext['sources'] ?? [])->keyBy('ref');

        return [
            'summary'     => trim($validated['summary']),
            'next_action' => [
                'title'    => trim((string) ($validated['next_action']['title'] ?? '')),
                'reason'   => trim((string) ($validated['next_action']['reason'] ?? '')),
                'priority' => $validated['next_action']['priority'] ?? null,
            ],
            'highlights' => collect($validated['highlights'])
                ->map(fn (array $highlight) => [
                    'label' => trim($highlight['label']),
                    'value' => trim($highlight['value']),
                ])
                ->values()
                ->all(),
            // A model that invents or concatenates a source_ref should cost us that one
            // attention point, not the whole summary: the rest of the answer is still
            // usable and a generation is expensive.
            'attention_points' => collect($validated['attention_points'])
                ->map(function (array $point) use ($requestSources, $logContext) {
                    $source = $requestSources->get($point['source_ref']);

                    if (! is_array($source)) {
                        Log::warning('Aandachtspunt overgeslagen: onbekende bronverwijzing', $logContext + [
                            'source_ref' => $point['source_ref'],
                        ]);

                        return null;
                    }

                    return [
                        'text'   => trim($point['text']),
                        'source' => [
                            'ref'        => $source['ref'],
                            'type'       => $source['type'],
                            'entity_id'  => $source['entity_id'],
                            'label'      => $source['label'],
                            'date'       => $source['date'],
                            'date_label' => $source['date_label'],
                            'version'    => $source['version'],
                        ],
                    ];
                })
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /**
     * Final guard against a cited record changing between generation and save. A point
     * that no longer matches is dropped rather than failing the whole summary.
     *
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $logContext
     * @return array<string, mixed>
     */
    private function dropStaleCitations(array $validated, array $context, array $logContext): array
    {
        $sources = collect($context['sources'] ?? [])->keyBy('ref');

        $validated['attention_points'] = collect($validated['attention_points'])
            ->filter(function (array $point) use ($sources, $logContext) {
                $currentSource = $sources->get($point['source']['ref']);

                if (
                    ! is_array($currentSource)
                    || ($currentSource['version'] ?? null) !== $point['source']['version']
                ) {
                    Log::warning('Aandachtspunt overgeslagen: bron niet meer actueel', $logContext + [
                        'source_ref' => $point['source']['ref'],
                    ]);

                    return false;
                }

                return true;
            })
            ->values()
            ->all();

        return $validated;
    }

    /**
     * Keep the subject and every cited record stable through the final source check
     * and the summary update.
     *
     * @param  array<string, mixed>  $validated
     */
    private function lockCitationSources(array $validated, Model $subject): void
    {
        $subject->newQuery()->whereKey($subject->getKey())->lockForUpdate()->first();

        collect($validated['attention_points'])
            ->pluck('source')
            ->unique(fn (array $source) => $source['type'].':'.$source['entity_id'])
            ->each(function (array $source) {
                if ($source['type'] === 'order') {
                    $order = Order::query()
                        ->whereKey($source['entity_id'])
                        ->lockForUpdate()
                        ->first();

                    if ($order) {
                        $salesLead = SalesLead::query()
                            ->whereKey($order->sales_lead_id)
                            ->lockForUpdate()
                            ->first();

                        if ($salesLead?->lead_id) {
                            Lead::query()->whereKey($salesLead->lead_id)->lockForUpdate()->first();
                        }
                    }

                    return;
                }

                if ($source['type'] === 'sales') {
                    $salesLead = SalesLead::query()
                        ->whereKey($source['entity_id'])
                        ->lockForUpdate()
                        ->first();

                    if ($salesLead?->lead_id) {
                        Lead::query()->whereKey($salesLead->lead_id)->lockForUpdate()->first();
                    }

                    return;
                }

                // CRM entities come from the shared EntityType map; the rest are records
                // only the summary itself cites.
                $model = EntityType::tryFrom($source['type'])?->getModel() ?? match ($source['type']) {
                    'activity' => Activity::class,
                    'email'    => Email::class,
                    'feedback' => AiFeedback::class,
                    default    => null,
                };

                if ($model) {
                    $model::query()
                        ->whereKey($source['entity_id'])
                        ->lockForUpdate()
                        ->first();
                }

                if ($source['type'] === 'lead') {
                    DB::table('lead_persons')
                        ->where('lead_id', $source['entity_id'])
                        ->lockForUpdate()
                        ->get();
                } elseif ($source['type'] === 'person') {
                    DB::table('lead_persons')
                        ->where('person_id', $source['entity_id'])
                        ->lockForUpdate()
                        ->get();
                }
            });
    }
}
