<?php

namespace Webkul\Lead\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Webkul\Lead\Contracts\Stage as StageContract;

class Stage extends Model implements StageContract
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'lead_pipeline_stages';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'probability',
        'sort_order',
        'lead_pipeline_id',
        'is_won',
        'is_lost',
        'is_default',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_won'     => 'boolean',
        'is_lost'    => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Get the pipeline that owns the pipeline stage.
     */
    public function pipeline()
    {
        return $this->belongsTo(PipelineProxy::modelClass(), 'lead_pipeline_id');
    }

    /**
     * Get the leads.
     */
    public function leads()
    {
        return $this->hasMany(LeadProxy::modelClass(), 'lead_pipeline_stage_id');
    }

    public function isLost(): bool
    {
        return (bool) $this->is_lost;
    }

    public function isWon(): bool
    {
        return (bool) $this->is_won;
    }

    public static function newFactory()
    {
        return \Database\Factories\StageFactory::new();
    }
}
