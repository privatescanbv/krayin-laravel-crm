<?php

namespace App\Models;

use App\Enums\AppointmentTimeFilter;
use App\Enums\Departments;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderPurchaseStatus;
use App\Enums\PaymentType;
use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStage;
use App\Helpers\ValueNormalizer;
use App\Traits\HasAuditTrail;
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
        'order_number',
        'title',
        'total_price',
        'pipeline_stage_id',
        'first_examination_at',
        'sales_lead_id',
        'user_id',
        'combine_order',
        'confirmation_letter_content',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'total_price'          => 'decimal:2',
        'pipeline_stage_id'    => 'integer',
        'first_examination_at' => 'datetime',
        'sales_lead_id'        => 'integer',
        'user_id'              => 'integer',
        'combine_order'        => 'boolean',
        'created_by'           => 'integer',
        'updated_by'           => 'integer',
    ];

    public static function rules(): array
    {
        return [
            'title'                => 'required|string|max:255',
            'total_price'          => 'required|numeric|min:0',
            'pipeline_stage_id'    => 'nullable|integer|exists:lead_pipeline_stages,id',
            'first_examination_at' => 'nullable|date',
            'sales_lead_id'        => 'required|integer|exists:salesleads,id',
            'user_id'              => 'nullable|integer|exists:users,id',
            'combine_order'        => 'boolean',
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

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
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
