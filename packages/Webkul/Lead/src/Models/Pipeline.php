<?php

namespace Webkul\Lead\Models;

use App\Enums\PipelineType;
use Illuminate\Database\Eloquent\Model;
use Webkul\Lead\Contracts\Pipeline as PipelineContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Pipeline extends Model implements PipelineContract
{
    use HasFactory;

    protected $table = 'lead_pipelines';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'rotten_days',
        'is_default',
        'type',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'type' => PipelineType::class,
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'type' => PipelineType::LEAD,
    ];

    /**
     * Get the leads.
     */
    public function leads()
    {
        return $this->hasMany(LeadProxy::modelClass(), 'lead_pipeline_id');
    }

    /**
     * Get the stages that owns the pipeline.
     */
    public function stages()
    {
        return $this->hasMany(StageProxy::modelClass(), 'lead_pipeline_id')->orderBy('sort_order', 'ASC');
    }

    /**
     * Scope a query to only include lead pipelines.
     */
    public function scopeLeadPipelines($query)
    {
        return $query->where('type', PipelineType::LEAD);
    }

    /**
     * Scope a query to only include workflow pipelines.
     */
    public function scopeWorkflowPipelines($query)
    {
        return $query->where('type', PipelineType::WORKFLOW);
    }

    public static function newFactory()
    {
        return \Database\Factories\PipelineFactory::new();
    }
}
