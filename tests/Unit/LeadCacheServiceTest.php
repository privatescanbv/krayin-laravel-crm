<?php

namespace Tests\Unit;

use App\Services\LeadDuplicateCacheService;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Webkul\Lead\Models\Lead;

class LeadCacheServiceTest extends TestCase
{
    use RefreshDatabase;

    private LeadDuplicateCacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed database and disable observers
        $this->seed(TestSeeder::class);
        Lead::unsetEventDispatcher();

        $this->cacheService = app(LeadDuplicateCacheService::class);
        Cache::flush();
    }

    /** @test */
    public function it_can_be_instantiated()
    {
        $this->assertInstanceOf(LeadDuplicateCacheService::class, $this->cacheService);
    }

    /** @test */
    public function it_returns_empty_collection_for_nonexistent_lead()
    {
        $duplicates = $this->cacheService->getCachedDuplicates(99999);

        $this->assertInstanceOf(Collection::class, $duplicates);
        $this->assertTrue($duplicates->isEmpty());
    }

    /** @test */
    public function it_can_cache_and_retrieve_duplicates()
    {
        $lead = Lead::factory()->create();

        $duplicates = $this->cacheService->getCachedDuplicates($lead->id);

        $this->assertInstanceOf(Collection::class, $duplicates);
    }

    /** @test */
    public function it_can_invalidate_cache()
    {
        $lead = Lead::factory()->create();

        // Cache something
        $this->cacheService->getCachedDuplicates($lead->id);

        // Invalidate
        $this->cacheService->invalidateLeadCache($lead->id);

        // Should work without errors
        $this->assertTrue(true);
    }

    /** @test */
    public function it_provides_cache_stats()
    {
        $stats = $this->cacheService->getCacheStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_leads', $stats);
        $this->assertArrayHasKey('cache_ttl_hours', $stats);
        $this->assertArrayHasKey('cache_backend', $stats);
    }

    /** @test */
    public function it_can_handle_lead_merge()
    {
        $lead1 = Lead::factory()->create();
        $lead2 = Lead::factory()->create();

        // Should not throw errors
        $this->cacheService->handleLeadMerge($lead1->id, [$lead2->id]);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_can_clear_all_cache()
    {
        // Should not throw errors
        $this->cacheService->clearAllCache();

        $this->assertTrue(true);
    }
}
