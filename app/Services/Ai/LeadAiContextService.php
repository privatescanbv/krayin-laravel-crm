<?php

namespace App\Services\Ai;

use App\Enums\ActivityType;
use App\Models\LeadAiFeedback;
use App\Models\Order;
use App\Models\SalesLead;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webkul\Activity\Models\Activity;
use Webkul\Email\Models\Email;
use Webkul\Lead\Models\Lead;

class LeadAiContextService
{
    /** @var list<string> */
    private const CUSTOMER_CONTACT_ACTIVITY_TYPES = [
        ActivityType::CALL->value,
        ActivityType::PATIENT_MESSAGE->value,
    ];

    /** @var list<string> */
    private const TIMELINE_ACTIVITY_TYPES = [
        ActivityType::CALL->value,
        ActivityType::TASK->value,
        ActivityType::NOTE->value,
        ActivityType::PATIENT_MESSAGE->value,
    ];

    /** Domains that mark an email as outgoing staff mail. */
    private const STAFF_EMAIL_DOMAINS = [
        'privatescan.nl',
        'herniapoli.nl',
        'mbsoftware.nl',
    ];

    /**
     * Build the internal context used for citation validation, audit snapshots and
     * feedback inclusion tracking. Call forLlm() before sending anything to the model.
     *
     * @return array<string, mixed>
     */
    public function build(Lead $lead): array
    {
        $lead->loadMissing(['stage', 'source', 'type', 'persons']);

        $personIds = $lead->persons
            ->pluck('id')
            ->when($lead->contact_person_id, fn (Collection $ids) => $ids->push($lead->contact_person_id))
            ->unique()
            ->values();

        $leadIds = Lead::query()
            ->where(function ($query) use ($lead, $personIds) {
                $query->whereKey($lead->id);

                if ($lead->user_id && $personIds->isNotEmpty()) {
                    $query->orWhere(function ($relatedLeads) use ($lead, $personIds) {
                        $relatedLeads
                            ->where('user_id', $lead->user_id)
                            ->where(function ($samePerson) use ($personIds) {
                                $samePerson
                                    ->whereIn('contact_person_id', $personIds)
                                    ->orWhereIn('id', DB::table('lead_persons')
                                        ->select('lead_id')
                                        ->whereIn('person_id', $personIds));
                            });
                    });
                }
            })
            ->latest('created_at')
            ->limit(20)
            ->pluck('id')
            ->push($lead->id)
            ->unique()
            ->values();

        $historicalLeadIds = $leadIds->reject(fn (int $id) => $id === $lead->id)->values();

        $salesLeads = SalesLead::query()
            ->with(['stage', 'orders.stage'])
            ->whereIn('lead_id', $leadIds)
            ->latest('created_at')
            ->limit(20)
            ->get();

        $currentSalesLeads = $salesLeads->where('lead_id', $lead->id)->values();
        $historicalSalesLeads = $salesLeads->where('lead_id', '!=', $lead->id)->values();

        $salesLeadIds = $salesLeads->pluck('id');
        $orderIds = $salesLeads->flatMap->orders->pluck('id');
        $currentOrderIds = $currentSalesLeads->flatMap->orders->pluck('id');

        $activities = $this->fetchActivities($leadIds, $salesLeadIds, $orderIds);
        $emails = $this->fetchEmails($leadIds, $salesLeadIds, $orderIds);

        $feedback = LeadAiFeedback::query()
            ->with('user')
            ->where('lead_id', $lead->id)
            ->where('is_active', true)
            ->oldest('created_at')
            ->get();

        $history = $this->historyEntries($historicalSalesLeads);
        $examinedOrderIds = $historicalSalesLeads
            ->flatMap->orders
            ->filter(fn (Order $order) => $order->first_examination_at !== null)
            ->pluck('id');

        $timeline = $this->buildTimeline(
            $activities,
            $emails,
            $lead->id,
            $currentOrderIds,
            $examinedOrderIds,
        );

        $leadSource = $this->source(
            'lead',
            $lead->id,
            'Lead: '.$lead->name,
            $lead->updated_at ?? $lead->created_at,
            'Laatst gewijzigd',
            null,
            [
                'updated_at'  => $this->date($lead->updated_at),
                'description' => $lead->description,
                'stage'       => $lead->stage?->name,
                'lost_reason' => $lead->stage?->is_lost ? $lead->lost_reason?->label() : null,
            ],
        );

        $currentOrder = $this->currentOrderEntry($currentSalesLeads);
        $feedbackEntries = $feedback->map(fn (LeadAiFeedback $item) => $this->feedbackEntry($item))->values()->all();
        $lastCustomerContact = $this->lastCustomerContact($activities, $emails, $timeline);

        $context = [
            'lead'                   => $this->compactLead($lead, $leadSource),
            'current_order'          => $currentOrder,
            'history'                => $history,
            'timeline'               => $timeline,
            'active_feedback'        => $feedbackEntries,
            'last_customer_contact'  => $lastCustomerContact,
            // Kept for audit / ownership checks that still reason about related leads.
            'historical_lead_ids'    => $historicalLeadIds->all(),
            'sales_ids'              => $salesLeads->pluck('id')->all(),
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
    public function forLlm(array $context): array
    {
        $payload = [
            'lead' => $this->projectLead($context['lead'] ?? []),
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
            fn (array $entry): array => $this->projectTimelineEntry($entry),
            $context['timeline'] ?? [],
        );

        if ($timeline !== []) {
            $payload['timeline'] = $timeline;
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
            $payload['last_customer_contact'] = $this->projectLastContact($context['last_customer_contact']);
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
    public function auditSnapshot(array $context): array
    {
        return [
            'lead_id'             => $context['lead']['id'] ?? null,
            'lead_updated_at'     => $context['lead']['updated_at'] ?? null,
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
     * @param  Collection<int, int>  $leadIds
     * @param  Collection<int, int>  $salesLeadIds
     * @param  Collection<int, int>  $orderIds
     * @return Collection<int, Activity>
     */
    private function fetchActivities(Collection $leadIds, Collection $salesLeadIds, Collection $orderIds): Collection
    {
        $limit = max(1, (int) config('services.llm.lead_summary.activity_limit', 12));

        return Activity::query()
            ->without('user')
            ->where(function ($query) use ($leadIds, $salesLeadIds, $orderIds) {
                $query->whereIn('lead_id', $leadIds);

                if ($salesLeadIds->isNotEmpty()) {
                    $query->orWhereIn('sales_lead_id', $salesLeadIds);
                }

                if ($orderIds->isNotEmpty()) {
                    $query->orWhereIn('order_id', $orderIds);
                }
            })
            ->whereIn('type', self::TIMELINE_ACTIVITY_TYPES)
            ->latest('created_at')
            ->limit($limit * 2)
            ->get();
    }

    /**
     * @param  Collection<int, int>  $leadIds
     * @param  Collection<int, int>  $salesLeadIds
     * @param  Collection<int, int>  $orderIds
     * @return Collection<int, Email>
     */
    private function fetchEmails(Collection $leadIds, Collection $salesLeadIds, Collection $orderIds): Collection
    {
        $limit = max(1, (int) config('services.llm.lead_summary.email_limit', 6));

        return Email::query()
            ->where(function ($query) use ($leadIds, $salesLeadIds, $orderIds) {
                $query->whereIn('lead_id', $leadIds);

                if ($salesLeadIds->isNotEmpty()) {
                    $query->orWhereIn('sales_lead_id', $salesLeadIds);
                }

                if ($orderIds->isNotEmpty()) {
                    $query->orWhereIn('order_id', $orderIds);
                }
            })
            ->latest('created_at')
            ->limit($limit * 3)
            ->get();
    }

    /**
     * @param  Collection<int, SalesLead>  $historicalSalesLeads
     * @return list<array<string, mixed>>
     */
    private function historyEntries(Collection $historicalSalesLeads): array
    {
        $entries = [];

        foreach ($historicalSalesLeads as $salesLead) {
            if ($salesLead->orders->isEmpty()) {
                $description = $this->compactText($salesLead->description ?: $salesLead->name, 240);
                $source = $this->source(
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

                if ($description === null || $source === null) {
                    continue;
                }

                $entries[] = [
                    'description' => $description,
                    'status'      => $salesLead->stage?->name,
                    'ref'         => $source['ref'],
                    '_source'     => $source,
                ];

                continue;
            }

            foreach ($salesLead->orders as $order) {
                $entries[] = $this->orderEntry($order, $salesLead);
            }
        }

        return array_values(array_filter($entries));
    }

    /**
     * @param  Collection<int, SalesLead>  $currentSalesLeads
     * @return array<string, mixed>|null
     */
    private function currentOrderEntry(Collection $currentSalesLeads): ?array
    {
        $order = $currentSalesLeads
            ->flatMap->orders
            ->sortByDesc(fn (Order $order) => $order->created_at?->getTimestamp() ?? 0)
            ->first();

        if (! $order instanceof Order) {
            return null;
        }

        $salesLead = $currentSalesLeads->firstWhere('id', $order->sales_lead_id);

        return $this->orderEntry($order, $salesLead instanceof SalesLead ? $salesLead : null);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderEntry(Order $order, ?SalesLead $salesLead = null): array
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
    private function compactLead(Lead $lead, ?array $source): array
    {
        $data = [
            'id'          => $lead->id,
            'name'        => $lead->name,
            'description' => $this->compactText($lead->description, 800),
            'stage'       => $lead->stage?->name,
            'source'      => $lead->source?->name,
            'type'        => $lead->type?->name,
            'updated_at'  => $this->date($lead->updated_at),
            'ref'         => $source['ref'] ?? null,
            '_source'     => $source,
        ];

        if ($lead->stage?->is_lost && $lead->lost_reason) {
            $data['lost_reason'] = $lead->lost_reason->label();
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function feedbackEntry(LeadAiFeedback $item): array
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

    /**
     * @param  Collection<int, Activity>  $activities
     * @param  Collection<int, Email>  $emails
     * @param  Collection<int, int>  $currentOrderIds
     * @param  Collection<int, int>  $examinedOrderIds
     * @return list<array<string, mixed>>
     */
    private function buildTimeline(
        Collection $activities,
        Collection $emails,
        int $currentLeadId,
        Collection $currentOrderIds,
        Collection $examinedOrderIds,
    ): array {
        $activityLimit = max(1, (int) config('services.llm.lead_summary.activity_limit', 12));
        $emailLimit = max(1, (int) config('services.llm.lead_summary.email_limit', 6));

        $selectedActivities = $this->selectActivities(
            $activities,
            $currentLeadId,
            $currentOrderIds,
            $examinedOrderIds,
            $activityLimit,
        );

        $activityTexts = $selectedActivities
            ->map(fn (array $entry) => mb_strtolower((string) ($entry['text'] ?? '')))
            ->filter()
            ->values();

        $selectedEmails = $this->selectEmails(
            $emails,
            $currentLeadId,
            $activityTexts,
            $emailLimit,
        );

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
     * @param  Collection<int, int>  $currentOrderIds
     * @param  Collection<int, int>  $examinedOrderIds
     * @return Collection<int, array<string, mixed>>
     */
    private function selectActivities(
        Collection $activities,
        int $currentLeadId,
        Collection $currentOrderIds,
        Collection $examinedOrderIds,
        int $limit,
    ): Collection {
        $open = collect();
        $candidates = collect();

        foreach ($activities as $activity) {
            $type = $this->enumValue($activity->type);

            if ($type === null || ! in_array($type, self::TIMELINE_ACTIVITY_TYPES, true)) {
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
                && ! $currentOrderIds->contains($activity->order_id)
                && $this->looksLikeExecutionNote($activity)
            ) {
                continue;
            }

            // Commercial history already covers earlier eras; keep the timeline focused
            // on the current thread unless the activity is an open task.
            $onCurrentThread = $activity->lead_id === $currentLeadId
                || $currentOrderIds->contains($activity->order_id);

            if (! $onCurrentThread && ! (! $activity->is_done && $type === ActivityType::TASK->value)) {
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
                'score'   => $this->activityScore($activity, $currentLeadId, $currentOrderIds),
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
    private function selectEmails(
        Collection $emails,
        int $currentLeadId,
        Collection $activityTexts,
        int $limit,
    ): Collection {
        $seenFingerprints = [];
        $candidates = collect();

        foreach ($emails as $email) {
            // Prefer the current commercial thread; historical eras are covered by history[].
            if ($email->lead_id !== null && $email->lead_id !== $currentLeadId) {
                continue;
            }

            $subject = $this->compactText($email->subject, 240);
            $body = $this->compactText($email->reply, 600);
            $text = $this->emailText($subject, $body);

            if ($text === null) {
                continue;
            }

            if ($this->isLowValueEmail($subject, $body, $email, $currentLeadId)) {
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

            $source = $this->source(
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
                'score'     => $this->emailScore($email, $currentLeadId, $direction, $subject, $body),
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
    private function lastCustomerContact(Collection $activities, Collection $emails, array $timeline): ?array
    {
        $contacts = collect();

        foreach ($activities as $activity) {
            $type = $this->enumValue($activity->type);

            if ($type === null || ! in_array($type, self::CUSTOMER_CONTACT_ACTIVITY_TYPES, true)) {
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

            $source = $this->source(
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

    private function activityText(Activity $activity): ?string
    {
        $title = $this->compactText($activity->title, 400);
        $comment = $this->compactText($activity->comment, 800);

        if ($title !== null && $comment !== null && $title !== $comment) {
            return $this->compactText($title.'. '.$comment, 900);
        }

        return $comment ?? $title;
    }

    private function emailText(?string $subject, ?string $body): ?string
    {
        if ($subject !== null && $body !== null) {
            return $this->compactText($subject.': '.$body, 700);
        }

        return $body ?? $subject;
    }

    private function isMeaninglessSystemLikeActivity(Activity $activity): bool
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

    private function isLowValueActivity(Activity $activity): bool
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

    private function looksLikeExecutionNote(Activity $activity): bool
    {
        $text = mb_strtolower((string) ($activity->comment ?: $activity->title));

        return (bool) preg_match('/\b(scan uitgevoerd|onderzoek uitgevoerd|mri uitgevoerd|uitslag besproken)\b/u', $text);
    }

    /**
     * @param  Collection<int, int>  $currentOrderIds
     */
    private function activityScore(Activity $activity, int $currentLeadId, Collection $currentOrderIds): int
    {
        $score = 0;
        $at = $activity->completed_at ?? $activity->schedule_from ?? $activity->created_at;

        if ($activity->lead_id === $currentLeadId || $currentOrderIds->contains($activity->order_id)) {
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

    private function emailScore(
        Email $email,
        int $currentLeadId,
        string $direction,
        ?string $subject,
        ?string $body,
    ): int {
        $score = 0;
        $daysAgo = $email->created_at ? max(0, (int) $email->created_at->diffInDays(now())) : 999;

        if ($email->lead_id === $currentLeadId) {
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

    private function isLowValueEmail(?string $subject, ?string $body, Email $email, int $currentLeadId): bool
    {
        if ($this->looksLikeAppointmentConfirmation($subject, $body)) {
            $daysAgo = $email->created_at ? max(0, (int) $email->created_at->diffInDays(now())) : 999;

            // Keep a recent confirmation on the current lead; drop older/historical ones.
            if ($daysAgo > 14 || $email->lead_id !== $currentLeadId) {
                return true;
            }
        }

        $haystack = mb_strtolower(($subject ?? '').' '.($body ?? ''));

        return (bool) preg_match('/\b(automatische statusupdate|geen actie vereist|herinnering: openstaande vraag)\b/u', $haystack)
            && ! preg_match('/\b(vraag|wanneer|planning|akkoord|bezwaar)\b/u', $haystack);
    }

    private function looksLikeAppointmentConfirmation(?string $subject, ?string $body): bool
    {
        $haystack = mb_strtolower(($subject ?? '').' '.($body ?? ''));

        return (bool) preg_match('/\b(bevestiging afspraak|afspraakbevestiging|hierbij bevestigen wij uw afspraak)\b/u', $haystack);
    }

    /**
     * @param  Collection<int, string>  $activityTexts
     */
    private function isRepresentedByActivity(string $emailText, Collection $activityTexts): bool
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

    private function emailDirection(Email $email): string
    {
        $from = strtolower((string) $email->sender_email);

        foreach (self::STAFF_EMAIL_DOMAINS as $domain) {
            if (str_ends_with($from, '@'.$domain)) {
                return 'outgoing';
            }
        }

        return 'incoming';
    }

    /**
     * @param  array<string, mixed>  $lead
     * @return array<string, mixed>
     */
    private function projectLead(array $lead): array
    {
        $projected = array_filter([
            'name'        => $lead['name'] ?? null,
            'stage'       => $lead['stage'] ?? null,
            'description' => $lead['description'] ?? null,
            'source'      => $lead['source'] ?? null,
            'type'        => $lead['type'] ?? null,
            'lost_reason' => $lead['lost_reason'] ?? null,
            'ref'         => $lead['ref'] ?? ($lead['_source']['ref'] ?? null),
        ], fn ($value) => $value !== null && $value !== '');

        return $projected;
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    private function projectOrder(array $order): array
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
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function projectTimelineEntry(array $entry): array
    {
        return array_filter([
            'ref'       => $entry['ref'] ?? null,
            'date'      => $entry['date'] ?? null,
            'type'      => $entry['type'] ?? null,
            'direction' => ($entry['type'] ?? null) === 'email' ? ($entry['direction'] ?? null) : null,
            'text'      => $entry['text'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    private function projectLastContact(array $contact): array
    {
        return array_filter([
            'ref'       => $contact['ref'] ?? null,
            'date'      => $contact['date'] ?? null,
            'direction' => $contact['direction'] ?? null,
            'text'      => $contact['text'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function compactText(?string $value, int $limit): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?: $text;

        return Str::limit(trim($text), $limit, '');
    }

    private function enumValue(mixed $value): ?string
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
    private function source(
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
     * @return list<array{
     *     ref: string,
     *     type: string,
     *     entity_id: int,
     *     label: string,
     *     date: string,
     *     date_label: string,
     *     version: string
     * }>
     */
    private function sourceCatalog(array $context): array
    {
        $sources = [];
        $this->collectSources($context, $sources);

        return array_values($sources);
    }

    /**
     * @param  array<string, array{
     *     ref: string,
     *     type: string,
     *     entity_id: int,
     *     label: string,
     *     date: string,
     *     date_label: string,
     *     version: string
     * }>  $sources
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

    private function date(?CarbonInterface $date): ?string
    {
        return $date?->toIso8601String();
    }

    private function dateOnly(?CarbonInterface $date): ?string
    {
        return $date?->toDateString();
    }
}
