<?php

namespace Tests\Feature;

use App\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Source;
use Webkul\Lead\Models\Stage;
use Webkul\Lead\Models\Type;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

class ComprehensiveAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    protected User $user1;
    protected User $user2;
    protected Role $role;
    protected Pipeline $pipeline;
    protected Stage $stage;
    protected Source $source;
    protected Type $type;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a role for users
        $this->role = Role::create([
            'name'            => 'Admin',
            'description'     => 'Administrator role',
            'permission_type' => 'all',
            'permissions'     => [],
        ]);

        // Create test users
        $this->user1 = User::create([
            'name' => 'Test User 1',
            'email' => 'user1@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => $this->role->id,
        ]);

        $this->user2 = User::create([
            'name' => 'Test User 2',
            'email' => 'user2@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => $this->role->id,
        ]);

        // Create required Lead dependencies
        $this->pipeline = Pipeline::create([
            'name'        => 'Test Pipeline',
            'is_default'  => 1,
            'rotten_days' => 30,
        ]);

        $this->stage = Stage::create([
            'name'             => 'New',
            'code'             => 'new',
            'lead_pipeline_id' => $this->pipeline->id,
            'sort_order'       => 1,
            'probability'      => 10,
        ]);

        $this->source = Source::create([
            'name' => 'Test Source',
        ]);

        $this->type = Type::create([
            'name' => 'Test Type',
        ]);
    }

    public function test_address_audit_trail()
    {
        $this->actingAs($this->user1);
        $person = Person::factory()->create();

        // Create address
        $address = Address::create([
            'street' => 'Test Street',
            'house_number' => '123',
            'postal_code' => '1234AB',
            'city' => 'Test City',
            'country' => 'Test Country',
            'person_id' => $person->id,
        ]);

        // Assert creation audit
        $this->assertEquals($this->user1->id, $address->created_by);
        $this->assertEquals($this->user1->id, $address->updated_by);
        $this->assertNotNull($address->created_at);
        $this->assertNotNull($address->updated_at);

        // Update as different user
        $this->actingAs($this->user2);
        $address->update(['city' => 'Updated City']);

        // Assert update audit
        $this->assertEquals($this->user1->id, $address->created_by);
        $this->assertEquals($this->user2->id, $address->updated_by);

        // Test relations
        $this->assertInstanceOf(User::class, $address->creator);
        $this->assertInstanceOf(User::class, $address->updater);
        $this->assertEquals($this->user1->id, $address->creator->id);
        $this->assertEquals($this->user2->id, $address->updater->id);
    }

    public function test_lead_audit_trail()
    {
        $this->actingAs($this->user1);

        // Create lead with all required dependencies
        $lead = Lead::create([
            'title' => 'Test Lead',
            'description' => 'Test Description',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'emails' => ['john@example.com'],
            'phones' => ['1234567890'],
            'user_id' => $this->user1->id,
            'lead_pipeline_id' => $this->pipeline->id,
            'lead_pipeline_stage_id' => $this->stage->id,
            'lead_source_id' => $this->source->id,
            'lead_type_id' => $this->type->id,
        ]);
        
        // Refresh to get database values (LeadObserver does direct DB updates)
        $lead->refresh();

        // Assert creation audit
        $this->assertEquals($this->user1->id, $lead->created_by);
        $this->assertEquals($this->user1->id, $lead->updated_by);
        $this->assertNotNull($lead->created_at);
        $this->assertNotNull($lead->updated_at);
        
        // Verify required relationships exist
        $this->assertNotNull($lead->lead_pipeline_stage_id);
        $this->assertNotNull($lead->stage);
        $this->assertEquals($this->stage->id, $lead->lead_pipeline_stage_id);

        // Update as different user
        $this->actingAs($this->user2);
        $lead->update(['title' => 'Updated Lead Title']);
        
        // Refresh to get database values (LeadObserver does direct DB updates)
        $lead->refresh();

        // Assert update audit
        $this->assertEquals($this->user1->id, $lead->created_by);
        $this->assertEquals($this->user2->id, $lead->updated_by);

        // Test relations
        $this->assertInstanceOf(User::class, $lead->creator);
        $this->assertInstanceOf(User::class, $lead->updater);
        $this->assertEquals($this->user1->id, $lead->creator->id);
        $this->assertEquals($this->user2->id, $lead->updater->id);
    }

    public function test_person_audit_trail()
    {
        $this->actingAs($this->user1);

        // Create person
        $person = Person::create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'emails' => ['jane@example.com'],
            'phones' => ['0987654321'],
            'user_id' => $this->user1->id,
        ]);

        // Assert creation audit
        $this->assertEquals($this->user1->id, $person->created_by);
        $this->assertEquals($this->user1->id, $person->updated_by);
        $this->assertNotNull($person->created_at);
        $this->assertNotNull($person->updated_at);

        // Update as different user
        $this->actingAs($this->user2);
        $person->update(['first_name' => 'Janet']);

        // Assert update audit
        $this->assertEquals($this->user1->id, $person->created_by);
        $this->assertEquals($this->user2->id, $person->updated_by);

        // Test relations
        $this->assertInstanceOf(User::class, $person->creator);
        $this->assertInstanceOf(User::class, $person->updater);
        $this->assertEquals($this->user1->id, $person->creator->id);
        $this->assertEquals($this->user2->id, $person->updater->id);
    }

    public function test_organization_audit_trail()
    {
        $this->actingAs($this->user1);

        // Create organization
        $organization = Organization::create([
            'name' => 'Test Company',
            'user_id' => $this->user1->id,
        ]);

        // Assert creation audit
        $this->assertEquals($this->user1->id, $organization->created_by);
        $this->assertEquals($this->user1->id, $organization->updated_by);
        $this->assertNotNull($organization->created_at);
        $this->assertNotNull($organization->updated_at);

        // Update as different user
        $this->actingAs($this->user2);
        $organization->update(['name' => 'Updated Company Name']);

        // Assert update audit
        $this->assertEquals($this->user1->id, $organization->created_by);
        $this->assertEquals($this->user2->id, $organization->updated_by);

        // Test relations
        $this->assertInstanceOf(User::class, $organization->creator);
        $this->assertInstanceOf(User::class, $organization->updater);
        $this->assertEquals($this->user1->id, $organization->creator->id);
        $this->assertEquals($this->user2->id, $organization->updater->id);
    }

    public function test_user_audit_trail()
    {
        $this->actingAs($this->user1);

        // Create user
        $newUser = User::create([
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => bcrypt('password'),
            'status' => 1,
            'role_id' => $this->role->id,
        ]);

        // Assert creation audit
        $this->assertEquals($this->user1->id, $newUser->created_by);
        $this->assertEquals($this->user1->id, $newUser->updated_by);
        $this->assertNotNull($newUser->created_at);
        $this->assertNotNull($newUser->updated_at);

        // Update as different user
        $this->actingAs($this->user2);
        $newUser->update(['name' => 'Updated User Name']);

        // Assert update audit
        $this->assertEquals($this->user1->id, $newUser->created_by);
        $this->assertEquals($this->user2->id, $newUser->updated_by);

        // Test relations
        $this->assertInstanceOf(User::class, $newUser->creator);
        $this->assertInstanceOf(User::class, $newUser->updater);
        $this->assertEquals($this->user1->id, $newUser->creator->id);
        $this->assertEquals($this->user2->id, $newUser->updater->id);
    }

    public function test_all_entities_have_audit_trail_columns()
    {
        // Test that all tables have the required audit trail columns
        $this->assertTrue(\Schema::hasColumn('addresses', 'created_by'));
        $this->assertTrue(\Schema::hasColumn('addresses', 'updated_by'));
        
        $this->assertTrue(\Schema::hasColumn('leads', 'created_by'));
        $this->assertTrue(\Schema::hasColumn('leads', 'updated_by'));
        
        $this->assertTrue(\Schema::hasColumn('persons', 'created_by'));
        $this->assertTrue(\Schema::hasColumn('persons', 'updated_by'));
        
        $this->assertTrue(\Schema::hasColumn('organizations', 'created_by'));
        $this->assertTrue(\Schema::hasColumn('organizations', 'updated_by'));
        
        $this->assertTrue(\Schema::hasColumn('users', 'created_by'));
        $this->assertTrue(\Schema::hasColumn('users', 'updated_by'));

        // All should also have standard Laravel timestamps
        $this->assertTrue(\Schema::hasColumn('addresses', 'created_at'));
        $this->assertTrue(\Schema::hasColumn('addresses', 'updated_at'));
        $this->assertTrue(\Schema::hasColumn('leads', 'created_at'));
        $this->assertTrue(\Schema::hasColumn('leads', 'updated_at'));
        $this->assertTrue(\Schema::hasColumn('persons', 'created_at'));
        $this->assertTrue(\Schema::hasColumn('persons', 'updated_at'));
        $this->assertTrue(\Schema::hasColumn('organizations', 'created_at'));
        $this->assertTrue(\Schema::hasColumn('organizations', 'updated_at'));
        $this->assertTrue(\Schema::hasColumn('users', 'created_at'));
        $this->assertTrue(\Schema::hasColumn('users', 'updated_at'));
    }

    public function test_audit_trail_without_authenticated_user()
    {
        // Test that audit trail fields are null when no user is authenticated
        
        // Create person without authentication
        $person = Person::factory()->create();
        
        $address = Address::create([
            'street' => 'Test Street',
            'house_number' => '123',
            'postal_code' => '1234AB',
            'city' => 'Test City',
            'country' => 'Test Country',
            'person_id' => $person->id,
        ]);

        $this->assertNull($address->created_by);
        $this->assertNull($address->updated_by);
        $this->assertNotNull($address->created_at);
        $this->assertNotNull($address->updated_at);
    }

    public function test_audit_trail_fillable_fields()
    {
        // Test that all models have audit trail fields in their fillable arrays
        $address = new Address();
        $this->assertContains('created_by', $address->getFillable());
        $this->assertContains('updated_by', $address->getFillable());

        $lead = new Lead();
        $this->assertContains('created_by', $lead->getFillable());
        $this->assertContains('updated_by', $lead->getFillable());

        $person = new Person();
        $this->assertContains('created_by', $person->getFillable());
        $this->assertContains('updated_by', $person->getFillable());

        // Note: Organization fillable is handled dynamically in the service provider
    }
}