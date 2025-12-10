<?php

namespace Tests\Unit;

use App\Actions\Sales\SalesToLostAction;
use App\Models\SalesLead;
use App\Observers\SalesLeadObserver;
use App\Services\WebhookService;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use Webkul\Lead\Models\Stage;

class SalesLeadWebhookTest extends TestCase
{
    use RefreshDatabase;

    private int $webhookCallCount = 0;

    private SalesLeadObserver $observer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestSeeder::class);

        $this->webhookCallCount = 0;

        $this->observer = new SalesLeadObserver(
            webhookService: $this->mockWebhookService(),
            salesToLostAction: app(SalesToLostAction::class)
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_sends_webhook_on_created()
    {
        $salesLead = $this->createTestSalesLead();

        $this->observer->created($salesLead);

        $this->assertWebhookCalls(1, 'Webhook should be sent on created.');
    }

    /** @test */
    public function it_does_not_send_webhook_when_stage_did_not_change()
    {
        $salesLead = $this->createTestSalesLead();

        $this->observer->created($salesLead);
        $this->observer->updated($salesLead);

        // 1 webhook call for created, no update call since stage did not change
        $this->assertWebhookCalls(1, 'No webhook expected if pipeline stage stays the same.');
    }

    /** @test */
    public function it_sends_webhook_once_when_pipeline_stage_is_changed()
    {
        $salesLead = $this->createTestSalesLead();

        $this->observer->created($salesLead);

        // Simuleer wijziging van stage
        $salesLead->refresh();
        $newStage = Stage::where('id', '!=', $salesLead->pipeline_stage_id)->first();
        $salesLead->pipeline_stage_id = $newStage->id;

        $this->observer->updated($salesLead);

        $this->assertWebhookCalls(2, 'Webhook should be sent once when stage actually changes.');
    }

    public function it_does_not_send_webhook_when_pipeline_stage_is_not_changed(): void
    {
        $salesLead = $this->createTestSalesLead();

        $this->observer->created($salesLead);

        // Simuleer wijziging van een ander veld, niet de stage
        $salesLead->refresh();
        $salesLead->name = 'Updated name';

        $this->observer->updated($salesLead);

        $this->assertWebhookCalls(1, 'Webhook should be sent once when stage actually changes.');
    }

    private function mockWebhookService(): WebhookService
    {
        $mock = Mockery::mock(WebhookService::class);
        $mock->shouldReceive('sendWebhook')
            ->andReturnUsing(function () {
                $this->webhookCallCount++;

                return true;
            });

        return $mock;
    }

    private function createTestSalesLead(): SalesLead
    {
        $stage = Stage::first();

        return SalesLead::factory()->create([
            'name'               => 'Test Sales Lead',
            'pipeline_stage_id'  => $stage->id,
        ]);
    }

    private function assertWebhookCalls(int $expectedCount, string $message): void
    {
        $this->assertEquals($expectedCount, $this->webhookCallCount, $message);
    }
}
