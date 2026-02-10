<?php

use App\Models\Department;
use Database\Seeders\TestSeeder;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'user');
});

test('creating a lead with create_person_from_lead creates an active person', function (): void {
    $departmentId = Department::query()->value('id');
    expect($departmentId)->not->toBeNull();

    $response = $this->post(route('admin.leads.store'), [
        'first_name'             => 'Jane',
        'last_name'              => 'Doe',
        'department_id'          => $departmentId,
        'emails'                 => [
            ['value' => 'jane.doe+'.uniqid().'@example.test', 'label' => 'eigen'],
        ],
        'create_person_from_lead' => '1',
    ]);

    $response->assertStatus(302);

    $lead = Lead::query()->latest('id')->firstOrFail();

    $person = $lead->persons()->first();
    expect($person)->not->toBeNull();

    expect((bool) $person->is_active)->toBeTrue();
});
