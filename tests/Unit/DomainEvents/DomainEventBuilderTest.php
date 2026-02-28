<?php

namespace Tests\Unit\DomainEvents;

use App\Services\DomainEvents\DomainEventBuilder;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;

class DomainEventBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TestSeeder::class);
    }

    /** @test */
    public function it_builds_correct_structure_with_all_required_keys(): void
    {
        $stage = Stage::first();
        $lead  = Lead::factory()->create(['lead_pipeline_stage_id' => $stage->id]);

        $event = DomainEventBuilder::pipelineStageChanged(
            aggregateType: 'Lead',
            entity: $lead,
            oldStageId: null,
            newStageId: $stage->id,
        );

        $this->assertArrayHasKey('eventId', $event);
        $this->assertArrayHasKey('timestamp', $event);
        $this->assertArrayHasKey('aggregateType', $event);
        $this->assertArrayHasKey('aggregateId', $event);
        $this->assertArrayHasKey('eventType', $event);
        $this->assertArrayHasKey('payload', $event);
        $this->assertArrayHasKey('oldStage', $event['payload']);
        $this->assertArrayHasKey('newStage', $event['payload']);
        $this->assertArrayHasKey('entity', $event['payload']);
    }

    /** @test */
    public function it_sets_aggregate_type_aggregate_id_and_event_type_correctly(): void
    {
        $stage = Stage::first();
        $lead  = Lead::factory()->create(['lead_pipeline_stage_id' => $stage->id]);

        $event = DomainEventBuilder::pipelineStageChanged(
            aggregateType: 'Lead',
            entity: $lead,
            oldStageId: null,
            newStageId: $stage->id,
        );

        $this->assertSame('Lead', $event['aggregateType']);
        $this->assertSame($lead->getKey(), $event['aggregateId']);
        $this->assertSame('PipelineStageChanged', $event['eventType']);
    }

    /** @test */
    public function it_maps_old_and_new_stage_id_code_name_into_payload(): void
    {
        $stages   = Stage::take(2)->get();
        $oldStage = $stages->first();
        $newStage = $stages->last();

        $lead = Lead::factory()->create(['lead_pipeline_stage_id' => $newStage->id]);

        $event = DomainEventBuilder::pipelineStageChanged(
            aggregateType: 'Lead',
            entity: $lead,
            oldStageId: $oldStage->id,
            newStageId: $newStage->id,
        );

        $this->assertSame($oldStage->id, $event['payload']['oldStage']['id']);
        $this->assertSame($oldStage->code, $event['payload']['oldStage']['code']);
        $this->assertSame($oldStage->name, $event['payload']['oldStage']['name']);

        $this->assertSame($newStage->id, $event['payload']['newStage']['id']);
        $this->assertSame($newStage->code, $event['payload']['newStage']['code']);
        $this->assertSame($newStage->name, $event['payload']['newStage']['name']);
    }

    /** @test */
    public function it_sets_old_stage_null_when_old_stage_id_is_null(): void
    {
        $stage = Stage::first();
        $lead  = Lead::factory()->create(['lead_pipeline_stage_id' => $stage->id]);

        $event = DomainEventBuilder::pipelineStageChanged(
            aggregateType: 'Lead',
            entity: $lead,
            oldStageId: null,
            newStageId: $stage->id,
        );

        $this->assertNull($event['payload']['oldStage']);
    }

    /** @test */
    public function it_includes_entity_as_array(): void
    {
        $stage = Stage::first();
        $lead  = Lead::factory()->create(['lead_pipeline_stage_id' => $stage->id]);

        $event = DomainEventBuilder::pipelineStageChanged(
            aggregateType: 'Lead',
            entity: $lead,
            oldStageId: null,
            newStageId: $stage->id,
        );

        $this->assertIsArray($event['payload']['entity']);
        $this->assertSame($lead->id, $event['payload']['entity']['id']);
    }

    /** @test */
    public function it_generates_valid_uuid_v7(): void
    {
        $stage = Stage::first();
        $lead  = Lead::factory()->create(['lead_pipeline_stage_id' => $stage->id]);

        $event = DomainEventBuilder::pipelineStageChanged(
            aggregateType: 'Lead',
            entity: $lead,
            oldStageId: null,
            newStageId: $stage->id,
        );

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $event['eventId']
        );
    }

    /** @test */
    public function two_calls_produce_different_event_ids(): void
    {
        $stage = Stage::first();
        $lead  = Lead::factory()->create(['lead_pipeline_stage_id' => $stage->id]);

        $event1 = DomainEventBuilder::pipelineStageChanged(
            aggregateType: 'Lead',
            entity: $lead,
            oldStageId: null,
            newStageId: $stage->id,
        );

        $event2 = DomainEventBuilder::pipelineStageChanged(
            aggregateType: 'Lead',
            entity: $lead,
            oldStageId: null,
            newStageId: $stage->id,
        );

        $this->assertNotSame($event1['eventId'], $event2['eventId']);
    }
}
