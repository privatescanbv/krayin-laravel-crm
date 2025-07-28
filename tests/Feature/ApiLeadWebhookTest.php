<?php

use App\Enums\WebhookType;
use App\Services\WebhookService;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

class ApiLeadWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed test data
        $this->seed(TestSeeder::class);
        
        // Create a test user
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** @test */
    public function test_lead_creation_via_api_sends_only_one_webhook()
    {
        // Mock the HTTP client to capture webhook calls
        Http::fake();
        
        // Mock the webhook service to count calls
        $webhookCallCount = 0;
        $webhookData = [];
        
        $this->mock(WebhookService::class, function ($mock) use (&$webhookCallCount, &$webhookData) {
            $mock->shouldReceive('sendWebhook')
                ->andReturnUsing(function ($data, $type, $caller) use (&$webhookCallCount, &$webhookData) {
                    $webhookCallCount++;
                    $webhookData[] = [
                        'data' => $data,
                        'type' => $type,
                        'caller' => $caller
                    ];
                    
                    Log::info('Webhook called', [
                        'count' => $webhookCallCount,
                        'caller' => $caller,
                        'type' => $type->value,
                        'data' => $data
                    ]);
                    
                    return true;
                });
        });

        // Prepare lead data for API call
        $leadData = [
            'title' => 'Test Lead via API',
            'description' => 'Test lead created via API to test webhook behavior',
            'emails' => [
                [
                    'value' => 'test@example.com',
                    'label' => 'work',
                    'is_default' => true
                ]
            ],
            'phones' => [
                [
                    'value' => '+31612345678',
                    'label' => 'mobile',
                    'is_default' => true
                ]
            ],
            'first_name' => 'Test',
            'last_name' => 'User',
            'lead_type_id' => 1, // Assuming this exists from seeder
            'lead_source_id' => 1, // Assuming this exists from seeder
        ];

        // Make API call to create lead
        $response = $this->postJson('/api/leads', $leadData);

        // Assert the response is successful
        $response->assertStatus(201)
                ->assertJson([
                    'message' => 'Lead created successfully.'
                ]);

        // Assert that exactly 1 webhook was sent
        $this->assertEquals(1, $webhookCallCount, 
            "Expected exactly 1 webhook call, but got {$webhookCallCount}. " .
            "Webhook calls: " . json_encode($webhookData)
        );

        // Assert the webhook was sent with correct type
        $this->assertCount(1, $webhookData);
        $this->assertEquals(WebhookType::LEAD_PIPELINE_STAGE_CHANGE, $webhookData[0]['type']);
        
        // Assert the webhook contains the lead data
        $this->assertArrayHasKey('entity_id', $webhookData[0]['data']);
        $this->assertArrayHasKey('status', $webhookData[0]['data']);
        $this->assertArrayHasKey('department', $webhookData[0]['data']);

        // Verify the lead was actually created
        $leadId = $response->json('data.id');
        $lead = Lead::find($leadId);
        $this->assertNotNull($lead);
        $this->assertEquals('Test Lead via API', $lead->title);
    }

    /** @test */
    public function test_lead_creation_via_api_with_operatie_type_sends_only_one_webhook()
    {
        // Mock the HTTP client to capture webhook calls
        Http::fake();
        
        // Mock the webhook service to count calls
        $webhookCallCount = 0;
        $webhookData = [];
        
        $this->mock(WebhookService::class, function ($mock) use (&$webhookCallCount, &$webhookData) {
            $mock->shouldReceive('sendWebhook')
                ->andReturnUsing(function ($data, $type, $caller) use (&$webhookCallCount, &$webhookData) {
                    $webhookCallCount++;
                    $webhookData[] = [
                        'data' => $data,
                        'type' => $type,
                        'caller' => $caller
                    ];
                    
                    Log::info('Webhook called', [
                        'count' => $webhookCallCount,
                        'caller' => $caller,
                        'type' => $type->value,
                        'data' => $data
                    ]);
                    
                    return true;
                });
        });

        // Create "Operatie" lead type if it doesn't exist
        $operatieType = \Webkul\Lead\Models\Type::firstOrCreate(['name' => 'Operatie']);

        // Prepare lead data for API call with Operatie type
        $leadData = [
            'title' => 'Test Operatie Lead via API',
            'description' => 'Test operatie lead created via API to test webhook behavior',
            'emails' => [
                [
                    'value' => 'operatie@example.com',
                    'label' => 'work',
                    'is_default' => true
                ]
            ],
            'phones' => [
                [
                    'value' => '+31612345679',
                    'label' => 'mobile',
                    'is_default' => true
                ]
            ],
            'first_name' => 'Operatie',
            'last_name' => 'User',
            'lead_type_id' => $operatieType->id,
            'lead_source_id' => 1, // Assuming this exists from seeder
        ];

        // Make API call to create lead
        $response = $this->postJson('/api/leads', $leadData);

        // Assert the response is successful
        $response->assertStatus(201)
                ->assertJson([
                    'message' => 'Lead created successfully.'
                ]);

        // Assert that exactly 1 webhook was sent
        $this->assertEquals(1, $webhookCallCount, 
            "Expected exactly 1 webhook call for Operatie lead, but got {$webhookCallCount}. " .
            "Webhook calls: " . json_encode($webhookData)
        );

        // Assert the webhook was sent with correct type
        $this->assertCount(1, $webhookData);
        $this->assertEquals(WebhookType::LEAD_PIPELINE_STAGE_CHANGE, $webhookData[0]['type']);
        
        // Assert the webhook contains the lead data with Hernia department
        $this->assertArrayHasKey('entity_id', $webhookData[0]['data']);
        $this->assertArrayHasKey('status', $webhookData[0]['data']);
        $this->assertArrayHasKey('department', $webhookData[0]['data']);
        $this->assertEquals('Hernia', $webhookData[0]['data']['department']);

        // Verify the lead was actually created
        $leadId = $response->json('data.id');
        $lead = Lead::find($leadId);
        $this->assertNotNull($lead);
        $this->assertEquals('Test Operatie Lead via API', $lead->title);
    }

    /** @test */
    public function test_webhook_contains_correct_lead_data()
    {
        // Mock the HTTP client
        Http::fake();
        
        // Mock the webhook service to capture webhook data
        $webhookData = null;
        
        $this->mock(WebhookService::class, function ($mock) use (&$webhookData) {
            $mock->shouldReceive('sendWebhook')
                ->once()
                ->andReturnUsing(function ($data, $type, $caller) use (&$webhookData) {
                    $webhookData = $data;
                    return true;
                });
        });

        // Prepare lead data for API call
        $leadData = [
            'title' => 'Webhook Data Test Lead',
            'description' => 'Testing webhook data content',
            'emails' => [
                [
                    'value' => 'webhook@example.com',
                    'label' => 'work',
                    'is_default' => true
                ]
            ],
            'first_name' => 'Webhook',
            'last_name' => 'Test',
            'lead_type_id' => 1,
            'lead_source_id' => 1,
        ];

        // Make API call to create lead
        $response = $this->postJson('/api/leads', $leadData);
        $response->assertStatus(201);

        // Assert webhook data contains expected fields
        $this->assertNotNull($webhookData);
        $this->assertArrayHasKey('entity_id', $webhookData);
        $this->assertArrayHasKey('status', $webhookData);
        $this->assertArrayHasKey('source_code', $webhookData);
        $this->assertArrayHasKey('source_code_id', $webhookData);
        $this->assertArrayHasKey('department', $webhookData);

        // Verify the entity_id matches the created lead
        $leadId = $response->json('data.id');
        $this->assertEquals($leadId, $webhookData['entity_id']);
    }
}