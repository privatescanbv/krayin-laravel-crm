<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Webkul\Lead\Repositories\PipelineRepository;

class PipelineCookieService
{
    const COOKIE_NAME = 'last_selected_pipeline_id';

    const COOKIE_DURATION = 60 * 24 * 30; // 30 days in minutes

    public function __construct(
        protected PipelineRepository $pipelineRepository
    ) {}

    /**
     * Get the last selected pipeline ID from cookie
     */
    public function getLastSelectedPipelineId(): ?int
    {
        try {
            $pipelineId = request()->cookie(self::COOKIE_NAME);

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
     *
     * @return \Illuminate\Cookie\CookieJar
     */
    public function setLastSelectedPipelineId(int $pipelineId)
    {
        try {
            // Validate that the pipeline exists before setting cookie
            $pipeline = $this->pipelineRepository->find($pipelineId);

            if (! $pipeline) {
                return null;
            }

            return Cookie::queue(
                Cookie::make(
                    self::COOKIE_NAME,
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

            return null;
        }
    }

    /**
     * Get the effective pipeline ID considering URL parameter and cookie
     *
     * @param  string|int|null  $requestPipelineId  Pipeline ID from request parameter
     */
    public function getEffectivePipelineId($requestPipelineId = null): ?int
    {
        try {
            // Convert string to int if needed
            if ($requestPipelineId !== null) {
                $requestPipelineId = is_numeric($requestPipelineId) ? (int) $requestPipelineId : null;
            }

            // URL parameter takes precedence over cookie
            if ($requestPipelineId) {
                // Set cookie to remember this choice
                $this->setLastSelectedPipelineId($requestPipelineId);

                return $requestPipelineId;
            }

            // Fall back to cookie value
            return $this->getLastSelectedPipelineId();
        } catch (Exception $e) {
            // Log error but don't break the application
            Log::warning('Error in getEffectivePipelineId', [
                'error'             => $e->getMessage(),
                'requestPipelineId' => $requestPipelineId,
            ]);

            return null;
        }
    }

    /**
     * Clear the pipeline cookie
     */
    public function clearPipelineCookie(): void
    {
        Cookie::queue(Cookie::forget(self::COOKIE_NAME));
    }
}
