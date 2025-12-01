<?php

namespace Tests\Unit;

use App\Enums\Departments;
use App\Models\Department;
use Tests\TestCase;
use Webkul\User\Models\Group;

class DepartmentTest extends TestCase
{
    public function test_map_group_to_department_id_uses_current_seeded_group_names()
    {
        // Arrange: create departments as in the enum (current standard data)
        $herniaDepartment = Department::query()->create([
            'name' => Departments::HERNIA->value, // e.g. 'Herniapoli'
        ]);

        $privatescanDepartment = Department::query()->create([
            'name' => Departments::PRIVATESCAN->value, // 'Privatescan'
        ]);

        // And groups exactly like the installer seeder does:
        // name equals the enum value (Departments::<...>->value)
        Group::query()->create([
            'name'          => Departments::HERNIA->value,
            'description'   => 'Hernia group',
            'department_id' => $herniaDepartment->id,
        ]);

        Group::query()->create([
            'name'          => Departments::PRIVATESCAN->value,
            'description'   => 'Privatescan group',
            'department_id' => $privatescanDepartment->id,
        ]);

        // Act: map using the group name(s) as they exist in the DB
        $mappedDepartmentIdForHernia = Department::mapGroupToDepartmentId([Departments::HERNIA->value]);
        $mappedDepartmentIdForPrivatescan = Department::mapGroupToDepartmentId([Departments::PRIVATESCAN->value]);

        // Assert: we expect it to resolve to the departments behind these groups.
        // Met de huidige implementatie zal dit waarschijnlijk falen voor Hernia,
        // maar de test weerspiegelt nu 1-op-1 de standaard data.
        $this->assertSame(
            $herniaDepartment->id,
            $mappedDepartmentIdForHernia,
            'Hernia group should map to the Hernia department as seeded'
        );

        $this->assertSame(
            $privatescanDepartment->id,
            $mappedDepartmentIdForPrivatescan,
            'Privatescan group should map to the Privatescan department as seeded'
        );
    }
}
