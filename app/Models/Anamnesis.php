<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Anamnesis extends Model
{
    use HasFactory;

    protected $table = 'anamnesis';

    protected $primaryKey = 'id';
    public $incrementing = false;
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
        'lengte',
        'gewicht',
        'metalen',
        'opm_metalen_c',
        'medicijnen',
        'opm_medicijnen_c',
        'glaucoom',
        'opm_glaucoom_c',
        'claustrofobie',
        'dormicum',
        'hart_operatie_c',
        'opm_hart_operatie_c',
        'implantaat_c',
        'opm_implantaat_c',
        'operaties_c',
        'opm_operaties_c',
        'opmerking',
        'hart_erfelijk',
        'opm_erf_hart_c',
        'vaat_erfelijk',
        'opm_erf_vaat_c',
        'tumoren_erfelijk',
        'opm_erf_tumor_c',
        'allergie_c',
        'opm_allergie_c',
        'rugklachten',
        'opm_rugklachten_c',
        'heart_problems',
        'opm_hartklachten_c',
        'smoking',
        'opm_roken_c',
        'diabetes',
        'opm_diabetes_c',
        'spijsverteringsklachten',
        'opm_spijsvertering_c',
        'risico_hartinfarct',
        'actief',
        'opm_advies_c',
        'lead_id',
        'user_id',
    ];

    protected $casts = [
        'deleted' => 'boolean',
        'metalen' => 'boolean',
        'medicijnen' => 'boolean',
        'glaucoom' => 'boolean',
        'claustrofobie' => 'boolean',
        'dormicum' => 'boolean',
        'hart_operatie_c' => 'boolean',
        'implantaat_c' => 'boolean',
        'operaties_c' => 'boolean',
        'hart_erfelijk' => 'boolean',
        'vaat_erfelijk' => 'boolean',
        'tumoren_erfelijk' => 'boolean',
        'allergie_c' => 'boolean',
        'rugklachten' => 'boolean',
        'heart_problems' => 'boolean',
        'smoking' => 'boolean',
        'diabetes' => 'boolean',
        'spijsverteringsklachten' => 'boolean',
        'actief' => 'boolean',
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
}
