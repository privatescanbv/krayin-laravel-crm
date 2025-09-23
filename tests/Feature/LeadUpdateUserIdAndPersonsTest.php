<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

class LeadUpdateUserIdAndPersonsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_allows_updating_lead_with_empty_user_id_and_sets_null()
    {
        $lead = Lead::factory()->create();

        $this->assertNotNull($lead->user_id);

        // Simulate UI sending empty string for user_id when clearing the field
        $updated = app(\Webkul\Lead\Repositories\LeadRepository::class)
            ->update(['user_id' => ''], $lead->id);

        $this->assertNull($updated->user_id);
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'user_id' => null,
        ]);
    }

    /** @test */
    public function it_can_sync_persons_when_updating_even_if_user_id_is_cleared()
    {
        $lead = Lead::factory()->create();
        $personA = Person::factory()->create();
        $personB = Person::factory()->create();

        $payload = [
            'user_id' => '',
            'person_ids' => [$personA->id, $personB->id],
        ];

        $updated = app(\Webkul\Lead\Repositories\LeadRepository::class)
            ->update($payload, $lead->id);

        $this->assertNull($updated->user_id);
        $this->assertCount(2, $updated->fresh()->persons);
        $this->assertTrue($updated->fresh()->persons->pluck('id')->contains($personA->id));
        $this->assertTrue($updated->fresh()->persons->pluck('id')->contains($personB->id));
    }
}

