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
use App\Helpers\ValueNormalizer;
use App\Traits\HasAuditTrail;
use BackedEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Webkul\Activity\Models\Activity;
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
        'title',
        'total_price',
        'pipeline_stage_id',
        'lost_reason',
        'closed_at',
        'first_examination_at',
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
        'first_examination_at'             => 'datetime',
        'closed_at'                        => 'date',
        'lost_reason'                      => LostReason::class,
        'sales_lead_id'                    => 'integer',
        'user_id'                          => 'integer',
        'clinic_coordinator_user_id'       => 'integer',
        'combine_order'                    => 'boolean',
        'is_business'                      => 'boolean',
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
     * Get the "order verzonden" stage ID for the given department.
     */
    public static function orderSendByDepartmentStageId(?Department $department): int
    {
        if ($department->name === Departments::HERNIA->value) {
            return PipelineStage::ORDER_BEVESTIGD_HERNIA->id();
        }

        return PipelineStage::ORDER_BEVESTIGD->id();
    }

    /**
     * Normalize empty datetime input to NULL (see ValueNormalizer::nullableDateTime()).
     */
    public function setFirstExaminationAtAttribute(mixed $value): void
    {
        $this->attributes['first_examination_at'] = ValueNormalizer::nullableDateTime($value);
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
     * @return \Illuminate\Support\Collection<int, AfbPersonDocument>
     */
    public function latestSuccessfulAfbDocuments(): \Illuminate\Support\Collection
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
     */
    public function scopeAppointmentEligible(Builder $query): Builder
    {
        return $query
            ->whereNotIn('pipeline_stage_id', PipelineStage::getOrderStagesIdsBeforeConfirmed())
            ->whereNotNull('first_examination_at');
    }

    /**
     * Scope appointment time filtering.
     */
    public function scopeAppointmentTimeFilter(Builder $query, ?AppointmentTimeFilter $filter, Carbon $now): Builder
    {
        return $query
            ->when($filter === AppointmentTimeFilter::FUTURE, fn (Builder $q) => $q->where('first_examination_at', '>=', $now))
            ->when($filter === AppointmentTimeFilter::PAST, fn (Builder $q) => $q->where('first_examination_at', '<', $now));
    }
}
