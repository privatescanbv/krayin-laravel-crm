<?php

namespace App\Services\Ai\Context;

use App\Enums\ActivityType;
use App\Models\AiFeedback;
use App\Models\Order;
use App\Models\SalesLead;
use App\Services\Ai\AiSubjectDefinition;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Webkul\Activity\Models\Activity;
use Webkul\Email\Models\Email;
use Webkul\Lead\Models\Lead;

/**
 * Shared context gathering for every AI summary subject.
 *
 * Subclasses answer only two questions: which records are relevant (resolveScope)
 * and how the subject itself is described (subjectEntry). Everything else —
 * commercial history, timeline selection and scoring, feedback, citation sources
 * and the compact LLM projection — lives here and is identical for all subjects.
 */
abstract class AiContextBuilder
{
    /** @var list<string> */
    protected const CUSTOMER_CONTACT_ACTIVITY_TYPES = [
        ActivityType::CALL->value,
        ActivityType::PATIENT_MESSAGE->value,
    ];

    /** @var list<string> */
    protected const TIMELINE_ACTIVITY_TYPES = [
        ActivityType::CALL->value,
        ActivityType::TASK->value,
        ActivityType::NOTE->value,
        ActivityType::PATIENT_MESSAGE->value,
    ];

    /** Domains that mark an email as outgoing staff mail. */
    protected const STAFF_EMAIL_DOMAINS = [
        'privatescan.nl',
        'herniapoli.nl',
        'mbsoftware.nl',
    ];

    /** How many leads and sales leads of the same patient are considered at most. */
    protected const RELATED_RECORD_LIMIT = 20;

    /*
    |--------------------------------------------------------------------------
    | Projection
    |--------------------------------------------------------------------------
    */
    /** Internal bookkeeping that never reaches the model. */
    private const INTERNAL_KEYS = ['id', 'updated_at', 'sort_at', 'score'];

    private ?AiSubjectDefinition $definition = null;

    public function for(AiSubjectDefinition $definition): static
    {
        $clone = clone $this;
        $clone->definition = $definition;

        return $clone;
    }

    public function definition(): AiSubjectDefinition
    {
        return $this->definition ?? throw new RuntimeException(
            static::class.' was used without a subject definition; resolve it through AiSubjectRegistry::builder().'
        );
    }

    /**
     * Build the internal context used for citation validation, audit snapshots and
     * feedback inclusion tracking. Call forLlm() before sending anything to the model.
     *
     * @return array<string, mixed>
     */
    final public function build(Model $subject): array
    {
        $scope = $this->resolveScope($subject);

        $activities = $this->fetchActivities($scope);
        $emails = $this->fetchEmails($scope);

        $feedback = AiFeedback::query()
            ->with('user')
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->where('is_active', true)
            ->oldest('created_at')
            ->get();

        $timeline = $this->buildTimeline($activities, $emails, $scope);

        $context = [
            'subject_key'           => $this->definition()->key,
            'payload_key'           => $this->definition()->payloadKey,
            'subject'               => $this->subjectEntry($subject, $scope),
            'current_order'         => $this->currentOrderEntry($scope),
            'history'               => $this->historyEntries($scope),
            'timeline'              => $timeline,
            'extra'                 => $this->extraBlocks($subject, $scope),
            'active_feedback'       => $feedback->map(fn (AiFeedback $item) => $this->feedbackEntry($item))->values()->all(),
            'last_customer_contact' => $this->lastCustomerContact($activities, $emails, $timeline),
            // Kept for audit / ownership checks that still reason about related records.
            'historical_lead_ids'   => $scope->historicalLeadIds()->all(),
            'sales_ids'             => $scope->salesLeadIds()->all(),
        ];

        $context['sources'] = $this->sourceCatalog($context);

        return $context;
    }

    /**
     * Project the internal context into the compact payload sent to the LLM.
     * Source metadata stays server-side; every citable row carries an inline ref.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    final public function forLlm(array $context): array
    {
        $payload = [
            ($context['payload_key'] ?? 'subject') => $this->project($context['subject'] ?? []),
        ];

        if (! empty($context['current_order'])) {
            $payload['current_order'] = $this->projectOrder($context['current_order']);
        }

        $history = array_map(
            fn (array $entry): array => $this->projectOrder($entry),
            $context['history'] ?? [],
        );

        if ($history !== []) {
            $payload['history'] = $history;
        }

        $timeline = array_map(
            fn (array $entry): array => $this->projectDatedEntry($entry),
            $context['timeline'] ?? [],
        );

        if ($timeline !== []) {
            $payload['timeline'] = $timeline;
        }

        foreach ($context['extra'] ?? [] as $key => $block) {
            $projected = $this->project($block);

            if ($projected !== [] && $projected !== null) {
                $payload[$key] = $projected;
            }
        }

        $feedback = array_values(array_filter(array_map(
            function (array $item): ?array {
                $ref = $item['_source']['ref'] ?? null;
                $text = $item['correction'] ?? null;

                if (! is_string($ref) || ! is_string($text) || $text === '') {
                    return null;
                }

                return ['ref' => $ref, 'text' => $text];
            },
            $context['active_feedback'] ?? [],
        )));

        if ($feedback !== []) {
            $payload['feedback'] = $feedback;
        }

        if (! empty($context['last_customer_contact'])) {
            $payload['last_customer_contact'] = $this->projectDatedEntry($context['last_customer_contact'], withType: false);
        }

        return $payload;
    }

    /**
     * Store only identifiers, counts and timestamps for auditability; sensitive source text stays out
     * of the generation snapshot and application logs.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    final public function auditSnapshot(array $context): array
    {
        return [
            'subject_key'         => $context['subject_key'] ?? null,
            'subject_id'          => $context['subject']['id'] ?? null,
            'subject_updated_at'  => $context['subject']['updated_at'] ?? null,
            'historical_lead_ids' => $context['historical_lead_ids'] ?? [],
            'sales_ids'           => $context['sales_ids'] ?? [],
            'history_count'       => count($context['history'] ?? []),
            'timeline_count'      => count($context['timeline'] ?? []),
            'feedback'            => collect($context['active_feedback'] ?? [])
                ->map(fn (array $item) => ['id' => $item['id'], 'updated_at' => $item['updated_at']])
                ->values()
                ->all(),
        ];
    }

    /**
     * Which records this subject may reason about.
     */
    abstract protected function resolveScope(Model $subject): AiContextScope;

    /**
     * Compact description of the subject itself, including a "_source" entry so it
     * can be cited. Keys "id" and "updated_at" stay server-side.
     *
     * @return array<string, mixed>
     */
    abstract protected function subjectEntry(Model $subject, AiContextScope $scope): array;

    /**
     * Extra payload blocks specific to one subject (e.g. an order's open checks).
     * Values are projected generically; any nested "_source" is citable.
     *
     * @return array<string, mixed>
     */
    protected function extraBlocks(Model $subject, AiContextScope $scope): array
    {
        return [];
    }

    /*
    |--------------------------------------------------------------------------
    | Scope resolution
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve the shared scope from the patient(s) involved.
     *
     * Commercial history belongs to the patient, not to the current assignee, so all
     * leads of the same person are pulled in regardless of owner or stage. Which of
     * them count as "current" is what makes a lead summary differ from an order one.
     *
     * @param  Collection<int, int>  $personIds
     * @param  bool  $patientWide  For subjects that span the whole patient rather than one deal:
     *                             every lead counts as the running thread, and only orders that
     *                             are still ahead count as running — executed ones are history.
     */
    protected function scopeForPersons(
        Collection $personIds,
        ?int $primaryLeadId = null,
        ?int $currentSalesLeadId = null,
        ?int $currentOrderId = null,
        bool $patientWide = false,
    ): AiContextScope {
        $personIds = $personIds->filter()->unique()->values();

        $leads = Lead::query()
            ->with(['stage', 'source', 'type'])
            ->where(function ($query) use ($personIds, $primaryLeadId) {
                $query->whereRaw('1 = 0');

                if ($primaryLeadId) {
                    $query->orWhere('id', $primaryLeadId);
                }

                if ($personIds->isNotEmpty()) {
                    $query->orWhere(function ($samePerson) use ($personIds) {
                        $samePerson
                            ->whereIn('contact_person_id', $personIds)
                            ->orWhereIn('id', DB::table('lead_persons')
                                ->select('lead_id')
                                ->whereIn('person_id', $personIds));
                    });
                }
            })
            ->latest('created_at')
            ->limit(self::RELATED_RECORD_LIMIT)
            ->get();

        // The subject's own lead can fall outside that window on a long patient history.
        if ($primaryLeadId !== null && ! $leads->contains('id', $primaryLeadId)) {
            $primaryLead = Lead::query()->with(['stage', 'source', 'type'])->find($primaryLeadId);

            if ($primaryLead) {
                $leads = $leads->prepend($primaryLead);
            }
        }

        $leadIds = $leads->pluck('id')->values();

        $currentLeadIds = ($patientWide ? $leadIds : collect(array_filter([$primaryLeadId])))
            ->filter()
            ->unique()
            ->values();

        $salesLeads = $leadIds->isEmpty()
            ? collect()
            : SalesLead::query()
                ->with(['stage', 'orders.stage'])
                ->whereIn('lead_id', $leadIds)
                ->latest('created_at')
                ->limit(self::RELATED_RECORD_LIMIT)
                ->get();

        // A sales lead can be the subject without its lead being reachable (or set),
        // so make sure it is always part of the scope.
        if ($currentSalesLeadId !== null && ! $salesLeads->contains('id', $currentSalesLeadId)) {
            $extra = SalesLead::query()
                ->with(['stage', 'orders.stage'])
                ->whereKey($currentSalesLeadId)
                ->first();

            if ($extra) {
                $salesLeads = $salesLeads->prepend($extra);
            }
        }

        $currentSalesLeads = $currentSalesLeadId !== null
            ? $salesLeads->where('id', $currentSalesLeadId)->values()
            : $salesLeads->whereIn('lead_id', $currentLeadIds->all())->values();

        $currentOrderIds = match (true) {
            $currentOrderId !== null => collect([$currentOrderId]),
            $patientWide             => $salesLeads->flatMap->orders
                ->filter(fn (Order $order) => $order->first_examination_at?->isFuture() === true)
                ->pluck('id')
                ->values(),
            default => $currentSalesLeads->flatMap->orders->pluck('id')->values(),
        };

        return new AiContextScope(
            leadIds: $leadIds,
            currentLeadIds: $currentLeadIds,
            leads: $leads,
            historicalLeads: $leads->reject(fn (Lead $lead) => $currentLeadIds->contains($lead->id))->values(),
            salesLeads: $salesLeads,
            currentSalesLeads: $currentSalesLeads,
            currentOrderIds: $currentOrderIds,
        );
    }

    /**
     * Every person attached to a lead or sales lead, including its contact person.
     *
     * @param  Lead|SalesLead  $record
     * @return Collection<int, int>
     */
    protected function personIdsOf(Model $record): Collection
    {
        $record->loadMissing('persons');

        return $record->persons
            ->pluck('id')
            ->when($record->contact_person_id, fn (Collection $ids) => $ids->push($record->contact_person_id))
            ->filter()
            ->unique()
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Shared gathering
    |--------------------------------------------------------------------------
    */

    /**
     * @return Collection<int, Activity>
     */
    protected function fetchActivities(AiContextScope $scope): Collection
    {
        $limit = $this->definition()->activityLimit;

        return Activity::query()
            ->without('user')
            ->where(fn ($query) => $this->scopeToRelatedRecords($query, $scope))
            ->whereIn('type', static::TIMELINE_ACTIVITY_TYPES)
            ->latest('created_at')
            ->limit($limit * 2)
            ->get();
    }

    /**
     * @return Collection<int, Email>
     */
    protected function fetchEmails(AiContextScope $scope): Collection
    {
        $limit = $this->definition()->emailLimit;

        return Email::query()
            ->where(fn ($query) => $this->scopeToRelatedRecords($query, $scope))
            ->latest('created_at')
            ->limit($limit * 3)
            ->get();
    }

    /**
     * Compact commercial history: everything outside the current thread.
     *
     * Prefer order/sales rows when present; otherwise fall back to the lead itself
     * (important for lost leads that never became a sales lead/order).
     *
     * @return list<array<string, mixed>>
     */
    protected function historyEntries(AiContextScope $scope): array
    {
        $entries = [];
        $leadIdsCoveredBySales = [];

        foreach ($scope->salesLeads as $salesLead) {
            if ($salesLead->orders->isEmpty()) {
                // A deal without orders only says something as history; as the running
                // thread it is already described by the subject block.
                if ($scope->currentSalesLeads->contains('id', $salesLead->id)) {
                    continue;
                }

                $entry = $this->historyEntryFor($salesLead, $this->salesLeadSource($salesLead));

                if ($entry !== null) {
                    $entries[] = $entry;

                    if ($salesLead->lead_id) {
                        $leadIdsCoveredBySales[$salesLead->lead_id] = true;
                    }
                }

                continue;
            }

            // Everything that is not part of the running thread is history, including
            // sibling orders of the order or deal being summarised.
            foreach ($salesLead->orders as $order) {
                if ($scope->currentOrderIds->contains($order->id)) {
                    continue;
                }

                $entries[] = $this->orderEntry($order, $salesLead);

                if ($salesLead->lead_id) {
                    $leadIdsCoveredBySales[$salesLead->lead_id] = true;
                }
            }
        }

        foreach ($scope->historicalLeads as $historicalLead) {
            if (isset($leadIdsCoveredBySales[$historicalLead->id])) {
                continue;
            }

            $leadEntry = $this->historyEntryFor($historicalLead, $this->leadSource($historicalLead));

            if ($leadEntry !== null) {
                $entries[] = $leadEntry;
            }
        }

        return array_values(array_filter($entries));
    }

    /**
     * A lead or sales lead as one history line. Null when it carries no usable text
     * or has no date to cite.
     *
     * @param  Lead|SalesLead  $record
     * @param  array<string, mixed>|null  $source
     * @return array<string, mixed>|null
     */
    protected function historyEntryFor(Model $record, ?array $source): ?array
    {
        $description = $this->compactText($record->description ?: $record->name, 240);

        if ($description === null || $source === null) {
            return null;
        }

        $entry = [
            'description' => $description,
            'status'      => $record->stage?->name,
            'ref'         => $source['ref'],
            '_source'     => $source,
        ];

        if ($record->stage?->is_lost && $record->lost_reason) {
            $entry['lost_reason'] = $record->lost_reason->label();
        }

        return $entry;
    }

    /**
     * A separate "current_order" block, for subjects that have exactly one running order
     * worth calling out. Subjects that render their orders themselves leave this null.
     *
     * @return array<string, mixed>|null
     */
    protected function currentOrderEntry(AiContextScope $scope): ?array
    {
        return null;
    }

    /**
     * The newest running order of the scope, as a "current_order" block.
     *
     * @return array<string, mixed>|null
     */
    protected function newestCurrentOrderEntry(AiContextScope $scope): ?array
    {
        $order = $scope->currentOrders()
            ->sortByDesc(fn (Order $order) => $order->created_at?->getTimestamp() ?? 0)
            ->first();

        if (! $order instanceof Order) {
            return null;
        }

        $salesLead = $scope->salesLeads->firstWhere('id', $order->sales_lead_id);

        return $this->orderEntry($order, $salesLead instanceof SalesLead ? $salesLead : null);
    }

    /**
     * @return array<string, mixed>
     */
    protected function orderEntry(Order $order, ?SalesLead $salesLead = null): array
    {
        $label = 'Order: '.($order->order_number ?: $order->title ?: "#{$order->id}");
        $description = $this->compactText(
            $order->title ?: $salesLead?->description ?: $salesLead?->name,
            240,
        );
        $fingerprint = [
            'updated_at'           => $this->date($order->updated_at),
            'number'               => $order->order_number,
            'title'                => $order->title,
            'value'                => (float) $order->total_price,
            'stage'                => $order->stage?->name,
            'lost_reason'          => $order->stage?->is_lost ? $order->lost_reason?->label() : null,
            'first_examination_at' => $this->date($order->first_examination_at),
            'closed_at'            => $this->date($order->closed_at),
        ];

        $created = $this->source('order', $order->id, $label, $order->created_at, 'Aangemaakt', 'created', $fingerprint);
        $examination = $this->source('order', $order->id, $label, $order->first_examination_at, 'Onderzoeksdatum', 'examination', $fingerprint);
        $closed = $this->source('order', $order->id, $label, $order->closed_at, 'Afgesloten', 'closed', $fingerprint);

        $primary = $examination ?? $created ?? $closed;

        $entry = [
            'id'             => $order->id,
            'number'         => $order->order_number,
            'description'    => $description,
            'status'         => $order->stage?->name,
            'examination_at' => $this->dateOnly($order->first_examination_at),
            'value'          => (float) $order->total_price,
            'ref'            => $primary['ref'] ?? null,
            '_sources'       => array_values(array_filter([$created, $examination, $closed])),
        ];

        if ($created) {
            $entry['created_ref'] = $created['ref'];
        }

        if ($examination) {
            $entry['examination_ref'] = $examination['ref'];
        }

        if ($closed) {
            $entry['closed_ref'] = $closed['ref'];
        }

        if ($order->stage?->is_lost && $order->lost_reason) {
            $entry['lost_reason'] = $order->lost_reason->label();
        }

        return $entry;
    }

    /**
     * @return array<string, mixed>
     */
    protected function feedbackEntry(AiFeedback $item): array
    {
        $source = $this->source(
            'feedback',
            $item->id,
            'AI-correctie door '.($item->user?->name ?? 'onbekende gebruiker'),
            $item->updated_at ?? $item->created_at,
            'Laatst gewijzigd',
            null,
            [
                'updated_at' => $this->date($item->updated_at),
                'feedback'   => $item->feedback,
                'is_active'  => $item->is_active,
            ],
        );

        return [
            'id'         => $item->id,
            'correction' => $item->feedback,
            'updated_at' => $this->date($item->updated_at),
            'version'    => $item->getRawOriginal('updated_at'),
            'ref'        => $source['ref'] ?? null,
            '_source'    => $source,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Timeline
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Collection<int, Activity>  $activities
     * @param  Collection<int, Email>  $emails
     * @return list<array<string, mixed>>
     */
    protected function buildTimeline(Collection $activities, Collection $emails, AiContextScope $scope): array
    {
        $selectedActivities = $this->selectActivities($activities, $scope);

        $activityTexts = $selectedActivities
            ->map(fn (array $entry) => mb_strtolower((string) ($entry['text'] ?? '')))
            ->filter()
            ->values();

        $selectedEmails = $this->selectEmails($emails, $scope, $activityTexts);

        return collect($selectedActivities)
            ->merge($selectedEmails)
            ->sortBy(fn (array $entry) => $entry['sort_at'] ?? $entry['date'] ?? '')
            ->map(function (array $entry) {
                unset($entry['sort_at'], $entry['score']);

                return $entry;
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @return Collection<int, array<string, mixed>>
     */
    protected function selectActivities(Collection $activities, AiContextScope $scope): Collection
    {
        $limit = $this->definition()->activityLimit;
        $examinedOrderIds = $scope->examinedHistoricalOrderIds();
        $open = collect();
        $candidates = collect();

        foreach ($activities as $activity) {
            $type = $this->enumValue($activity->type);

            if ($type === null || ! in_array($type, static::TIMELINE_ACTIVITY_TYPES, true)) {
                continue;
            }

            if ($this->isMeaninglessSystemLikeActivity($activity)) {
                continue;
            }

            if ($this->isLowValueActivity($activity)) {
                continue;
            }

            // Historical execution notes are already represented by history examination refs.
            if (
                $activity->order_id
                && $examinedOrderIds->contains($activity->order_id)
                && $this->looksLikeExecutionNote($activity)
            ) {
                continue;
            }

            // Commercial history already covers earlier eras; keep the timeline focused
            // on the current thread unless the activity is an open task.
            if (! $this->isOnCurrentThread($activity, $scope) && ! (! $activity->is_done && $type === ActivityType::TASK->value)) {
                continue;
            }

            $text = $this->activityText($activity);

            if ($text === null) {
                continue;
            }

            $at = $activity->completed_at ?? $activity->schedule_from ?? $activity->created_at;
            $source = $this->source(
                'activity',
                $activity->id,
                'Activiteit: '.($activity->title ?: $text),
                $at,
                $activity->completed_at ? 'Afgerond' : ($activity->schedule_from ? 'Gepland' : 'Aangemaakt'),
                null,
                [
                    'updated_at'    => $this->date($activity->updated_at),
                    'type'          => $type,
                    'title'         => $activity->title,
                    'comment'       => $activity->comment,
                    'status'        => $this->enumValue($activity->status),
                    'is_done'       => $activity->is_done,
                    'schedule_from' => $this->date($activity->schedule_from),
                    'completed_at'  => $this->date($activity->completed_at),
                ],
            );

            if ($source === null) {
                continue;
            }

            $entry = [
                'ref'     => $source['ref'],
                'date'    => $this->dateOnly($at),
                'type'    => $type,
                'text'    => $text,
                'sort_at' => $this->date($at),
                'score'   => $this->activityScore($activity, $scope),
                '_source' => $source,
            ];

            if (! $activity->is_done && $type === ActivityType::TASK->value) {
                $open->push($entry);

                continue;
            }

            $candidates->push($entry);
        }

        $selected = $candidates
            ->sortByDesc(fn (array $entry) => $entry['score'])
            ->take(max(0, $limit - $open->count()))
            ->values();

        return $open->merge($selected)->values();
    }

    /**
     * @param  Collection<int, Email>  $emails
     * @param  Collection<int, string>  $activityTexts
     * @return Collection<int, array<string, mixed>>
     */
    protected function selectEmails(Collection $emails, AiContextScope $scope, Collection $activityTexts): Collection
    {
        $limit = $this->definition()->emailLimit;
        $seenFingerprints = [];
        $candidates = collect();

        foreach ($emails as $email) {
            // Prefer the current commercial thread; historical eras are covered by history[].
            if ($email->lead_id !== null && ! $scope->currentLeadIds->contains($email->lead_id)) {
                continue;
            }

            $subject = $this->compactText($email->subject, 240);
            $body = $this->compactText($email->reply, 600);
            $text = $this->emailText($subject, $body);

            if ($text === null) {
                continue;
            }

            if ($this->isLowValueEmail($subject, $body, $email, $scope)) {
                continue;
            }

            if ($this->isRepresentedByActivity($text, $activityTexts)) {
                continue;
            }

            $fingerprint = mb_strtolower(($subject ?? '').'|'.Str::limit($body ?? '', 120, ''));

            if (isset($seenFingerprints[$fingerprint])) {
                continue;
            }

            $seenFingerprints[$fingerprint] = true;

            $source = $this->emailSource($email, $subject, $body);

            if ($source === null) {
                continue;
            }

            $direction = $this->emailDirection($email);

            $candidates->push([
                'ref'       => $source['ref'],
                'date'      => $this->dateOnly($email->created_at),
                'type'      => 'email',
                'text'      => $text,
                'direction' => $direction,
                'sort_at'   => $this->date($email->created_at),
                'score'     => $this->emailScore($email, $scope, $direction, $subject, $body),
                '_source'   => $source,
            ]);
        }

        return $candidates
            ->sortByDesc(fn (array $entry) => $entry['score'])
            ->take($limit)
            ->values();
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @param  Collection<int, Email>  $emails
     * @param  list<array<string, mixed>>  $timeline
     * @return array<string, mixed>|null
     */
    protected function lastCustomerContact(Collection $activities, Collection $emails, array $timeline): ?array
    {
        $contacts = collect();

        foreach ($activities as $activity) {
            $type = $this->enumValue($activity->type);

            if ($type === null || ! in_array($type, static::CUSTOMER_CONTACT_ACTIVITY_TYPES, true)) {
                continue;
            }

            if ($this->isMeaninglessSystemLikeActivity($activity)) {
                continue;
            }

            $at = $activity->completed_at ?? $activity->created_at;

            if ($at === null) {
                continue;
            }

            $text = $this->activityText($activity);
            $source = $this->source(
                'activity',
                $activity->id,
                'Activiteit: '.($activity->title ?: ($text ?? 'contact')),
                $at,
                $activity->completed_at ? 'Afgerond' : 'Aangemaakt',
                null,
                [
                    'updated_at' => $this->date($activity->updated_at),
                    'type'       => $type,
                    'title'      => $activity->title,
                    'comment'    => $activity->comment,
                ],
            );

            if ($source === null || $text === null) {
                continue;
            }

            $contacts->push([
                'ref'       => $source['ref'],
                'date'      => $this->dateOnly($at),
                'direction' => 'outbound',
                'text'      => $text,
                'sort_at'   => $at->getTimestamp(),
                '_source'   => $source,
            ]);
        }

        foreach ($emails as $email) {
            if ($email->created_at === null) {
                continue;
            }

            $subject = $this->compactText($email->subject, 240);
            $body = $this->compactText($email->reply, 600);
            $text = $this->emailText($subject, $body);
            $direction = $this->emailDirection($email);
            $source = $this->emailSource($email, $subject, $body);

            if ($source === null || $text === null) {
                continue;
            }

            $contacts->push([
                'ref'       => $source['ref'],
                'date'      => $this->dateOnly($email->created_at),
                'direction' => $direction,
                'text'      => $text,
                'sort_at'   => $email->created_at->getTimestamp(),
                '_source'   => $source,
            ]);
        }

        $last = $contacts->sortByDesc('sort_at')->first();

        if (! is_array($last)) {
            return null;
        }

        // Skip when the newest timeline row already carries the same ref — no extra signal.
        $latestTimelineRef = collect($timeline)->last()['ref'] ?? null;

        if ($latestTimelineRef === $last['ref']) {
            return null;
        }

        unset($last['sort_at']);

        return $last;
    }

    /*
    |--------------------------------------------------------------------------
    | Relevance filters and scoring
    |--------------------------------------------------------------------------
    */

    protected function isOnCurrentThread(Activity $activity, AiContextScope $scope): bool
    {
        return $scope->currentLeadIds->contains($activity->lead_id)
            || $scope->currentOrderIds->contains($activity->order_id);
    }

    protected function activityText(Activity $activity): ?string
    {
        $title = $this->compactText($activity->title, 400);
        $comment = $this->compactText($activity->comment, 800);

        if ($title !== null && $comment !== null && $title !== $comment) {
            return $this->compactText($title.'. '.$comment, 900);
        }

        return $comment ?? $title;
    }

    protected function emailText(?string $subject, ?string $body): ?string
    {
        if ($subject !== null && $body !== null) {
            return $this->compactText($subject.': '.$body, 700);
        }

        return $body ?? $subject;
    }

    protected function isMeaninglessSystemLikeActivity(Activity $activity): bool
    {
        if ($this->enumValue($activity->type) === ActivityType::SYSTEM->value) {
            return true;
        }

        $title = mb_strtolower(trim((string) $activity->title));
        $comment = mb_strtolower(trim((string) $activity->comment));

        $createdLabel = mb_strtolower(trans('admin::app.activities.created'));

        return in_array($title, ['aangemaakt', $createdLabel, 'created'], true)
            && ($comment === '' || $comment === $title);
    }

    protected function isLowValueActivity(Activity $activity): bool
    {
        // Open tasks are always commercially relevant.
        if (! $activity->is_done && $this->enumValue($activity->type) === ActivityType::TASK->value) {
            return false;
        }

        $text = mb_strtolower((string) ($activity->comment ?: $activity->title));

        if ($text === '') {
            return true;
        }

        // Routine filler / internal noise that rarely changes the commercial conclusion.
        if (preg_match('/\b(automatische herinnering|interne notitie|interne afstemming|afspraakherinnering|bereikbaarheid gecheckt|geen gehoor|voicemail)\b/u', $text)) {
            return true;
        }

        return false;
    }

    protected function looksLikeExecutionNote(Activity $activity): bool
    {
        $text = mb_strtolower((string) ($activity->comment ?: $activity->title));

        return (bool) preg_match('/\b(scan uitgevoerd|onderzoek uitgevoerd|mri uitgevoerd|uitslag besproken)\b/u', $text);
    }

    protected function activityScore(Activity $activity, AiContextScope $scope): int
    {
        $score = 0;
        $at = $activity->completed_at ?? $activity->schedule_from ?? $activity->created_at;

        if ($this->isOnCurrentThread($activity, $scope)) {
            $score += 50;
        }

        if (! $activity->is_done) {
            $score += 40;
        }

        if ($at) {
            $daysAgo = max(0, (int) $at->diffInDays(now()));
            $score += max(0, 40 - $daysAgo);
        }

        $type = $this->enumValue($activity->type);

        if (in_array($type, [ActivityType::CALL->value, ActivityType::PATIENT_MESSAGE->value], true)) {
            $score += 10;
        }

        return $score;
    }

    protected function emailScore(
        Email $email,
        AiContextScope $scope,
        string $direction,
        ?string $subject,
        ?string $body,
    ): int {
        $score = 0;
        $daysAgo = $email->created_at ? max(0, (int) $email->created_at->diffInDays(now())) : 999;

        if ($scope->currentLeadIds->contains($email->lead_id)) {
            $score += 40;
        }

        if ($direction === 'incoming') {
            $score += 25;
        }

        $score += max(0, 40 - min($daysAgo, 40));

        $haystack = mb_strtolower(($subject ?? '').' '.($body ?? ''));

        if (preg_match('/\b(vraag|wanneer|planning|akkoord|bezwaar|afgewezen|goedgekeurd|vervolg)\b/u', $haystack)) {
            $score += 20;
        }

        if ($this->looksLikeAppointmentConfirmation($subject, $body) && $daysAgo > 14) {
            $score -= 50;
        }

        return $score;
    }

    protected function isLowValueEmail(?string $subject, ?string $body, Email $email, AiContextScope $scope): bool
    {
        if ($this->looksLikeAppointmentConfirmation($subject, $body)) {
            $daysAgo = $email->created_at ? max(0, (int) $email->created_at->diffInDays(now())) : 999;

            // Keep a recent confirmation on the current thread; drop older/historical ones.
            if ($daysAgo > 14 || ! $scope->currentLeadIds->contains($email->lead_id)) {
                return true;
            }
        }

        $haystack = mb_strtolower(($subject ?? '').' '.($body ?? ''));

        return (bool) preg_match('/\b(automatische statusupdate|geen actie vereist|herinnering: openstaande vraag)\b/u', $haystack)
            && ! preg_match('/\b(vraag|wanneer|planning|akkoord|bezwaar)\b/u', $haystack);
    }

    protected function looksLikeAppointmentConfirmation(?string $subject, ?string $body): bool
    {
        $haystack = mb_strtolower(($subject ?? '').' '.($body ?? ''));

        return (bool) preg_match('/\b(bevestiging afspraak|afspraakbevestiging|hierbij bevestigen wij uw afspraak)\b/u', $haystack);
    }

    /**
     * @param  Collection<int, string>  $activityTexts
     */
    protected function isRepresentedByActivity(string $emailText, Collection $activityTexts): bool
    {
        $needle = mb_strtolower(Str::limit($emailText, 120, ''));

        foreach ($activityTexts as $activityText) {
            similar_text($needle, $activityText, $percent);

            if ($percent >= 72) {
                return true;
            }
        }

        return false;
    }

    protected function emailDirection(Email $email): string
    {
        $from = strtolower((string) $email->sender_email);

        foreach (static::STAFF_EMAIL_DOMAINS as $domain) {
            if (str_ends_with($from, '@'.$domain)) {
                return 'outgoing';
            }
        }

        return 'incoming';
    }

    /**
     * Drop internal bookkeeping and empty values, recursively. This is what keeps a
     * new subject block or extra data block from needing its own projection code.
     */
    protected function project(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_values(array_filter(
                array_map(fn (mixed $item) => $this->project($item), $value),
                fn (mixed $item) => $item !== null && $item !== '' && $item !== [],
            ));
        }

        $projected = [];

        foreach ($value as $key => $item) {
            if (str_starts_with((string) $key, '_') || in_array($key, self::INTERNAL_KEYS, true)) {
                continue;
            }

            $item = $this->project($item);

            if ($item === null || $item === '' || $item === []) {
                continue;
            }

            $projected[$key] = $item;
        }

        return $projected;
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    protected function projectOrder(array $order): array
    {
        $ref = $order['ref'] ?? null;

        $projected = array_filter([
            'ref'            => $ref,
            'number'         => $order['number'] ?? null,
            'description'    => $order['description'] ?? null,
            'status'         => $order['status'] ?? null,
            'examination_at' => $order['examination_at'] ?? null,
            'lost_reason'    => $order['lost_reason'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        foreach (['created_ref', 'examination_ref', 'closed_ref'] as $key) {
            if (isset($order[$key]) && $order[$key] !== $ref) {
                $projected[$key] = $order[$key];
            }
        }

        if (isset($order['value']) && (float) $order['value'] > 0) {
            $projected['value'] = (float) $order['value'];
        }

        return $projected;
    }

    /**
     * A dated row (timeline entry or last contact). Direction only carries meaning on
     * mail, so it is dropped for other timeline types.
     *
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    protected function projectDatedEntry(array $entry, bool $withType = true): array
    {
        $type = $entry['type'] ?? null;

        return array_filter([
            'ref'       => $entry['ref'] ?? null,
            'date'      => $entry['date'] ?? null,
            'type'      => $withType ? $type : null,
            'direction' => ! $withType || $type === 'email' ? ($entry['direction'] ?? null) : null,
            'text'      => $entry['text'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /*
    |--------------------------------------------------------------------------
    | Sources and primitives
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, mixed>|null
     */
    protected function leadSource(Lead $lead): ?array
    {
        return $this->source(
            'lead',
            $lead->id,
            'Lead: '.$lead->name,
            $lead->closed_at ?? $lead->updated_at ?? $lead->created_at,
            $lead->closed_at
                ? 'Afgesloten'
                : ($lead->stage?->is_lost ? 'Verloren' : ($lead->stage?->is_won ? 'Gewonnen' : 'Laatst gewijzigd')),
            null,
            [
                'updated_at'  => $this->date($lead->updated_at),
                'description' => $lead->description,
                'stage'       => $lead->stage?->name,
                'lost_reason' => $lead->stage?->is_lost ? $lead->lost_reason?->label() : null,
            ],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function salesLeadSource(SalesLead $salesLead): ?array
    {
        return $this->source(
            'sales',
            $salesLead->id,
            'Sales: '.($salesLead->name ?: "#{$salesLead->id}"),
            $salesLead->closed_at ?? $salesLead->created_at,
            $salesLead->closed_at ? 'Afgesloten' : 'Aangemaakt',
            null,
            [
                'updated_at'  => $this->date($salesLead->updated_at),
                'description' => $salesLead->description,
                'stage'       => $salesLead->stage?->name,
                'closed_at'   => $this->date($salesLead->closed_at),
            ],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function emailSource(Email $email, ?string $subject, ?string $body): ?array
    {
        return $this->source(
            'email',
            $email->id,
            'E-mail: '.($subject ?: 'Zonder onderwerp'),
            $email->created_at,
            'Ontvangen/verzonden',
            null,
            [
                'updated_at' => $this->date($email->updated_at),
                'subject'    => $email->subject,
                'from'       => $email->from,
                'body'       => $body,
            ],
        );
    }

    protected function compactText(?string $value, int $limit): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?: $text;

        return Str::limit(trim($text), $limit, '');
    }

    protected function enumValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return $value !== null ? (string) $value : null;
    }

    /**
     * @param  array<string, mixed>  $fingerprint
     * @return array{
     *     ref: string,
     *     type: string,
     *     entity_id: int,
     *     label: string,
     *     date: string,
     *     date_label: string,
     *     version: string
     * }|null
     */
    protected function source(
        string $type,
        int $entityId,
        string $label,
        ?CarbonInterface $date,
        string $dateLabel,
        ?string $event = null,
        array $fingerprint = [],
    ): ?array {
        if (! $date) {
            return null;
        }

        return [
            'ref'        => implode(':', array_filter([$type, $entityId, $event], fn ($part) => $part !== null && $part !== '')),
            'type'       => $type,
            'entity_id'  => $entityId,
            'label'      => $label,
            'date'       => $date->toIso8601String(),
            'date_label' => $dateLabel,
            'version'    => hash('sha256', json_encode([
                'date'        => $date->toIso8601String(),
                'label'       => $label,
                'fingerprint' => $fingerprint,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array<string, mixed>>
     */
    protected function sourceCatalog(array $context): array
    {
        $sources = [];
        $this->collectSources($context, $sources);

        return array_values($sources);
    }

    protected function date(?CarbonInterface $date): ?string
    {
        return $date?->toIso8601String();
    }

    protected function dateOnly(?CarbonInterface $date): ?string
    {
        return $date?->toDateString();
    }

    /**
     * Activities and e-mails hang off a lead, a sales lead or an order; match any of them.
     */
    private function scopeToRelatedRecords(mixed $query, AiContextScope $scope): void
    {
        $salesLeadIds = $scope->salesLeadIds();
        $orderIds = $scope->orderIds();

        $query->whereRaw('1 = 0');

        if ($scope->leadIds->isNotEmpty()) {
            $query->orWhereIn('lead_id', $scope->leadIds);
        }

        if ($salesLeadIds->isNotEmpty()) {
            $query->orWhereIn('sales_lead_id', $salesLeadIds);
        }

        if ($orderIds->isNotEmpty()) {
            $query->orWhereIn('order_id', $orderIds);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $sources
     */
    private function collectSources(mixed $value, array &$sources): void
    {
        if (! is_array($value)) {
            return;
        }

        if (isset($value['_source']['ref'], $value['_source']['date'])) {
            $sources[$value['_source']['ref']] = $value['_source'];
        }

        foreach ($value['_sources'] ?? [] as $source) {
            if (isset($source['ref'], $source['date'])) {
                $sources[$source['ref']] = $source;
            }
        }

        foreach ($value as $key => $child) {
            if (! in_array($key, ['_source', '_sources'], true)) {
                $this->collectSources($child, $sources);
            }
        }
    }
}
