<?php

namespace Tests\Feature;

use App\Models\Anamnesis;
use App\Models\Clinic;
use App\Models\PartnerProduct;
use App\Models\ProductType;
use App\Models\Resource;
use App\Models\ResourceType;
use App\Models\Shift;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Route;
use Webkul\Activity\Models\Activity;
use Webkul\Attribute\Models\Attribute;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\EmailTemplate\Models\EmailTemplate;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Source;
use Webkul\Lead\Models\Stage;
use Webkul\Lead\Models\Type;
use Webkul\Product\Models\Product;
use Webkul\Product\Models\ProductGroup;
use Webkul\Quote\Models\Quote;
use Webkul\Tag\Models\Tag;
use Webkul\User\Models\Group;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;
use Webkul\Warehouse\Models\Location;
use Webkul\Warehouse\Models\Warehouse;
use Webkul\WebForm\Models\WebForm;
use Webkul\Workflow\Models\Workflow;

/**
 * Test all admin URLs to ensure they don't return 500 errors.
 * This test dynamically discovers all admin routes and tests them,
 * so new routes are automatically included.
 */
beforeEach(function () {
    // Seed essential test data (pipelines, attributes, departments, etc.)
    $this->seed(TestSeeder::class);
});

it('tests all admin GET routes return valid HTTP status codes', function () {
    // Create admin user with full permissions
    $role = Role::factory()->create([
        'name' => 'Administrator',
        'description' => 'Full access administrator',
        'permission_type' => 'all',
        'permissions' => null,
    ]);

    $admin = User::factory()->create([
        'email' => 'admin_url_test@example.com',
        'name' => 'Admin URL Tester',
        'status' => 1,
        'role_id' => $role->id,
        'view_permission' => 'global',
    ]);

    // Create test data for routes that require IDs
    $testData = createTestDataForRoutes();

    // Get all routes from Laravel
    $allRoutes = Route::getRoutes();
    $adminPrefix = config('app.admin_path', 'admin');

    $testedRoutes = [];
    $failedRoutes = [];
    $skippedRoutes = [];

    foreach ($allRoutes as $route) {
        // Only test GET routes in the admin area
        if (! in_array('GET', $route->methods()) || ! in_array('HEAD', $route->methods())) {
            continue;
        }

        $uri = $route->uri();

        // Skip non-admin routes
        if (! str_starts_with($uri, $adminPrefix)) {
            continue;
        }

        // Skip routes without names (usually fallback routes)
        $routeName = $route->getName();
        if (! $routeName) {
            continue;
        }

        // Skip certain route patterns that are not meant to be tested directly
        if (shouldSkipRoute($routeName, $uri)) {
            $skippedRoutes[] = [
                'name' => $routeName,
                'uri' => $uri,
                'reason' => 'Excluded pattern',
            ];
            continue;
        }

        // Build URL with test data for routes with parameters
        try {
            $url = buildUrlWithParameters($routeName, $uri, $testData);
            
            if ($url === null) {
                $skippedRoutes[] = [
                    'name' => $routeName,
                    'uri' => $uri,
                    'reason' => 'Could not resolve parameters',
                ];
                continue;
            }

            // Make request
            $response = $this->actingAs($admin, 'user')
                ->get($url);

            $statusCode = $response->status();

            // Accept 200, 302 (redirects), 404 (not found is OK for some routes)
            // But NOT 500 (internal server error)
            if ($statusCode === 500) {
                $failedRoutes[] = [
                    'name' => $routeName,
                    'uri' => $uri,
                    'url' => $url,
                    'status' => $statusCode,
                ];
            }

            $testedRoutes[] = [
                'name' => $routeName,
                'uri' => $uri,
                'url' => $url,
                'status' => $statusCode,
            ];
        } catch (\Exception $e) {
            $failedRoutes[] = [
                'name' => $routeName,
                'uri' => $uri,
                'url' => $url ?? 'N/A',
                'status' => 'EXCEPTION',
                'message' => $e->getMessage(),
            ];
        }
    }

    // Output summary
    echo "\n\n";
    echo "========================================\n";
    echo "Admin URL Status Test Summary\n";
    echo "========================================\n";
    echo "Total routes tested: " . count($testedRoutes) . "\n";
    echo "Skipped routes: " . count($skippedRoutes) . "\n";
    echo "Failed routes: " . count($failedRoutes) . "\n";
    echo "\n";

    if (count($failedRoutes) > 0) {
        echo "FAILED ROUTES (returning 500 or exceptions):\n";
        echo "----------------------------------------\n";
        foreach ($failedRoutes as $failed) {
            echo "❌ {$failed['name']}\n";
            echo "   URI: {$failed['uri']}\n";
            echo "   URL: {$failed['url']}\n";
            echo "   Status: {$failed['status']}\n";
            if (isset($failed['message'])) {
                echo "   Error: {$failed['message']}\n";
            }
            echo "\n";
        }
    }

    // Report some successful tests
    $successfulTests = array_filter($testedRoutes, fn($r) => $r['status'] === 200);
    echo "Sample of SUCCESSFUL routes (HTTP 200):\n";
    echo "----------------------------------------\n";
    foreach (array_slice($successfulTests, 0, 10) as $success) {
        echo "✅ {$success['name']} -> {$success['url']}\n";
    }
    echo "\n";

    // The test should fail if any route returns 500
    expect(count($failedRoutes))
        ->toBe(0, 'Some admin routes returned 500 errors or exceptions. Check output above.');
});

/**
 * Create test data for routes that require parameters
 */
function createTestDataForRoutes(): array
{
    $data = [];

    // Get pipeline and stage from seeder
    $pipeline = Pipeline::first();
    if (! $pipeline) {
        $pipeline = Pipeline::create(['name' => 'Test Pipeline', 'is_default' => 1, 'rotten_days' => 30]);
    }
    $stage = Stage::where('lead_pipeline_id', $pipeline->id)->first();
    if (! $stage) {
        $stage = Stage::create([
            'lead_pipeline_id' => $pipeline->id,
            'name' => 'Test Stage',
            'code' => 'test',
            'sort_order' => 1,
            'probability' => 10,
        ]);
    }

    // Basic entities
    $data['lead'] = Lead::factory()->create([
        'lead_pipeline_id' => $pipeline->id,
        'lead_pipeline_stage_id' => $stage->id,
    ]);
    $data['person'] = Person::factory()->create();
    $data['organization'] = Organization::factory()->create();
    
    // Create product using direct creation (no factory available)
    $data['product'] = Product::create([
        'name' => 'Test Product',
        'sku' => 'TEST-SKU-' . uniqid(),
        'description' => 'Test product description',
        'price' => 100.00,
        'quantity' => 10,
    ]);

    // Create quote using direct creation
    $data['quote'] = Quote::create([
        'user_id' => User::first()->id,
        'person_id' => $data['person']->id,
        'subject' => 'Test Quote',
        'expired_at' => now()->addDays(30),
        'sub_total' => 100.00,
        'discount_amount' => 0,
        'tax_amount' => 21.00,
        'adjustment_amount' => 0,
        'grand_total' => 121.00,
        'lead_id' => $data['lead']->id,
    ]);

    // Create activity using direct creation
    $group = Group::first();
    if ($group) {
        $data['activity'] = Activity::create([
            'type' => 'call',
            'user_id' => User::first()->id,
            'title' => 'Test Activity',
            'schedule_from' => now(),
            'schedule_to' => now()->addHour(),
            'is_done' => 0,
            'group_id' => $group->id,
        ]);
    }

    // Settings entities - use firstOrCreate for existing seeded data
    $data['group'] = Group::first() ?? Group::create(['name' => 'Test Group']);
    $data['role'] = Role::first() ?? Role::factory()->create();
    $data['user'] = User::factory()->create();
    $data['pipeline'] = $pipeline;
    $data['type'] = Type::first() ?? Type::create(['name' => 'Test Type']);
    $data['source'] = Source::first() ?? Source::create(['name' => 'Test Source']);
    $data['tag'] = Tag::firstOrCreate(['name' => 'Test Tag']);
    
    // Warehouse - create if doesn't exist
    $data['warehouse'] = Warehouse::firstOrCreate(
        ['name' => 'Test Warehouse'],
        ['contact_name' => 'Test Contact']
    );
    $data['location'] = Location::firstOrCreate(
        ['name' => 'Test Location'],
        ['warehouse_id' => $data['warehouse']->id]
    );

    // Email template - create directly
    $data['email_template'] = EmailTemplate::firstOrCreate(
        ['name' => 'Test Template'],
        ['subject' => 'Test', 'content' => 'Test content']
    );

    // Web form - create directly
    $data['web_form'] = WebForm::firstOrCreate(
        ['title' => 'Test Form'],
        ['form_id' => 'test-form-' . uniqid()]
    );

    // Workflow - create directly  
    $data['workflow'] = Workflow::firstOrCreate(
        ['name' => 'Test Workflow'],
        ['entity_type' => 'leads', 'event' => 'activity.create']
    );

    // Attribute - get from seeder
    $data['attribute'] = Attribute::first() ?? Attribute::create([
        'code' => 'test_attribute',
        'name' => 'Test Attribute',
        'type' => 'text',
        'entity_type' => 'leads',
    ]);

    $data['productgroup'] = ProductGroup::factory()->create();

    // App-specific entities with factories
    if (class_exists(Clinic::class)) {
        $data['clinic'] = Clinic::factory()->create();
    }
    if (class_exists(ResourceType::class)) {
        $data['resource_type'] = ResourceType::first() ?? ResourceType::factory()->create();
    }
    if (class_exists(Resource::class)) {
        $resourceType = $data['resource_type'] ?? ResourceType::first();
        $data['resource'] = Resource::factory()->create([
            'resource_type_id' => $resourceType?->id,
        ]);
    }
    if (class_exists(Shift::class) && isset($data['resource'])) {
        $data['shift'] = Shift::factory()->create([
            'resource_id' => $data['resource']->id,
        ]);
    }
    if (class_exists(ProductType::class)) {
        $data['product_type'] = ProductType::factory()->create();
    }
    if (class_exists(PartnerProduct::class)) {
        $data['partner_product'] = PartnerProduct::factory()->create();
    }
    if (class_exists(Anamnesis::class)) {
        $data['anamnesis'] = Anamnesis::factory()->create([
            'lead_id' => $data['lead']->id,
            'person_id' => $data['person']->id,
        ]);
    }

    return $data;
}

/**
 * Determine if a route should be skipped
 */
function shouldSkipRoute(string $routeName, string $uri): bool
{
    // Skip documentation routes (they serve files)
    if (str_contains($routeName, 'docs')) {
        return true;
    }

    // Skip file upload routes
    if (str_contains($routeName, 'upload') || str_contains($routeName, 'tinymce')) {
        return true;
    }

    // Skip mass action routes (they're POST/DELETE usually, but just in case)
    if (str_contains($routeName, 'mass_update') || str_contains($routeName, 'mass_delete')) {
        return true;
    }

    // Skip debug routes
    if (str_contains($routeName, 'debug')) {
        return true;
    }

    // Skip API-like routes that expect specific data
    if (str_contains($routeName, 'look_up') || str_contains($routeName, 'lookup')) {
        return true;
    }

    // Skip datagrid export/download routes
    if (str_contains($routeName, 'download') || str_contains($routeName, 'export')) {
        return true;
    }

    // Skip stats/data retrieval endpoints
    if (str_contains($routeName, '.stats') || str_contains($routeName, '.get') && ! str_contains($routeName, 'forget')) {
        return true;
    }

    // Skip search endpoints (they usually expect query parameters)
    if (str_contains($routeName, 'search')) {
        return true;
    }

    return false;
}

/**
 * Build URL with parameters resolved from test data
 */
function buildUrlWithParameters(string $routeName, string $uri, array $testData): ?string
{
    try {
        // Extract parameter names from the route
        preg_match_all('/\{([^}]+)\}/', $uri, $matches);
        $parameters = $matches[1];

        if (empty($parameters)) {
            // No parameters, use route() helper directly
            return route($routeName);
        }

        // Try to resolve parameters from test data
        $resolvedParams = [];
        foreach ($parameters as $param) {
            // Remove optional marker
            $cleanParam = str_replace('?', '', $param);

            // Try to find corresponding test data
            $value = resolveParameterValue($cleanParam, $testData);
            
            if ($value === null) {
                // Can't resolve this parameter, skip the route
                return null;
            }

            $resolvedParams[$cleanParam] = $value;
        }

        // Build the route with parameters
        return route($routeName, $resolvedParams);
    } catch (\Exception $e) {
        return null;
    }
}

/**
 * Resolve a parameter value from test data
 */
function resolveParameterValue(string $param, array $testData): mixed
{
    // Direct match in test data
    if (isset($testData[$param])) {
        return $testData[$param]->id;
    }

    // Try common parameter name patterns
    $mappings = [
        'id' => 'lead', // Default ID to lead
        'lead_id' => 'lead',
        'person_id' => 'person',
        'personId' => 'person',
        'leadId' => 'lead',
        'pipeline_id' => 'pipeline',
        'warehouse_id' => 'warehouse',
        'warehouseId' => 'warehouse',
        'product_id' => 'product',
        'quote_id' => 'quote',
        'resourceId' => 'resource',
        'token' => null, // Can't test password reset tokens
        'path' => null, // Can't test file paths
    ];

    foreach ($mappings as $pattern => $dataKey) {
        if ($param === $pattern) {
            if ($dataKey === null) {
                return null;
            }
            return $testData[$dataKey]->id ?? null;
        }
    }

    // If the parameter ends with 'Id' or '_id', try to find the corresponding entity
    if (str_ends_with($param, 'Id') || str_ends_with($param, '_id')) {
        $entityName = str_replace(['_id', 'Id'], '', $param);
        $entityName = strtolower($entityName);
        
        if (isset($testData[$entityName])) {
            return $testData[$entityName]->id;
        }
    }

    // Default to the first lead ID for generic 'id' parameters
    if ($param === 'id' && isset($testData['lead'])) {
        return $testData['lead']->id;
    }

    return null;
}
