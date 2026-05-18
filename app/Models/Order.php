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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
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

        $time = $this->first_examination_time ?? $computed?->format('H:i') ?? '00:00';

        return Carbon::parse("$date $time:00");
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
            (float) $this->orderItems->sum(fn ($item) => (float) $item->resolvedPurchasePrice()->purchase_price),
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
            (float) $this->orderItems->sum(fn ($item) => (float) ($item->invoicePurchasePrice?->purchase_price ?? 0)),
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

    public function isHerniapoli(): bool
    {
        return $this->stage?->lead_pipeline_id === PipelineDefaultKeys::PIPELINE_HERNIA_ORDERS_ID->value;
    }

    public function getNameAttribute(): string
    {
        return $this->order_number.' '.$this->salesLead->name;
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
