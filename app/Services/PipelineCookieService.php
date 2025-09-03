<?php

namespace App\Services;

use Illuminate\Support\Facades\Cookie;
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
     *
     * @return int|null
     */
    public function getLastSelectedPipelineId(): ?int
    {
        $pipelineId = request()->cookie(self::COOKIE_NAME);
        
        if (!$pipelineId) {
            return null;
        }

        // Validate that the pipeline still exists
        $pipeline = $this->pipelineRepository->find($pipelineId);
        
        return $pipeline ? (int) $pipelineId : null;
    }

    /**
     * Set the last selected pipeline ID in cookie
     *
     * @param int $pipelineId
     * @return \Illuminate\Cookie\CookieJar
     */
    public function setLastSelectedPipelineId(int $pipelineId)
    {
        // Validate that the pipeline exists before setting cookie
        $pipeline = $this->pipelineRepository->find($pipelineId);
        
        if (!$pipeline) {
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
    }

    /**
     * Get the effective pipeline ID considering URL parameter and cookie
     *
     * @param int|null $requestPipelineId Pipeline ID from request parameter
     * @return int|null
     */
    public function getEffectivePipelineId(?int $requestPipelineId = null): ?int
    {
        // URL parameter takes precedence over cookie
        if ($requestPipelineId) {
            // Set cookie to remember this choice
            $this->setLastSelectedPipelineId($requestPipelineId);
            return $requestPipelineId;
        }

        // Fall back to cookie value
        return $this->getLastSelectedPipelineId();
    }

    /**
     * Clear the pipeline cookie
     *
     * @return void
     */
    public function clearPipelineCookie(): void
    {
        Cookie::queue(Cookie::forget(self::COOKIE_NAME));
    }
}