<?php

namespace Tests\Feature;

use App\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Webkul\Contact\Models\Person;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

class SimpleAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a role for users
        Role::create([
            'name'            => 'Admin',
            'description'     => 'Administrator role',
            'permission_type' => 'all',
            'permissions'     => [],
        ]);
    }

    public function test_address_has_audit_trail_columns()
    {
        // Test that the addresses table has the audit trail columns
        $this->assertTrue(\Schema::hasColumn('addresses', 'created_by'));
        $this->assertTrue(\Schema::hasColumn('addresses', 'updated_by'));
        $this->assertTrue(\Schema::hasColumn('addresses', 'created_at'));
        $this->assertTrue(\Schema::hasColumn('addresses', 'updated_at'));
    }

    public function test_address_audit_trail_fillable()
    {
        // Test that audit trail fields are fillable
        $address = new Address();
        $fillable = $address->getFillable();
        
        $this->assertContains('created_by', $fillable);
        $this->assertContains('updated_by', $fillable);
    }

    public function test_address_audit_trail_with_authenticated_user()
    {
        // Arrange
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => 1,
        ]);
        
        $person = Person::factory()->create();
        $this->actingAs($user);

        // Act
        $address = Address::create([
            'street' => 'Test Street',
            'house_number' => '123',
            'postal_code' => '1234AB',
            'city' => 'Test City',
            'country' => 'Test Country',
            'person_id' => $person->id,
        ]);

        // Assert
        $this->assertEquals($user->id, $address->created_by);
        $this->assertEquals($user->id, $address->updated_by);
        $this->assertNotNull($address->created_at);
        $this->assertNotNull($address->updated_at);
    }

    public function test_address_audit_trail_without_authenticated_user()
    {
        // Arrange - No authenticated user
        $person = Person::factory()->create();

        // Act
        $address = Address::create([
            'street' => 'Test Street',
            'house_number' => '123',
            'postal_code' => '1234AB',
            'city' => 'Test City',
            'country' => 'Test Country',
            'person_id' => $person->id,
        ]);

        // Assert - Should be null when no user is authenticated
        $this->assertNull($address->created_by);
        $this->assertNull($address->updated_by);
        $this->assertNotNull($address->created_at);
        $this->assertNotNull($address->updated_at);
    }
}