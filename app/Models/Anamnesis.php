<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Anamnesis extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $table = 'anamnesis';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'created_at',
        'updated_at',
        'created_at',
        'updated_by',
        'created_by',
        'description',
        'deleted',
        'team_id',
        'team_set_id',
        'assigned_user_id',
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
        'digestive_problems',
        'digestive_problems_notes',
        'heart_attack_risk',
        'active',
        'advice_notes',
        'lead_id',
        'user_id',
    ];

    protected $casts = [
        'deleted'             => 'boolean',
        'metals'              => 'boolean',
        'medications'         => 'boolean',
        'glaucoma'            => 'boolean',
        'claustrophobia'      => 'boolean',
        'dormicum'            => 'boolean',
        'heart_surgery'       => 'boolean',
        'implant'             => 'boolean',
        'surgeries'           => 'boolean',
        'hereditary_heart'    => 'boolean',
        'hereditary_vascular' => 'boolean',
        'hereditary_tumors'   => 'boolean',
        'allergies'           => 'boolean',
        'back_problems'       => 'boolean',
        'heart_problems'      => 'boolean',
        'smoking'             => 'boolean',
        'diabetes'            => 'boolean',
        'digestive_problems'  => 'boolean',
        'active'              => 'boolean',
    ];

    // Relaties
    public function lead()
    {
        return $this->belongsTo(\Webkul\Lead\Models\Lead::class, 'lead_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    // Dutch field name accessors for backward compatibility with views
    public function getLengteAttribute()
    {
        return $this->height;
    }

    public function getGewichtAttribute()
    {
        return $this->weight;
    }

    public function getMetalenAttribute()
    {
        return $this->metals;
    }

    public function getMedicijnenAttribute()
    {
        return $this->medications;
    }

    public function getGlaucoomAttribute()
    {
        return $this->glaucoma;
    }

    public function getClaustrofobieAttribute()
    {
        return $this->claustrophobia;
    }

    public function getHartOperatieCAttribute()
    {
        return $this->heart_surgery;
    }

    public function getImplantaatCAttribute()
    {
        return $this->implant;
    }

    public function getOperatiesCAttribute()
    {
        return $this->surgeries;
    }

    public function getHartErfelijkAttribute()
    {
        return $this->hereditary_heart;
    }

    public function getVaatErfelijkAttribute()
    {
        return $this->hereditary_vascular;
    }

    public function getTumorenErfelijkAttribute()
    {
        return $this->hereditary_tumors;
    }

    public function getAllergieAttribute()
    {
        return $this->allergies;
    }

    public function getAllergieCAttribute()
    {
        return $this->allergies;
    }

    public function getRugklachtenAttribute()
    {
        return $this->back_problems;
    }

    public function getActiefAttribute()
    {
        return $this->active;
    }

    public function getSpijsverteringsklachtenAttribute()
    {
        return $this->digestive_problems;
    }

    public function getOpmerkingAttribute()
    {
        return $this->remarks;
    }

    public function getRisicoHartinfarctAttribute()
    {
        return $this->heart_attack_risk;
    }

    // Notes accessors
    public function getOpmMetalenCAttribute()
    {
        return $this->metals_notes;
    }

    public function getOpmMedicijnenCAttribute()
    {
        return $this->medications_notes;
    }

    public function getOpmGlaucoomCAttribute()
    {
        return $this->glaucoma_notes;
    }

    public function getOpmHartOperatieCAttribute()
    {
        return $this->heart_surgery_notes;
    }

    public function getOpmImplantaatCAttribute()
    {
        return $this->implant_notes;
    }

    public function getOpmOperatiesCAttribute()
    {
        return $this->surgeries_notes;
    }

    public function getOpmErfHartCAttribute()
    {
        return $this->hereditary_heart_notes;
    }

    public function getOpmErfVaatCAttribute()
    {
        return $this->hereditary_vascular_notes;
    }

    public function getOpmErfTumorCAttribute()
    {
        return $this->hereditary_tumors_notes;
    }

    public function getOpmAllergieCAttribute()
    {
        return $this->allergies_notes;
    }

    public function getOpmRugklachtenCAttribute()
    {
        return $this->back_problems_notes;
    }

    public function getOpmRokenCAttribute()
    {
        return $this->smoking_notes;
    }

    public function getOpmDiabetesCAttribute()
    {
        return $this->diabetes_notes;
    }

    public function getOpmSpijsverteringCAttribute()
    {
        return $this->digestive_problems_notes;
    }

    public function getOpmHartklachtenCAttribute()
    {
        return $this->heart_problems_notes;
    }

    public function getOpmAdviesCAttribute()
    {
        return $this->advice_notes;
    }
}
