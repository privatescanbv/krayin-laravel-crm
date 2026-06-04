<?php

namespace App\Models;

use App\Enums\AfbDispatchStatus;
use App\Enums\AppointmentTimeFilter;
use App\Enums\Departments;
use App\Enums\LostReason;
use App\Enums\OrderItemStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderPurchaseStatus;
use App\Enums\PaymentType;
use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStage;
use App\Traits\HasAuditTrail;
use BackedEnum;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;

/**
 * @mixin IdeHelperOrder
 */
class Order extends Model
{
    use HasAuditTrail, HasFactory;

    protected $table = 'orders';

    protected $fillable = [
        'external_id',
        'order_number',
        'invoice_number',
        'is_business',
        'organization_id',
        'title',
        'total_price',
        'pipeline_stage_id',
        'lost_reason',
        'closed_at',
        'first_examination_at',
        'first_examination_time',
        'sales_lead_id',
        'user_id',
        'clinic_coordinator_user_id',
        'combine_order',
        'confirmation_letter_content',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'total_price'                      => 'decimal:2',
        'pipeline_stage_id'                => 'integer',
        'first_examination_at'             => 'date',
        'closed_at'                        => 'date',
        'lost_reason'                      => LostReason::class,
        'sales_lead_id'                    => 'integer',
        'user_id'                          => 'integer',
        'clinic_coordinator_user_id'       => 'integer',
        'combine_order'                    => 'boolean',
        'is_business'                      => 'boolean',
        'organization_id'                  => 'integer',
        'created_by'                       => 'integer',
        'updated_by'                       => 'integer',
    ];

    public static function rules(): array
    {
        return [
            'title'                         => 'required|string|max:255',
            'total_price'                   => 'required|numeric|min:0',
            'pipeline_stage_id'             => 'nullable|integer|exists:lead_pipeline_stages,id',
            'first_examination_at'          => 'nullable|date',
            'sales_lead_id'                 => 'required|integer|exists:salesleads,id',
            'user_id'                       => 'nullable|integer|exists:users,id',
            'clinic_coordinator_user_id'    => 'nullable|integer|exists:users,id',
            'combine_order'                 => 'boolean',
            'invoice_number'                => 'nullable|string|max:255',
            'is_business'                   => 'boolean',
            'organization_id'               => 'nullable|integer|exists:organizations,id',
        ];
    }

    /**
     * Get the first order pipeline stage ID for the given department.
     */
    public static function firstOrderStageId(?Department $department): int
    {
        if ($department->name === Departments::HERNIA->value) {
            return PipelineDefaultKeys::PIPELINE_HERNIA_ORDERS_ID->value === 7
                ? PipelineStage::ORDER_VOORBEREIDEN_HERNIA->id()
                : PipelineStage::ORDER_CONFIRM->id();
        }

        return PipelineStage::ORDER_CONFIRM->id();
    }

    /**
     * Order-pipeline "Bevestigd" stage for the lead's department (Privatescan vs Hernia).
     */
    public static function orderSendByDepartmentStageId(?Department $department): int
    {
        if ($department !== null && $department->name === Departments::HERNIA->value) {
            return PipelineStage::ORDER_BEVESTIGD_HERNIA->id();
        }

        return PipelineStage::ORDER_BEVESTIGD->id();
    }

    /**
     * Order-pipeline "Verloren" stage for the lead's department (Privatescan vs Hernia).
     */
    public static function lostOrderStageId(?Department $department): int
    {
        if ($department !== null && $department->name === Departments::HERNIA->value) {
            return PipelineStage::ORDER_VERLOREN_HERNIA->id();
        }

        return PipelineStage::ORDER_VERLOREN->id();
    }

    public function setFirstExaminationAtAttribute(mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['first_examination_at'] = null;

            return;
        }
        $this->attributes['first_examination_at'] = Carbon::parse($value)->format('Y-m-d');
    }

    /**
     * Combine the stored date with the stored time override into a full Carbon datetime.
     * Returns null when no override date is set.
     */
    public function firstExaminationCarbon(): ?Carbon
    {
        $computed = $this->earliestScheduledResourceSlotStart();

        $date = $this->first_examination_at?->format('Y-m-d') ?? $computed?->format('Y-m-d');

        if ($date === null) {
            return null;
        }

        $time = filled($this->first_examination_time) ? $this->first_examination_time : ($computed?->format('H:i') ?? '00:00');

        return Carbon::parse("$date $time");
    }

    public function hasFirstExaminationOverride(): bool
    {
        return $this->first_examination_at !== null || $this->first_examination_time !== null;
    }

    /**
     * Get the lost reason label for this order.
     */
    public function getLostReasonLabelAttribute(): ?string
    {
        return $this->lost_reason?->label() ?? null;
    }

    /**
     * Normalize lost reason assignment to allow empty strings and enums.
     */
    public function setLostReasonAttribute($value): void
    {
        if ($value === '' || $value === null) {
            $this->attributes['lost_reason'] = null;

            return;
        }

        if ($value instanceof BackedEnum) {
            $this->attributes['lost_reason'] = $value->value;

            return;
        }

        $this->attributes['lost_reason'] = $value;
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Order items that are not marked LOST (removed/cancelled lines).
     */
    public function activeOrderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class)->notLost();
    }

    /**
     * Order items for display in UI and emails (excludes LOST). Uses eager-loaded orderItems when present.
     *
     * @return Collection<int, OrderItem>
     */
    public function displayableOrderItems(?int $personId = null): Collection
    {
        $items = $this->relationLoaded('orderItems')
            ? $this->orderItems->filter(fn (OrderItem $item) => $item->status !== OrderItemStatus::LOST)
            : $this->activeOrderItems()->get();

        if ($personId !== null) {
            $items = $items->where('person_id', $personId);
        }

        return $items->values();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Earliest planned resource slot (`resource_orderitem.from`) on this order.
     * Order items with status LOST are ignored.
     */
    public function earliestScheduledResourceSlotStart(): ?Carbon
    {
        $this->loadMissing('orderItems.resourceOrderItems');

        $fromTimes = $this->orderItems
            ->filter(fn (OrderItem $item) => $item->status !== OrderItemStatus::LOST)
            ->flatMap(fn (OrderItem $item) => $item->resourceOrderItems)
            ->pluck('from')
            ->filter()
            ->map(fn ($from) => Carbon::parse($from));

        if ($fromTimes->isEmpty()) {
            return null;
        }

        return $fromTimes->sortBy(fn (Carbon $c) => $c->getTimestamp())->first();
    }

    /**
     * Returns all dates on which this order appears in the clinic guide, each with its display time.
     *
     * - Entry 0 (day 1): date + time from firstExaminationCarbon() — honours the stored date/time override.
     * - Entry 1+ (later days): each unique date from non-LOST resource_orderitem slots that falls on a
     *   different calendar day than day 1. Display time = earliest slot start on that day.
     *
     * Sorted ascending by date. Used by ClinicGuideController and matchesClinicGuideDate().
     *
     * @return Collection<int, array{date: Carbon, at: Carbon}>
     */
    public function clinicGuideDays(): Collection
    {
        $this->loadMissing('orderItems.resourceOrderItems');

        $firstAt = $this->firstExaminationCarbon();
        $days = collect();

        if ($firstAt !== null) {
            $days->push(['date' => $firstAt->copy()->startOfDay(), 'at' => $firstAt]);
        }

        $slotsByDate = $this->orderItems
            ->filter(fn (OrderItem $item) => $item->status !== OrderItemStatus::LOST)
            ->flatMap(fn (OrderItem $item) => $item->resourceOrderItems)
            ->pluck('from')
            ->filter()
            ->map(fn ($from) => Carbon::parse($from))
            ->groupBy(fn (Carbon $c) => $c->toDateString());

        foreach ($slotsByDate as $dateStr => $slots) {
            $slotDate = Carbon::parse($dateStr)->startOfDay();

            if ($firstAt !== null && $slotDate->isSameDay($firstAt)) {
                continue;
            }

            $earliest = $slots->sortBy(fn (Carbon $c) => $c->getTimestamp())->first();
            $days->push(['date' => $slotDate, 'at' => $earliest]);
        }

        return $days->sortBy(fn (array $entry) => $entry['date']->getTimestamp())->values();
    }

    /**
     * Returns the display datetime for this order on the given date, or null if not planned on that date.
     */
    public function clinicGuideEntryForDate(Carbon $date): ?Carbon
    {
        return $this->clinicGuideDays()
            ->filter(fn (array $entry) => $entry['date']->isSameDay($date))
            ->pluck('at')
            ->first();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function salesLead(): BelongsTo
    {
        return $this->belongsTo(SalesLead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clinicCoordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'clinic_coordinator_user_id');
    }

    public function lead(): ?Lead
    {
        return $this->salesLead?->lead;
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class, 'pipeline_stage_id');
    }

    public function orderChecks(): HasMany
    {
        return $this->hasMany(OrderCheck::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function afbPersonDocuments(): HasMany
    {
        return $this->hasMany(AfbPersonDocument::class);
    }

    public function personConfirmations(): HasMany
    {
        return $this->hasMany(OrderPersonConfirmation::class);
    }

    /**
     * Anamnesis records linked directly to this order (via anamnesis.order_id).
     */
    public function anamnesisRecords(): HasMany
    {
        return $this->hasMany(Anamnesis::class, 'order_id');
    }

    /**
     * Get effective anamnesis records for this order, following the inheritance chain:
     * Order → Sales → Lead. Per person, the most specific level wins.
     */
    public function getAnamnesisAttribute()
    {
        try {
            $salesLeadId = $this->sales_lead_id;
            $leadId = $this->salesLead?->lead_id;

            return Anamnesis::query()
                ->where('order_id', $this->id)
                ->when(
                    $salesLeadId,
                    fn ($query) => $query->orWhere('sales_id', $salesLeadId)
                )
                ->when(
                    $leadId,
                    fn ($query) => $query->orWhere('lead_id', $leadId)
                )
                ->get();
        } catch (Exception $e) {
            Log::warning('Could not load anamnesis for order', ['order_id' => $this->id, 'error' => $e->getMessage()]);

            return collect();
        }
    }

    /**
     * Resolve the effective anamnesis per person for this order.
     * Returns a collection keyed by person_id, with each value containing
     * the anamnesis and its source level ('order', 'sales', or 'lead').
     */
    public function resolveAnamnesisPerPerson(): Collection
    {
        $allAnamneses = $this->anamnesis;
        $persons = $this->salesLead?->persons ?? collect();

        return $persons->mapWithKeys(function ($person) use ($allAnamneses) {
            $personAnamneses = $allAnamneses->where('person_id', $person->id);

            $effective = $personAnamneses->firstWhere('order_id', $this->id)
                ?? $personAnamneses->firstWhere(fn ($a) => $a->sales_id && ! $a->order_id)
                ?? $personAnamneses->firstWhere(fn ($a) => $a->lead_id && ! $a->sales_id && ! $a->order_id);

            return [$person->id => [
                'person'       => $person,
                'anamnesis'    => $effective,
                'source'       => $effective?->source_level ?? 'lead',
                'has_override' => $effective?->order_id === $this->id,
            ]];
        });
    }

    /**
     * Check whether every person on the sales lead has a confirmed (email sent) record.
     */
    public function allPersonsConfirmed(): bool
    {
        if (! $this->salesLead) {
            return false;
        }

        $personIds = $this->salesLead->persons()->pluck('persons.id');

        if ($personIds->isEmpty()) {
            return false;
        }

        $confirmedCount = $this->personConfirmations()
            ->whereIn('person_id', $personIds)
            ->whereNotNull('email_sent_at')
            ->count();

        return $confirmedCount >= $personIds->count();
    }

    /**
     * Count how many persons have been confirmed vs total on this order.
     *
     * @return array{confirmed: int, total: int}
     */
    public function confirmationProgress(): array
    {
        if (! $this->salesLead) {
            return ['confirmed' => 0, 'total' => 0];
        }

        $personIds = $this->salesLead->persons()->pluck('persons.id');

        $confirmed = $this->personConfirmations()
            ->whereIn('person_id', $personIds)
            ->whereNotNull('email_sent_at')
            ->count();

        return ['confirmed' => $confirmed, 'total' => $personIds->count()];
    }

    /**
     * Returns the latest successful AfbPersonDocument per (person_id, clinic_department_id) combination.
     * Use this as the single source of truth for AFB display logic across views.
     * Requires 'afbPersonDocuments.dispatch' to be eager-loaded.
     *
     * @return Collection<int, AfbPersonDocument>
     */
    public function latestSuccessfulAfbDocuments(): Collection
    {
        return $this->afbPersonDocuments
            ->filter(fn (AfbPersonDocument $doc) => $doc->dispatch?->status === AfbDispatchStatus::SUCCESS)
            ->sortByDesc('sent_at')
            ->unique(fn (AfbPersonDocument $doc) => $doc->person_id.'_'.$doc->dispatch?->clinic_department_id);
    }

    /**
     * Netto betaald bedrag: aanbetalingen + kliniekbetalingen minus terugbetalingen.
     */
    public function netPaidAmount(): float
    {
        return round(
            (float) $this->payments->sum(fn ($p) => $p->type === PaymentType::REFUND
                ? -(float) $p->amount
                : (float) $p->amount
            ),
            2
        );
    }

    /**
     * Herbereken total_price op basis van niet-LOST orderregels en sla op zonder observers.
     */
    public function recalculateTotalPrice(): void
    {
        $this->total_price = $this->orderItems()->notLost()->sum('total_price');
        $this->saveQuietly();
    }

    /**
     * Betaalstatus klant: vergelijk netto betaald bedrag met total_price.
     */
    public function paymentStatus(): OrderPaymentStatus
    {
        return OrderPaymentStatus::forOrder(
            round((float) $this->total_price, 2),
            $this->netPaidAmount(),
        );
    }

    /**
     * Som van alle hoofdinkoopprijzen van de order items.
     * Gebruikt resolved purchase price (order item > product > partner product).
     */
    public function totalPurchasePrice(): float
    {
        $this->loadMissing('orderItems.product.partnerProducts.purchasePrice');

        return round(
            (float) $this->orderItems
                ->reject(fn ($item) => $item->status === OrderItemStatus::LOST)
                ->sum(fn ($item) => (float) $item->resolvedPurchasePrice()->purchase_price),
            2
        );
    }

    /**
     * Gecombineerde afletteren-status over alle order items.
     */
    public function purchaseStatus(): OrderPurchaseStatus
    {
        $purchaseTotal = $this->totalPurchasePrice();
        $invoiceTotal = round(
            (float) $this->orderItems
                ->reject(fn ($item) => $item->status === OrderItemStatus::LOST)
                ->sum(fn ($item) => (float) ($item->invoicePurchasePrice?->purchase_price ?? 0)),
            2
        );

        return OrderPurchaseStatus::forOrder($purchaseTotal, $invoiceTotal);
    }

    public function isWon(): bool
    {
        return (bool) $this->stage?->is_won;
    }

    public function isLost(): bool
    {
        return (bool) $this->stage?->is_lost;
    }

    /**
     * Orders without a pipeline stage, or in a stage that is neither won nor lost.
     */
    public function scopeInOpenStage(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('pipeline_stage_id')
                ->orWhereHas('stage', fn (Builder $s) => $s->where('is_won', false)->where('is_lost', false));
        });
    }

    public function isHerniapoli(): bool
    {
        return (int) $this->stage?->lead_pipeline_id === PipelineDefaultKeys::PIPELINE_HERNIA_ORDERS_ID->value;
    }

    public function getPipelineDepartment(): Department
    {
        $name = $this->isHerniapoli()
            ? Departments::HERNIA->value
            : Departments::PRIVATESCAN->value;

        return Department::query()->where('name', $name)->firstOrFail();
    }

    public function getNameAttribute(): string
    {
        return $this->order_number.' '.($this->salesLead?->name ?? '');
    }

    public function getOpenActivitiesCountAttribute(): int
    {
        return $this->activities()->where('is_done', 0)->count();
    }

    /**
     * Scope orders that belong to a given patient (Person), via salesLead -> persons.
     */
    public function scopeForPerson(Builder $query, Person $person): Builder
    {
        return $query->whereHas('salesLead.persons', function ($q) use ($person) {
            $q->whereKey($person->id);
        });
    }

    /**
     * Scope orders that are eligible to be shown as appointments in the patient portal.
     * Includes orders with a resolved first examination via {@see firstExaminationCarbon()}:
     * stored override date and/or a scheduled resource slot on a non-lost order item.
     */
    public function scopeAppointmentEligible(Builder $query): Builder
    {
        $allowedStageIds = array_map(
            fn (PipelineStage $s) => $s->id(),
            PipelineStage::getOrderStagesAfterPlanned(),
        );

        return $query
            ->whereIn('pipeline_stage_id', $allowedStageIds)
            ->where(function (Builder $q) {
                $q->whereNotNull('first_examination_at')
                    ->orWhereHas('orderItems', function (Builder $orderItems) {
                        $orderItems
                            ->where('status', '!=', OrderItemStatus::LOST->value)
                            ->whereHas('resourceOrderItems', fn (Builder $roi) => $roi->whereNotNull('from'));
                    });
            });
    }

    /**
     * Whether this order's resolved first-examination instant matches the portal time filter.
     * Prefer this over SQL on {@see $first_examination_at} alone (slots without override are excluded there).
     */
    public function matchesAppointmentTimeFilter(?AppointmentTimeFilter $filter, Carbon $now): bool
    {
        $at = $this->firstExaminationCarbon();
        if (! $at) {
            return false;
        }

        if ($filter === null) {
            return true;
        }

        return match ($filter) {
            AppointmentTimeFilter::FUTURE => $at->gte($now),
            AppointmentTimeFilter::PAST   => $at->lt($now),
        };
    }

    public function resolveAddress(?Person $person = null): ?Address
    {
        if ($this->is_business) {
            if ($this->organization?->address) {
                return $this->organization->address;
            }

            $org = $this->salesLead?->lead?->organization;
            if ($org?->address) {
                return $org->address;
            }
        }

        if ($this->combine_order) {
            return $this->salesLead?->getContactPersonOrFirstPerson()?->address;
        }

        return $person?->address ?? null;
    }

    /**
     * @return string attention name
     */
    public function resolveAttentionName(): string
    {
        if ($this->is_business) {
            return $this->organization?->name ?? '[Organisatie heeft geen naam]';
        }

        return $this->salesLead?->getContactPersonOrFirstPerson()?->namePatient ?? '';
    }
}
