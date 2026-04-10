<?php

namespace Tests\Feature\Planning;

use App\Models\Clinic;
use App\Models\ClinicDepartment;
use App\Models\Resource;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('planning create-test-data adds ma–vr 08:00–17:00 shift for resources in active clinics', function () {
    $clinic = Clinic::factory()->create(['is_active' => true]);
    $dept = ClinicDepartment::factory()->create(['clinic_id' => $clinic->id]);
    $resource = Resource::factory()->create([
        'clinic_id'            => $clinic->id,
        'clinic_department_id' => $dept->id,
    ]);

    $this->artisan('planning:create-test-data')->assertSuccessful();

    $shift = Shift::query()->where('resource_id', $resource->id)->sole();

    expect($shift->period_end)->toBeNull()
        ->and($shift->available)->toBeTrue()
        ->and($shift->notes)->toBe('Auto-generated test shift');

    $blocks = $shift->weekday_time_blocks;
    expect($blocks)->toBeArray();

    for ($d = 1; $d <= 5; $d++) {
        $dayBlocks = $blocks[$d] ?? $blocks[(string) $d] ?? null;
        expect($dayBlocks)->toBeArray()
            ->and($dayBlocks[0]['from'] ?? null)->toBe('08:00')
            ->and($dayBlocks[0]['to'] ?? null)->toBe('17:00');
    }

    expect($blocks)->not->toHaveKey(6)
        ->and($blocks)->not->toHaveKey(7)
        ->and($blocks)->not->toHaveKey('6')
        ->and($blocks)->not->toHaveKey('7');
});

test('planning create-test-data replaces prior auto-generated test shift', function () {
    $clinic = Clinic::factory()->create(['is_active' => true]);
    $dept = ClinicDepartment::factory()->create(['clinic_id' => $clinic->id]);
    $resource = Resource::factory()->create([
        'clinic_id'            => $clinic->id,
        'clinic_department_id' => $dept->id,
    ]);

    Shift::create([
        'resource_id'         => $resource->id,
        'available'           => true,
        'notes'               => 'Auto-generated test shift',
        'period_start'        => now()->subMonth()->toDateString(),
        'period_end'          => null,
        'weekday_time_blocks' => [1 => [['from' => '09:00', 'to' => '12:00']]],
    ]);

    $this->artisan('planning:create-test-data')->assertSuccessful();

    expect(Shift::where('resource_id', $resource->id)->count())->toBe(1);

    $shift = Shift::query()->where('resource_id', $resource->id)->first();
    $blocks = $shift->weekday_time_blocks;
    expect($blocks[1][0]['from'] ?? $blocks['1'][0]['from'] ?? null)->toBe('08:00');
});
