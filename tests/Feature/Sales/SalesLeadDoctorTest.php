<?php

use App\Enums\PipelineStage;
use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Webkul\Contact\Models\Person;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function (): void {
    test()->withoutMiddleware(CanInstall::class);
    $this->seed(TestSeeder::class);
    $this->actingAs(getDefaultAdmin(), 'user');
});

function doctorUser(): User
{
    $role = Role::firstOrCreate(['code' => RoleCode::ARTS->value], [
        'name' => 'Arts', 'description' => 'Arts', 'permission_type' => 'custom', 'permissions' => [],
    ]);

    return makeUser(['role_id' => $role->id]);
}

function salesFor(string $department): SalesLead
{
    $dept = Department::firstOrCreate(['name' => $department]);
    $lead = Lead::factory()->create(['department_id' => $dept->id]);

    $sales = SalesLead::create([
        'name'              => 'Sale',
        'lead_id'           => $lead->id,
        'pipeline_stage_id' => PipelineStage::SALES_DOCTOR_ASSESSMENT_HERNIA->id(),
        'department_id'     => $dept->id,
        'user_id'           => getDefaultAdmin()->id,
    ]);
    $sales->syncPersons([Person::factory()->create()->id]);

    return $sales;
}

function updateSales(SalesLead $sales, array $extra = [])
{
    return test()->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->putJson(route('admin.sales-leads.update', $sales->id), [
        'name'       => 'Sale',
        'person_ids' => $sales->persons()->pluck('persons.id')->all(),
        ...$extra,
    ]);
}

test('an arts can be set and cleared on a Herniapoli sale', function (): void {
    $sales = salesFor('Herniapoli');
    $doctor = doctorUser();

    updateSales($sales, ['doctor_user_id' => $doctor->id])->assertOk();
    expect($sales->fresh()->doctor_user_id)->toBe($doctor->id);

    updateSales($sales, ['doctor_user_id' => null])->assertOk();
    expect($sales->fresh()->doctor_user_id)->toBeNull();
});

test('the arts is ignored for a non-Herniapoli sale', function (): void {
    $sales = salesFor('Privatescan');

    updateSales($sales, ['doctor_user_id' => doctorUser()->id])->assertOk();

    expect($sales->fresh()->doctor_user_id)->toBeNull();
});

test('a user without the Arts role is rejected', function (): void {
    $sales = salesFor('Herniapoli');

    updateSales($sales, ['doctor_user_id' => makeUser()->id])
        ->assertUnprocessable()->assertJsonValidationErrors('doctor_user_id');
});

test('the arts is optional: saving without it keeps working', function (): void {
    $sales = salesFor('Herniapoli');

    updateSales($sales)->assertOk();

    expect($sales->fresh()->doctor_user_id)->toBeNull();
});

test('a doctor who lost the Arts role can stay on the sale', function (): void {
    $sales = salesFor('Herniapoli');
    $doctor = doctorUser();
    updateSales($sales, ['doctor_user_id' => $doctor->id])->assertOk();
    $doctor->update(['role_id' => getDefaultAdmin()->role_id]);

    updateSales($sales, ['doctor_user_id' => $doctor->id])->assertOk();

    expect($sales->fresh()->doctor_user_id)->toBe($doctor->id);
});

test('edit shows the arts select only for Herniapoli', function (): void {
    $doctor = doctorUser();

    $this->get(route('admin.sales-leads.edit', salesFor('Herniapoli')->id))
        ->assertOk()->assertSee('name="doctor_user_id"', false)->assertSee($doctor->name);
    $this->get(route('admin.sales-leads.edit', salesFor('Privatescan')->id))
        ->assertOk()->assertDontSee('name="doctor_user_id"', false);
});

test('view shows the arts or "Niet ingevuld"', function (): void {
    $sales = salesFor('Herniapoli');
    $this->get(route('admin.sales-leads.view', $sales->id))->assertOk()->assertSee('Niet ingevuld');

    $doctor = doctorUser();
    updateSales($sales, ['doctor_user_id' => $doctor->id])->assertOk();
    $this->get(route('admin.sales-leads.view', $sales->id))->assertOk()->assertSee($doctor->name);
});

test('changing the arts is logged in the Wijzigingslogboek', function (): void {
    $sales = salesFor('Herniapoli');

    updateSales($sales, ['doctor_user_id' => doctorUser()->id])->assertOk();

    $this->assertDatabaseHas('activities', ['sales_lead_id' => $sales->id, 'title' => 'Arts gewijzigd']);
});
