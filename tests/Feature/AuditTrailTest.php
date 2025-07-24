<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Webkul\Contact\Models\Person;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_trail_fields_are_set_on_create()
    {
        // Arrange
        $user = User::factory()->create();
        $person = Person::factory()->create();
        $this->actingAs($user);

        // Act
        $address = Address::factory()->forPerson($person)->create();

        // Assert
        $this->assertEquals($user->id, $address->created_by);
        $this->assertEquals($user->id, $address->updated_by);
        $this->assertNotNull($address->created_at);
        $this->assertNotNull($address->updated_at);
    }

    public function test_audit_trail_updated_by_changes_on_update()
    {
        // Arrange
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $person = Person::factory()->create();
        
        $this->actingAs($user1);
        
        $address = Address::factory()->forPerson($person)->create();

        // Act - Switch to different user and update
        $this->actingAs($user2);
        $address->update(['city' => 'Updated City']);

        // Assert
        $this->assertEquals($user1->id, $address->created_by);
        $this->assertEquals($user2->id, $address->updated_by);
    }

    public function test_audit_trail_relations_work()
    {
        // Arrange
        $user = User::factory()->create();
        $person = Person::factory()->create();
        $this->actingAs($user);

        $address = Address::factory()->forPerson($person)->create();

        // Assert
        $this->assertInstanceOf(User::class, $address->creator);
        $this->assertInstanceOf(User::class, $address->updater);
        $this->assertEquals($user->id, $address->creator->id);
        $this->assertEquals($user->id, $address->updater->id);
    }
}