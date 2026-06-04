<?php

namespace App\Models;

use App\Enums\FormStatus;
use App\Enums\FormType;
use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperAnamnesisGvlForm
 */
class AnamnesisGvlForm extends Model
{
    use HasAuditTrail;

    protected $fillable = [
        'anamnesis_id',
        'gvl_form_id',
        'gvl_form_status',
        'gvl_form_type',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'gvl_form_status' => FormStatus::class,
        'gvl_form_type'   => FormType::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            if (! empty($model->gvl_form_id) && $model->gvl_form_status === null) {
                $model->gvl_form_status = FormStatus::New;
            }
        });
    }

    public function getGvlFormLinkAttribute(): ?string
    {
        if (empty($this->gvl_form_id)) {
            return null;
        }

        return rtrim(config('services.portal.patient.web_url'), '/').'/patient/forms/'.$this->gvl_form_id.'/step/1';
    }

    public function anamnesis(): BelongsTo
    {
        return $this->belongsTo(Anamnesis::class);
    }
}
