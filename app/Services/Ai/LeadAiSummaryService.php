<?php

namespace App\Services\Ai;

use App\Models\LeadAiFeedback;
use App\Models\LeadAiSummary;
use App\Models\LeadAiSummaryGeneration;
use App\Models\Order;
use App\Models\SalesLead;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Email\Models\Email;
use Webkul\Lead\Models\Lead;

class LeadAiSummaryService
{
    public function __construct(
        private readonly LlmService $llmService,
        private readonly LeadAiContextService $contextService,
    ) {}

    public function generate(Lead $lead, string $trigger = 'automatic'): LeadAiSummary
    {
        $promptVersion = (string) config('services.llm.lead_summary.prompt_version', 'v3');
        $model = AiPromptConfig::model('lead_summary');
        $startedAt = now();
        $startedTimestamp = microtime(true);

        $summary = LeadAiSummary::query()->firstOrCreate(
            ['lead_id' => $lead->id],
            [
                'status'         => 'pending',
                'prompt_version' => $promptVersion,
                'model'          => $model,
            ],
        );

        $summary->update([
            'status'         => 'processing',
            'last_error'     => null,
            'prompt_version' => $promptVersion,
            'model'          => $model,
        ]);

        $generation = LeadAiSummaryGeneration::query()->create([
            'lead_id'            => $lead->id,
            'lead_ai_summary_id' => $summary->id,
            'status'             => 'processing',
            'model'              => $model,
            'prompt_version'     => $promptVersion,
            'started_at'         => $startedAt,
        ]);

        $rawResponse = null;

        try {
            $context = $this->contextService->build($lead);
            $systemPrompt = (string) AiPromptConfig::prompt('lead_summary');
            $payload = json_encode(
                $this->contextService->forLlm($context),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
            $systemPromptBytes = strlen($systemPrompt);
            $userPayloadBytes = strlen($payload);
            $inputHash = hash('sha256', $payload);

            $generation->update([
                'input_hash'       => $inputHash,
                'context_snapshot' => array_merge(
                    $this->contextService->auditSnapshot($context),
                    [
                        'trigger'             => $trigger,
                        'payload_bytes'       => $userPayloadBytes,
                        'system_prompt_bytes' => $systemPromptBytes,
                        'user_payload_bytes'  => $userPayloadBytes,
                    ],
                ),
            ]);

            $usage = null;

            $rawResponse = $this->llmService->chat(
                useCase: 'lead_summary',
                userContent: $payload,
                context: [
                    'lead_id'             => $lead->id,
                    'generation_id'       => $generation->id,
                    'trigger'             => $trigger,
                    'system_prompt_bytes' => $systemPromptBytes,
                    'user_payload_bytes'  => $userPayloadBytes,
                ],
                logContent: false,
                usage: $usage,
            );

            $response = $this->llmService->parseJsonResponse(
                $rawResponse,
                [
                    'lead_id'       => $lead->id,
                    'generation_id' => $generation->id,
                ],
                'lead_summary',
                false,
            );

            // Rebuild after the LLM call so citations cannot be persisted from stale,
            // deleted, or reassigned records.
            $verificationContext = $this->contextService->build(Lead::findOrFail($lead->id));
            $validated = $this->validateResponse($response, $context, $verificationContext, $lead->id);
            $completedAt = now();
            $durationMs = (int) round((microtime(true) - $startedTimestamp) * 1000);

            DB::transaction(function () use (
                $summary,
                $generation,
                $validated,
                $rawResponse,
                $model,
                $promptVersion,
                $completedAt,
                $durationMs,
                $context,
                $usage,
            ) {
                $this->lockCitationSources($validated, $summary->lead_id);
                $validated = $this->dropStaleCitations(
                    $validated,
                    $this->contextService->build(Lead::findOrFail($summary->lead_id)),
                    $summary->lead_id,
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
                    'prompt_version'     => $promptVersion,
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
                    LeadAiFeedback::query()
                        ->where('lead_id', $summary->lead_id)
                        ->where('is_active', true)
                        ->where('id', $includedFeedback['id'])
                        ->where('updated_at', $includedFeedback['version'])
                        ->toBase()
                        ->update(['included_in_generation_at' => $completedAt]);
                }

                // Only the generation behind the current summary needs to stay around;
                // older attempts (including past failures) are no longer relevant once
                // a new one succeeds.
                LeadAiSummaryGeneration::query()
                    ->where('lead_id', $summary->lead_id)
                    ->where('id', '!=', $generation->id)
                    ->delete();
            });

            Log::info('Lead AI summary generated', [
                'lead_id'       => $lead->id,
                'generation_id' => $generation->id,
                'duration_ms'   => $durationMs,
                'model'         => $model,
            ]);
        } catch (ConnectionException $exception) {
            $durationMs = (int) round((microtime(true) - $startedTimestamp) * 1000);
            $error = Str::limit($exception->getMessage(), 2000, '');

            $this->recordFailure($generation, $summary, $error, $durationMs, $rawResponse);

            Log::error('Lead AI summary generation failed to reach the LLM; job will be retried from the queue', [
                'lead_id'         => $lead->id,
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

            Log::error('Lead AI summary generation failed', [
                'lead_id'         => $lead->id,
                'generation_id'   => $generation->id,
                'exception_class' => $exception::class,
                'error'           => $error,
            ]);
        }

        return $summary->refresh();
    }

    private function recordFailure(
        LeadAiSummaryGeneration $generation,
        LeadAiSummary $summary,
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
     * @return array{
     *     summary: string,
     *     next_action: array{title: string, reason: string, priority: string|null},
     *     highlights: list<array{label: string, value: string}>,
     *     attention_points: list<array{
     *         text: string,
     *         source: array{
     *             ref: string,
     *             type: string,
     *             entity_id: int,
     *             label: string,
     *             date: string,
     *             date_label: string,
     *             version: string
     *         }
     *     }>
     * }
     */
    private function validateResponse(
        array $response,
        array $requestContext,
        array $verificationContext,
        int $leadId,
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
        $currentSources = collect($verificationContext['sources'] ?? [])->keyBy('ref');

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
                ->map(function (array $point) use ($requestSources, $currentSources, $leadId) {
                    $requestedSource = $requestSources->get($point['source_ref']);
                    $source = $currentSources->get($point['source_ref']);

                    if (! is_array($requestedSource) || ! is_array($source)) {
                        Log::warning('Aandachtspunt overgeslagen: onbekende bronverwijzing', [
                            'lead_id'    => $leadId,
                            'source_ref' => $point['source_ref'],
                        ]);

                        return null;
                    }

                    if (($requestedSource['version'] ?? null) !== ($source['version'] ?? null)) {
                        Log::warning('Aandachtspunt overgeslagen: bron gewijzigd tijdens generatie', [
                            'lead_id'    => $leadId,
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
     * @return array<string, mixed>
     */
    private function dropStaleCitations(array $validated, array $context, int $leadId): array
    {
        $sources = collect($context['sources'] ?? [])->keyBy('ref');

        $validated['attention_points'] = collect($validated['attention_points'])
            ->filter(function (array $point) use ($sources, $leadId) {
                $currentSource = $sources->get($point['source']['ref']);

                if (
                    ! is_array($currentSource)
                    || ($currentSource['version'] ?? null) !== $point['source']['version']
                ) {
                    Log::warning('Aandachtspunt overgeslagen: bron niet meer actueel', [
                        'lead_id'    => $leadId,
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
     * Keep cited records stable through the final source check and summary update.
     *
     * @param  array<string, mixed>  $validated
     */
    private function lockCitationSources(array $validated, int $targetLeadId): void
    {
        Lead::query()->whereKey($targetLeadId)->lockForUpdate()->first();

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

                        if ($salesLead) {
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

                    if ($salesLead) {
                        Lead::query()->whereKey($salesLead->lead_id)->lockForUpdate()->first();
                    }

                    return;
                }

                $model = match ($source['type']) {
                    'lead'         => Lead::class,
                    'person'       => Person::class,
                    'organization' => Organization::class,
                    'activity'     => Activity::class,
                    'email'        => Email::class,
                    'feedback'     => LeadAiFeedback::class,
                    default        => null,
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
