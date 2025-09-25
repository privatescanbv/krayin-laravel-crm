<?php

namespace Tests\Feature\Settings;

use App\Models\Clinic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Webkul\User\Models\User;

class ClinicCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate');
    }

    protected function signInAdmin(): User
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'user');

        return $user;
    }

    public function test_index_returns_ok(): void
    {
        $this->signInAdmin();

        $response = $this->get(route('admin.settings.clinics.index'));
        $response->assertStatus(200);
    }

    public function test_can_create_clinic(): void
    {
        $this->signInAdmin();

        $payload = [
            'name'   => 'Test Clinic',
            'emails' => ['info@testclinic.tld'],
            'phones' => ['+31 10 123 4567'],
        ];

        $response = $this->post(route('admin.settings.clinics.store'), $payload);
        $response->assertStatus(200)->assertJsonPath('data.name', 'Test Clinic');

        $this->assertDatabaseHas('clinics', [
            'name' => 'Test Clinic',
        ]);
    }

    public function test_can_update_clinic(): void
    {
        $this->signInAdmin();

        $clinic = Clinic::factory()->create();

        $payload = [
            'name'   => 'Updated Clinic',
            'emails' => ['contact@updated.tld'],
            'phones' => ['+31 10 222 3333'],
            '_method' => 'put',
        ];

        $response = $this->post(route('admin.settings.clinics.update', ['id' => $clinic->id]), $payload);
        $response->assertStatus(200)->assertJsonPath('data.name', 'Updated Clinic');

        $this->assertDatabaseHas('clinics', [
            'id'   => $clinic->id,
            'name' => 'Updated Clinic',
        ]);
    }

    public function test_can_delete_clinic(): void
    {
        $this->signInAdmin();

        $clinic = Clinic::factory()->create();

        $response = $this->delete(route('admin.settings.clinics.delete', ['id' => $clinic->id]));
        $response->assertStatus(200);

        $this->assertDatabaseMissing('clinics', [
            'id' => $clinic->id,
        ]);
    }
}

