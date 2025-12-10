<?php

namespace Webkul\Lead\Repositories;

use App\Enums\PipelineType;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Webkul\Core\Eloquent\Repository;
use Webkul\Lead\Contracts\Pipeline;

class PipelineRepository extends Repository
{
    /**
     * Create a new repository instance.
     *
     * @return void
     */
    public function __construct(
        protected StageRepository $stageRepository,
        Container $container
    ) {
        parent::__construct($container);
    }

    /**
     * Specify model class name.
     *
     * @return mixed
     */
    public function model()
    {
        return \Webkul\Lead\Models\Pipeline::class;
    }

    /**
     * Get all lead pipelines.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLeadPipelines()
    {
        return $this->model->leadPipelines()->get();
    }

    /**
     * Get all workflow pipelines.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getWorkflowPipelines()
    {
        return $this->model->workflowPipelines()->get();
    }

    /**
     * Get pipelines by type.
     *
     * @param  \App\Enums\PipelineType  $type
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPipelinesByType(PipelineType $type)
    {
        return $this->model->where('type', $type)->get();
    }

    /**
     * Create pipeline.
     *
     * @return Pipeline
     */
    public function create(array $data)
    {
        if ($data['is_default'] ?? false) {
            $this->model->query()->where('type', $data['type'] ?? PipelineType::LEAD)->update(['is_default' => 0]);
        }

        $pipeline = $this->model->create($data);

        foreach ($data['stages'] as $stageData) {
            $this->stageRepository->create(array_merge([
                'lead_pipeline_id' => $pipeline->id,
            ], $stageData));
        }

        return $pipeline;
    }

    /**
     * Update pipeline.
     *
     * @param  int  $id
     * @param  string  $attribute
     * @return Pipeline
     */
    public function update(array $data, $id, $attribute = 'id')
    {
        $pipeline = $this->find($id);

        if ($data['is_default'] ?? false) {
            $this->model->query()->where('id', '<>', $id)->where('type', $data['type'] ?? $pipeline->type)->update(['is_default' => 0]);
        }

        $pipeline->update($data);

        $previousStageIds = $pipeline->stages()->pluck('id');

        foreach ($data['stages'] as $stageId => $stageData) {
            if (Str::contains($stageId, 'stage_')) {
                $this->stageRepository->create(array_merge([
                    'lead_pipeline_id' => $pipeline->id,
                ], $stageData));
            } else {
                if (is_numeric($index = $previousStageIds->search($stageId))) {
                    $previousStageIds->forget($index);
                }

                $this->stageRepository->update($stageData, $stageId);
            }
        }

        foreach ($previousStageIds as $stageId) {
            $pipeline->leads()->where('lead_pipeline_stage_id', $stageId)->update([
                'lead_pipeline_stage_id' => $pipeline->stages()->first()->id,
            ]);

            $this->stageRepository->delete($stageId);
        }

        return $pipeline;
    }

    /**
     * Return the default pipeline.
     *
     * @return Pipeline
     */
    public function getDefaultPipeline(PipelineType $type): Pipeline
    {
        $pipeline = $this->findOneWhere([
            ['is_default', '=', 1],
            ['type', '=', $type->value]
        ]);
        if (is_null( $pipeline)) {
            throw new Exception("Could not find pipeline by type {$type->value}");
        }

        return $pipeline;
    }
}
