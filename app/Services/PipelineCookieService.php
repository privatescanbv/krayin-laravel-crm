<?php

namespace App\Services;

use App\Enums\PipelineType;
use Exception;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Webkul\Lead\Contracts\Pipeline;
use Webkul\Lead\Repositories\PipelineRepository;

class PipelineCookieService
{
    const COOKIE_DURATION = 60 * 24 * 30; // 30 days in minutes

    public function __construct(
        protected PipelineRepository $pipelineRepository
    ) {}

    /**
     * Get the last selected pipeline ID from cookie
     */
    public function getLastSelectedPipelineId(PipelineType $type): ?int
    {
        try {
            $pipelineId = request()->cookie($this->getCookieName($type));

            if (! $pipelineId || ! is_numeric($pipelineId)) {
                return null;
            }

            // Validate that the pipeline still exists
            $pipeline = $this->pipelineRepository->find((int) $pipelineId);

            return $pipeline ? (int) $pipelineId : null;
        } catch (Exception $e) {
            // Log error but don't break the application
            Log::warning('Error in getLastSelectedPipelineId', [
                'error'      => $e->getMessage(),
                'pipelineId' => $pipelineId ?? 'null',
            ]);

            return null;
        }
    }

    /**
     * Set the last selected pipeline ID in cookie
     */
    public function setLastSelectedPipelineId(int $pipelineId, PipelineType $type): void
    {
        try {
            // Validate that the pipeline exists before setting cookie
            $pipeline = $this->pipelineRepository->find($pipelineId);

            if (! $pipeline) {
                return;
            }

            Cookie::queue(
                Cookie::make(
                    $this->getCookieName($type),
                    $pipelineId,
                    self::COOKIE_DURATION,
                    '/',
                    null,
                    false, // secure (set to true for HTTPS)
                    true   // httpOnly
                )
            );
        } catch (Exception $e) {
            // Log error but don't break the application
            Log::warning('Error in setLastSelectedPipelineId', [
                'error'      => $e->getMessage(),
                'pipelineId' => $pipelineId,
            ]);
        }
    }

    public function getPipeline(PipelineType $type, ?int $pipelineIdRequest): Pipeline
    {
        // retrieve from request or cookie
        $pipelineId = $this->getEffectivePipelineId($type, $pipelineIdRequest);
        $pipeline = null;
        if (! is_null($pipelineId)) {
            $pipeline = $this->pipelineRepository->findOrFail($pipelineId);
            if ($pipeline->type != $type) {
                logger()->warning("Invalid pipeline type {$pipeline->type->value}, fallback to default");
                $pipeline = null;
            }
        }
        if (is_null($pipeline)) {
            return $this->pipelineRepository->getDefaultPipeline($type);
        }

        return $pipeline;
    }

    /**
     * Clear the pipeline cookie
     */
    //    public function clearPipelineCookie(): void
    //    {
    //        Cookie::queue(Cookie::forget(self::COOKIE_NAME));
    //    }

    /**
     * Get the effective pipeline ID considering URL parameter and cookie
     *
     * @param  string|int|null  $requestPipelineId  Pipeline ID from request parameter
     */
    private function getEffectivePipelineId(PipelineType $type, $requestPipelineId = null): ?int
    {
        try {
            // Convert string to int if needed
            if ($requestPipelineId !== null) {
                $requestPipelineId = is_numeric($requestPipelineId) ? (int) $requestPipelineId : null;
            }

            // URL parameter takes precedence over cookie
            if ($requestPipelineId) {
                // Set cookie to remember this choice
                $this->setLastSelectedPipelineId($requestPipelineId, $type);

                return $requestPipelineId;
            }

            // Fall back to cookie value
            return $this->getLastSelectedPipelineId($type);
        } catch (Exception $e) {
            // Log error but don't break the application
            Log::warning('Error in getEffectivePipelineId', [
                'error'             => $e->getMessage(),
                'requestPipelineId' => $requestPipelineId,
            ]);

            return null;
        }
    }

    private function getCookieName(PipelineType $type): string
    {
        return match ($type) {
            PipelineType::LEAD       => 'last_selected_pipeline_id_lead',
            PipelineType::BACKOFFICE => 'last_selected_pipeline_id_sales',
            PipelineType::ORDER      => 'last_selected_pipeline_id_order',
            default                  => throw new Exception('Unknown request type '.$type->value),
        };
    }
}
