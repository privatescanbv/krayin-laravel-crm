<?php

use App\Enums\PipelineDefaultKeys;
use App\Enums\RoleCode;
use App\Models\ClinicDepartment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Resource;
use App\Models\ResourceOrderItem;
use App\Models\SalesLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['api.keys' => ['valid-api-key-123']]);

    foreach ([PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID, PipelineDefaultKeys::PIPELINE_PRIVATESCAN_SALES_ID, PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID] as $pipeline) {
        DB::table('lead_pipelines')->insertOrIgnore(['id' => $pipeline->value, 'name' => 'Pipeline '.$pipeline->value, 'is_default' => 0, 'rotten_days' => 30]);
    }
});

function apptDoctor(string $keycloakId, RoleCode $roleCode = RoleCode::ARTS): User
{
    $role = Role::query()->where('code', $roleCode->value)->first() ?? Role::factory()->create(['code' => $roleCode->value]);

    return User::withoutEvents(fn () => User::factory()->create([
        'status'           => 1,
        'keycloak_user_id' => $keycloakId,
        'role_id'          => $role->id,
    ]));
}

function apptHerniaStage(bool $visibleForRole = true): Stage
{
    $stage = Stage::factory()->create(['lead_pipeline_id' => PipelineDefaultKeys::PIPELINE_HERNIA_SALES_ID->value]);

    if ($visibleForRole) {
        foreach (RoleCode::cases() as $code) {
            $role = Role::query()->where('code', $code->value)->first() ?? Role::factory()->create(['code' => $code->value]);
            $stage->roles()->attach($role->id);
        }
    }

    return $stage;
}

/**
 * Creates a sale assigned to $doctor with a planned order + booking, in the Herniapoli pipeline.
 *
 * @return array{0: Order, 1: ResourceOrderItem}
 */
function apptForDoctor(User $doctor, Stage $stage): array
{
    $sale = SalesLead::factory()->create(['doctor_user_id' => $doctor->id, 'pipeline_stage_id' => $stage->id]);

    $department = ClinicDepartment::factory()->create(['order_confirmation_note' => 'Meld u bij de balie']);
    $resource = Resource::factory()->create(['clinic_department_id' => $department->id]);

    $order = Order::factory()->create(['sales_lead_id' => $sale->id]);
    $person = Person::factory()->create();
    $item = OrderItem::factory()->create(['order_id' => $order->id, 'person_id' => $person->id]);
    $booking = ResourceOrderItem::factory()->create([
        'orderitem_id' => $item->id,
        'resource_id'  => $resource->id,
        'from'         => now()->addDay(),
        'to'           => now()->addDay()->addHour(),
    ]);

    return [$order, $booking];
}

function apptApiKeyHeader(): array
{
    return ['X-API-KEY' => 'valid-api-key-123'];
}

it('shows the detail of an appointment of the doctor\'s own sale', function () {
    $doctor = apptDoctor('kc-appt-doc-1');
    [$order, $booking] = apptForDoctor($doctor, apptHerniaStage());

    $this->withHeaders(apptApiKeyHeader())
        ->getJson(route('api.clinic.appointments.show', ['kc-appt-doc-1', $booking->id]))
        ->assertOk()
        ->assertJsonPath('data.id', (string) $booking->id)
        ->assertJsonPath('data.registration_note', 'Meld u bij de balie')
        ->assertJsonPath('data.sale.id', $order->salesLead->id);
});

it('still accepts the legacy order-{id} form for the doctor\'s own sale', function () {
    $doctor = apptDoctor('kc-appt-doc-2');
    [$order, $booking] = apptForDoctor($doctor, apptHerniaStage());

    $this->withHeaders(apptApiKeyHeader())
        ->getJson(route('api.clinic.appointments.show', ['kc-appt-doc-2', 'order-'.$order->id]))
        ->assertOk()
        ->assertJsonPath('data.id', (string) $booking->id);
});

it('returns 404 for an appointment outside the doctor\'s own scope', function () {
    apptDoctor('kc-appt-doc-3');
    $other = apptDoctor('kc-appt-doc-4');
    [, $booking] = apptForDoctor($other, apptHerniaStage());

    $this->withHeaders(apptApiKeyHeader())
        ->getJson(route('api.clinic.appointments.show', ['kc-appt-doc-3', $booking->id]))
        ->assertNotFound();
});

it('returns 404 for a hidden-stage sale and forbids non-doctor roles', function () {
    $doctor = apptDoctor('kc-appt-doc-5');
    $hiddenStage = apptHerniaStage(visibleForRole: false);
    [, $hiddenBooking] = apptForDoctor($doctor, $hiddenStage);

    $this->withHeaders(apptApiKeyHeader())
        ->getJson(route('api.clinic.appointments.show', ['kc-appt-doc-5', $hiddenBooking->id]))
        ->assertNotFound();

    apptDoctor('kc-appt-clinic-1', RoleCode::KLINIEK_MEDEWERKER);
    $this->withHeaders(apptApiKeyHeader())
        ->getJson(route('api.clinic.appointments.show', ['kc-appt-clinic-1', $hiddenBooking->id]))
        ->assertForbidden();
});

it('only lists the order items of the booking\'s own person', function () {
    $doctor = apptDoctor('kc-appt-doc-items');
    [$order, $booking] = apptForDoctor($doctor, apptHerniaStage());

    OrderItem::factory()->create(['order_id' => $order->id, 'person_id' => Person::factory()->create()->id]);

    $this->withHeaders(apptApiKeyHeader())
        ->getJson(route('api.clinic.appointments.show', ['kc-appt-doc-items', $booking->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data.items');
});
