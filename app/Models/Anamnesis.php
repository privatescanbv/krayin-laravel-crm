<?php

namespace App\Models;

use App\Enums\FormStatus;
use App\Enums\FormType;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

/**
 * Anamnesis inheritance chain: Order → Sales → Lead.
 * Each level can optionally override the parent's anamnesis per person.
 */

/**
 * @mixin IdeHelperAnamnesis
 *
 * @method static Builder forOrder(Order $order)
 */
class Anamnesis extends Model
{
    use HasAuditTrail, HasFactory;

    public $incrementing = false;

    protected $table = 'anamnesis';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'description',
        'deleted',
        'team_id',
        'team_set_id',
        'comment_clinic',
        'height',
        'weight',
        'metals',
        'metals_notes',
        'medications',
        'medications_notes',
        'glaucoma',
        'glaucoma_notes',
        'claustrophobia',
        'dormicum',
        'dormicum_notes',
        'heart_surgery',
        'heart_surgery_notes',
        'implant',
        'implant_notes',
        'surgeries',
        'surgeries_notes',
        'hereditary_heart',
        'hereditary_heart_notes',
        'hereditary_vascular',
        'hereditary_vascular_notes',
        'hereditary_tumors',
        'hereditary_tumors_notes',
        'allergies',
        'allergies_notes',
        'back_problems',
        'back_problems_notes',
        'heart_problems',
        'heart_problems_notes',
        'smoking',
        'smoking_notes',
        'diabetes',
        'diabetes_notes',
        'infectious_disease',
        'infectious_disease_notes',
        'spijsverteringsklachten',
        'digestive_complaints_notes',
        'digestive_problems',
        'digestive_problems_notes',
        'heart_attack_risk',
        'active',
        'lead_id',
        'sales_id',
        'order_id',
        'person_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'deleted'                 => 'boolean',
        'height'                  => 'integer',
        'weight'                  => 'integer',
        'metals'                  => 'boolean',
        'medications'             => 'boolean',
        'glaucoma'                => 'boolean',
        'claustrophobia'          => 'boolean',
        'dormicum'                => 'boolean',
        'heart_surgery'           => 'boolean',
        'implant'                 => 'boolean',
        'surgeries'               => 'boolean',
        'hereditary_heart'        => 'boolean',
        'hereditary_vascular'     => 'boolean',
        'hereditary_tumors'       => 'boolean',
        'allergies'               => 'boolean',
        'back_problems'           => 'boolean',
        'heart_problems'          => 'boolean',
        'smoking'                 => 'boolean',
        'diabetes'                => 'boolean',
        'infectious_disease'      => 'boolean',
        'spijsverteringsklachten' => 'boolean',
        'digestive_problems'      => 'boolean',
        'active'                  => 'boolean',
        'lead_id'                 => 'integer',
        'sales_id'                => 'integer',
        'order_id'                => 'integer',
        'person_id'               => 'integer',
        'created_by'              => 'integer',
        'updated_by'              => 'integer',
    ];

    public static function getFieldsToCompare(): array
    {
        return [
            'name'                       => 'admin::app.anamnesis.fields.name',
            'description'                => 'admin::app.anamnesis.fields.description',
            'comment_clinic'             => 'admin::app.anamnesis.fields.comment_clinic',
            'height'                     => 'admin::app.anamnesis.fields.height',
            'weight'                     => 'admin::app.anamnesis.fields.weight',
            'metals'                     => 'admin::app.anamnesis.fields.metals',
            'metals_notes'               => 'admin::app.anamnesis.fields.metals_notes',
            'medications'                => 'admin::app.anamnesis.fields.medications',
            'medications_notes'          => 'admin::app.anamnesis.fields.medications_notes',
            'glaucoma'                   => 'admin::app.anamnesis.fields.glaucoma',
            'glaucoma_notes'             => 'admin::app.anamnesis.fields.glaucoma_notes',
            'claustrophobia'             => 'admin::app.anamnesis.fields.claustrophobia',
            'dormicum'                   => 'admin::app.anamnesis.fields.dormicum',
            'dormicum_notes'             => 'admin::app.anamnesis.fields.dormicum_notes',
            'heart_surgery'              => 'admin::app.anamnesis.fields.heart_surgery',
            'heart_surgery_notes'        => 'admin::app.anamnesis.fields.heart_surgery_notes',
            'implant'                    => 'admin::app.anamnesis.fields.implant',
            'implant_notes'              => 'admin::app.anamnesis.fields.implant_notes',
            'surgeries'                  => 'admin::app.anamnesis.fields.surgeries',
            'surgeries_notes'            => 'admin::app.anamnesis.fields.surgeries_notes',
            'hereditary_heart'           => 'admin::app.anamnesis.fields.hereditary_heart',
            'hereditary_heart_notes'     => 'admin::app.anamnesis.fields.hereditary_heart_notes',
            'hereditary_vascular'        => 'admin::app.anamnesis.fields.hereditary_vascular',
            'hereditary_vascular_notes'  => 'admin::app.anamnesis.fields.hereditary_vascular_notes',
            'hereditary_tumors'          => 'admin::app.anamnesis.fields.hereditary_tumors',
            'hereditary_tumors_notes'    => 'admin::app.anamnesis.fields.hereditary_tumors_notes',
            'allergies'                  => 'admin::app.anamnesis.fields.allergies',
            'allergies_notes'            => 'admin::app.anamnesis.fields.allergies_notes',
            'back_problems'              => 'admin::app.anamnesis.fields.back_problems',
            'back_problems_notes'        => 'admin::app.anamnesis.fields.back_problems_notes',
            'heart_problems'             => 'admin::app.anamnesis.fields.heart_problems',
            'heart_problems_notes'       => 'admin::app.anamnesis.fields.heart_problems_notes',
            'smoking'                    => 'admin::app.anamnesis.fields.smoking',
            'smoking_notes'              => 'admin::app.anamnesis.fields.smoking_notes',
            'diabetes'                   => 'admin::app.anamnesis.fields.diabetes',
            'diabetes_notes'             => 'admin::app.anamnesis.fields.diabetes_notes',
            'infectious_disease'         => 'admin::app.anamnesis.fields.infectious_disease',
            'infectious_disease_notes'   => 'admin::app.anamnesis.fields.infectious_disease_notes',
            'spijsverteringsklachten'    => 'admin::app.anamnesis.fields.spijsverteringsklachten',
            'digestive_complaints_notes' => 'admin::app.anamnesis.fields.digestive_complaints_notes',
            'digestive_problems'         => 'admin::app.anamnesis.fields.digestive_problems',
            'digestive_problems_notes'   => 'admin::app.anamnesis.fields.digestive_problems_notes',
            'heart_attack_risk'          => 'admin::app.anamnesis.fields.heart_attack_risk',
            'active'                     => 'admin::app.anamnesis.fields.active',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            // Enforce: at least one of order_id, sales_id, or lead_id must be filled.
            if (empty($model->order_id) && empty($model->sales_id) && empty($model->lead_id)) {
                throw ValidationException::withMessages([
                    'order_id' => ['order_id, sales_id of lead_id moet gevuld zijn.'],
                    'sales_id' => ['order_id, sales_id of lead_id moet gevuld zijn.'],
                    'lead_id'  => ['order_id, sales_id of lead_id moet gevuld zijn.'],
                ]);
            }

            $salesLeadTable = (new SalesLead)->getTable();
            $leadTable = (new Lead)->getTable();
            $orderTable = (new Order)->getTable();

            Validator::make($model->attributesToArray(), [
                'sales_id' => ['nullable', 'integer', "exists:{$salesLeadTable},id"],
                'lead_id'  => ['nullable', 'integer', "exists:{$leadTable},id"],
                'order_id' => ['nullable', 'integer', "exists:{$orderTable},id"],
            ])->validate();
        });
    }

    /**
     * Filter by the full inheritance chain for an order (order → sales → lead),
     * ordered so order-level records sort first, then sales, then lead.
     */
    public function scopeForOrder(Builder $query, Order $order): void
    {
        $leadId = $order->salesLead?->lead_id;

        $query->where(function (Builder $q) use ($order, $leadId) {
            $q->where('order_id', $order->id)
                ->orWhere('sales_id', $order->sales_lead_id)
                ->when($leadId, fn (Builder $q) => $q->orWhere('lead_id', $leadId));
        })
            ->orderByRaw('CASE WHEN order_id IS NOT NULL THEN 0 WHEN sales_id IS NOT NULL THEN 1 ELSE 2 END')
            ->latest('updated_at');
    }

    // Relaties
    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function sales()
    {
        return $this->belongsTo(SalesLead::class, 'sales_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function person()
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    /**
     * Determine the inheritance source for display purposes.
     * Returns 'order', 'sales', or 'lead'.
     */
    public function getSourceLevelAttribute(): string
    {
        if ($this->order_id) {
            return 'order';
        }

        if ($this->sales_id) {
            return 'sales';
        }

        return 'lead';
    }

    public function getLabelAttribute(): string
    {
        return $this->person?->name ?? $this->order?->title ?? $this->sales?->name ?? $this->lead?->title ?? 'Onbekend';
    }

    public function gvlForms(): HasMany
    {
        return $this->hasMany(AnamnesisGvlForm::class);
    }

    public function getLatestGvlFormAttribute(): ?AnamnesisGvlForm
    {
        if ($this->relationLoaded('gvlForms')) {
            return $this->gvlForms->sortByDesc('id')->first();
        }

        return $this->gvlForms()->latest()->first();
    }

    public function getGvlFormLinkAttribute(): ?string
    {
        return $this->latestGvlForm?->gvl_form_link;
    }

    public function getGvlFormStatusAttribute(): ?FormStatus
    {
        return $this->latestGvlForm?->gvl_form_status;
    }

    public function getGvlFormTypeAttribute(): ?FormType
    {
        return $this->latestGvlForm?->gvl_form_type;
    }
}
