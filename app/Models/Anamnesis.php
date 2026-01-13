<?php

namespace App\Models;

use App\Traits\HasAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

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
        'heart_surgery',
        'heart_surgery_notes',
        'implant',
        'implant_notes',
        'surgeries',
        'surgeries_notes',
        'remarks',
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
        'spijsverteringsklachten',
        'digestive_complaints_notes',
        'digestive_problems',
        'digestive_problems_notes',
        'heart_attack_risk',
        'active',
        'advice_notes',
        'lead_id',
        'person_id',
        'gvl_form_link',
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
        'spijsverteringsklachten' => 'boolean',
        'digestive_problems'      => 'boolean',
        'active'                  => 'boolean',
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
            'heart_surgery'              => 'admin::app.anamnesis.fields.heart_surgery',
            'heart_surgery_notes'        => 'admin::app.anamnesis.fields.heart_surgery_notes',
            'implant'                    => 'admin::app.anamnesis.fields.implant',
            'implant_notes'              => 'admin::app.anamnesis.fields.implant_notes',
            'surgeries'                  => 'admin::app.anamnesis.fields.surgeries',
            'surgeries_notes'            => 'admin::app.anamnesis.fields.surgeries_notes',
            'remarks'                    => 'admin::app.anamnesis.fields.remarks',
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
            'spijsverteringsklachten'    => 'admin::app.anamnesis.fields.spijsverteringsklachten',
            'digestive_complaints_notes' => 'admin::app.anamnesis.fields.digestive_complaints_notes',
            'digestive_problems'         => 'admin::app.anamnesis.fields.digestive_problems',
            'digestive_problems_notes'   => 'admin::app.anamnesis.fields.digestive_problems_notes',
            'heart_attack_risk'          => 'admin::app.anamnesis.fields.heart_attack_risk',
            'active'                     => 'admin::app.anamnesis.fields.active',
            'advice_notes'               => 'admin::app.anamnesis.fields.advice_notes',
        ];
    }

    // Relaties
    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function person()
    {
        return $this->belongsTo(Person::class, 'person_id');
    }
}
