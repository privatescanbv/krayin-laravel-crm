<?php

use App\Enums\ActivityType;
use App\Models\Clinic;
use App\Services\ActivityQueueRepository;
use Database\Seeders\TestSeeder;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

it('excludes clinic uploads (rekening/inkoop factuur) from the uploads queue', function () {
    $adminRole = Role::factory()->create([
        'permission_type' => 'all',
        'permissions'     => null,
    ]);

    $admin = User::factory()->create([
        'role_id'         => $adminRole->id,
        'view_permission' => 'global',
        'status'          => 1,
    ]);

    $clinic = Clinic::factory()->create();
    $person = Person::factory()->create();

    // Patient upload – has person_id, no clinic_id, should appear in queue
    $patientUpload = Activity::create([
        'type'      => ActivityType::FILE->value,
        'title'     => 'Patient upload',
        'is_done'   => false,
        'user_id'   => $admin->id,
        'person_id' => $person->id,
    ]);

    // Clinic invoice upload – has clinic_id, should NOT appear in queue
    $clinicUpload = Activity::create([
        'type'      => ActivityType::FILE->value,
        'title'     => 'Inkoop factuur clinic',
        'is_done'   => false,
        'user_id'   => $admin->id,
        'clinic_id' => $clinic->id,
    ]);

    $this->actingAs($admin, 'user');

    /** @var ActivityQueueRepository $repo */
    $repo = app(ActivityQueueRepository::class);

    $counts = $repo->counts('uploads');

    expect($counts['open'])->toBe(1);
});
