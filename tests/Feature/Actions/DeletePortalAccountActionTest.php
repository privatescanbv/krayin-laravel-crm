<?php

use App\Actions\Persons\DeletePortalAccountAction;
use App\Enums\PortalRevocationReason;
use App\Services\PersonKeycloakService;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Config;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Contact\Models\Person;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    // Zorg dat Keycloak als geconfigureerd wordt gezien.
    Config::set('services.keycloak.client_id', 'test-client');
});

test('deleting portal account deactivates person, clears keycloak id and password', function () {
    /** @var Person $person */
    $person = Person::factory()->create([
        'emails'           => [
            ['value' => 'patient@example.com', 'label' => 'eigen', 'is_default' => true],
        ],
        'is_active'        => true,
        'keycloak_user_id' => 'kc-user-1',
        'password'         => 'SomeSecret123!',
    ]);

    expect($person->password)->not->toBeNull();

    $personKeycloakService = Mockery::mock(PersonKeycloakService::class);
    $personKeycloakService->shouldReceive('delete')
        ->once()
        ->withArgs(function (Person $p) use ($person) {
            return $p->id === $person->id;
        })
        ->andReturn([
            'success' => true,
        ]);

    $activityRepository = app(ActivityRepository::class);
    $action = new DeletePortalAccountAction($personKeycloakService, $activityRepository);

    $result = $action->execute($person);

    expect($result['success'])->toBeTrue();

    $freshPerson = $person->fresh();

    expect($freshPerson->is_active)->toBeFalse()
        ->and($freshPerson->keycloak_user_id)->toBeNull()
        ->and($freshPerson->password)->toBeNull();
});

test('deleting portal account with reason creates audit activity', function () {
    /** @var Person $person */
    $person = Person::factory()->create([
        'emails'           => [
            ['value' => 'patient@example.com', 'label' => 'eigen', 'is_default' => true],
        ],
        'is_active'        => true,
        'keycloak_user_id' => 'kc-user-1',
        'password'         => 'SomeSecret123!',
    ]);

    $personKeycloakService = Mockery::mock(PersonKeycloakService::class);
    $personKeycloakService->shouldReceive('delete')
        ->once()
        ->andReturn(['success' => true]);

    $activityRepository = app(ActivityRepository::class);
    $action = new DeletePortalAccountAction($personKeycloakService, $activityRepository);

    $result = $action->execute(
        $person,
        PortalRevocationReason::RequestedByPatient,
        'Patiënt wil geen toegang meer'
    );

    expect($result['success'])->toBeTrue();

    // Refresh person to get the activities relationship
    $person->refresh();

    $activity = $person->activities()
        ->where('type', 'system')
        ->where('title', 'Patiëntportaal account ingetrokken')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->comment)->toBe('Op verzoek van patiënt: Patiënt wil geen toegang meer')
        ->and($activity->additional['revocation_reason'])->toBe('op_verzoek_patient')
        ->and($activity->additional['revocation_reason_label'])->toBe('Op verzoek van patiënt');
});
