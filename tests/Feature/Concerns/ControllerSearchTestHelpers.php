<?php

namespace Tests\Feature\Concerns;

use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Testing\TestResponse;
use Webkul\User\Models\User;

trait ControllerSearchTestHelpers
{
    /**
     * Setup common test environment.
     */
    protected function setUpSearchTest(): void
    {
        $this->seed(TestSeeder::class);

        // Create and authenticate a back-office user
        $this->user = User::factory()->create(['first_name' => 'Admin', 'last_name' => 'Tester']);
        $this->actingAs($this->user, 'user');
        $this->withoutMiddleware(Authenticate::class);
    }

    /**
     * Perform a search request and return the response.
     *
     * @param  string  $route  Route name
     * @param  array  $params  Query parameters
     */
    protected function performSearch(string $route, array $params = []): TestResponse
    {
        return $this->getJson(route($route, $params));
    }

    /**
     * Get IDs from search response.
     */
    protected function getSearchResultIds(TestResponse $response): array
    {
        $data = $response->json('data');
        if (! is_array($data)) {
            $data = $response->json() ?? [];
        }

        return collect($data)->pluck('id')->toArray();
    }

    /**
     * Assert that an entity is found in search results.
     */
    protected function assertEntityFound(TestResponse $response, int $entityId): void
    {
        $response->assertOk();
        $ids = $this->getSearchResultIds($response);
        expect($ids)->toContain($entityId);
    }

    /**
     * Assert that an entity is NOT found in search results.
     */
    protected function assertEntityNotFound(TestResponse $response, int $entityId): void
    {
        $response->assertOk();
        $ids = $this->getSearchResultIds($response);
        expect($ids)->not->toContain($entityId);
    }

    /**
     * Assert that search returns empty results.
     */
    protected function assertSearchEmpty(TestResponse $response): void
    {
        $response->assertOk();
        $data = $response->json('data');
        if (! is_array($data)) {
            $data = $response->json() ?? [];
        }
        expect($data)->toBeArray()->and($data)->toBeEmpty();
    }

    /**
     * Assert that search returns all entities (when no query provided).
     */
    protected function assertSearchReturnsAll(TestResponse $response, array $expectedIds): void
    {
        $response->assertOk();
        $ids = $this->getSearchResultIds($response);
        foreach ($expectedIds as $expectedId) {
            expect($ids)->toContain($expectedId);
        }
    }

    /**
     * Test search with query parameter finds matching entities.
     *
     * @param  string  $route  Route name
     * @param  callable  $createMatching  Callback to create matching entity
     * @param  callable  $createNonMatching  Callback to create non-matching entity
     * @param  string  $query  Search query
     * @param  array  $additionalParams  Additional query parameters
     */
    protected function testSearchFindsMatching(
        string $route,
        callable $createMatching,
        callable $createNonMatching,
        string $query,
        array $additionalParams = []
    ): void {
        $matching = $createMatching();
        $nonMatching = $createNonMatching();

        $response = $this->performSearch($route, array_merge([
            'query' => $query,
        ], $additionalParams));

        $this->assertEntityFound($response, $matching->id);
        $this->assertEntityNotFound($response, $nonMatching->id);
    }

    /**
     * Test search with search/searchFields parameters finds matching entities.
     *
     * @param  string  $route  Route name
     * @param  callable  $createMatching  Callback to create matching entity
     * @param  callable  $createNonMatching  Callback to create non-matching entity
     * @param  string  $search  Search string
     * @param  string  $searchFields  Search fields string
     * @param  array  $additionalParams  Additional query parameters
     */
    protected function testSearchWithFieldsFindsMatching(
        string $route,
        callable $createMatching,
        callable $createNonMatching,
        string $search,
        string $searchFields,
        array $additionalParams = []
    ): void {
        $matching = $createMatching();
        $nonMatching = $createNonMatching();

        $response = $this->performSearch($route, array_merge([
            'search'       => $search,
            'searchFields' => $searchFields,
        ], $additionalParams));

        $this->assertEntityFound($response, $matching->id);
        $this->assertEntityNotFound($response, $nonMatching->id);
    }
}
