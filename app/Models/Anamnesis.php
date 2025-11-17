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
