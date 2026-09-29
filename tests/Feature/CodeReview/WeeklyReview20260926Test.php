<?php

/**
 * Regression tests for the weekly code review of 2026-09-26 (top 5 bugs).
 */

use App\Enums\LostReason;
use App\Enums\OrderPaymentStatus;
use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStage;
use App\Models\Department;
use App\Models\Order;
use App\Models\SalesLead;
use App\Repositories\OrderRepository;
use Database\Seeders\TestSeeder;
use Webkul\Admin\DataGrids\Lead\LeadDataGrid;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function (): void {
    test()->withoutMiddleware(CanInstall::class);
    $this->seed(TestSeeder::class);
});

function reviewLead(string $departmentName): Lead
{
    $department = Department::firstOrCreate(['name' => $departmentName]);

    return Lead::factory()->create(['department_id' => $department->id]);
}

function reviewSales(Lead $lead, int $stageId, ?int $departmentId = null): SalesLead
{
    return SalesLead::create([
        'name'              => 'Review sales',
        'lead_id'           => $lead->id,
        'pipeline_stage_id' => $stageId,
        'department_id'     => $departmentId,
        'user_id'           => getDefaultAdmin()->id,
    ]);
}

// 1. SQL injection in LeadDataGrid rotten_lead filter
test('lead datagrid binds the rotten_lead filter instead of concatenating it', function (): void {
    $this->actingAs(getDefaultAdmin(), 'user');
    request()->merge(['rotten_lead' => ['in' => '1 OR 1=1']]);

    $query = app(LeadDataGrid::class)->prepareQueryBuilder();

    expect($query->toSql())->not->toContain('1=1')
        ->and($query->toSql())->toContain('rotten_lead = ?')
        ->and($query->getBindings())->toContain(1);
});

test('lead datagrid ignores a non-scalar rotten_lead filter', function (): void {
    $this->actingAs(getDefaultAdmin(), 'user');
    request()->merge(['rotten_lead' => ['in' => ['x']]]);

    expect(app(LeadDataGrid::class)->prepareQueryBuilder()->toSql())->not->toContain('rotten_lead = ?');
});

// 2. Sales stage/lost/referral routes were not ACL-protected
test('sales mutating routes require sales-leads permissions', function (string $routeName, string $method): void {
    $role = Role::factory()->create(['permission_type' => 'custom', 'permissions' => ['sales-leads', 'sales-leads.view']]);
    $user = User::factory()->create(['role_id' => $role->id, 'view_permission' => 'global', 'status' => 1]);
    $sales = reviewSales(reviewLead('Privatescan'), PipelineStage::SALES_IN_BEHANDELING->id());

    $this->actingAs($user, 'user')
        ->json($method, route($routeName, $sales->id), [
            'lead_pipeline_stage_id' => PipelineStage::SALES_IN_BEHANDELING->id(),
            'lost_reason'            => LostReason::DataEntry->value,
        ])
        ->assertStatus(401);
})->with([
    'stage update'          => ['admin.sales-leads.stage.update', 'PUT'],
    'lost'                  => ['admin.sales-leads.lost', 'PUT'],
    'create preventie sales' => ['admin.sales-leads.create-preventie-sales', 'POST'],
    'create hernia sales'   => ['admin.sales-leads.create-hernia-sales', 'POST'],
]);

// 3. Referral orders were moved into the lead department's order pipeline
test('losing a preventie sales from a hernia lead keeps its order in the privatescan order pipeline', function (): void {
    $this->actingAs(getDefaultAdmin(), 'user');
    $herniaLead = reviewLead('Herniapoli');
    $sales = reviewSales($herniaLead, PipelineStage::SALES_IN_BEHANDELING->id(), Department::findPrivateScanId());
    $order = Order::factory()->create([
        'sales_lead_id'     => $sales->id,
        'pipeline_stage_id' => PipelineStage::ORDER_CONFIRM->id(),
    ]);

    app(OrderRepository::class)->cleanUpFromLostSales((string) $sales->id);

    expect($order->fresh()->pipeline_stage_id)->toBe(PipelineStage::ORDER_VERLOREN->id());
});

test('hernia order still goes to the hernia lost stage', function (): void {
    $this->actingAs(getDefaultAdmin(), 'user');
    $order = Order::factory()->create(['pipeline_stage_id' => PipelineStage::ORDER_BEVESTIGD_HERNIA->id()]);

    expect($order->fresh()->lostStageIdForOwnPipeline())->toBe(PipelineStage::ORDER_VERLOREN_HERNIA->id())
        ->and($order->fresh()->sentStageIdForOwnPipeline())->toBe(PipelineStage::ORDER_BEVESTIGD_HERNIA->id());
});

// 4. Money received on a €0 order was hidden as "Niet van toepassing"
test('payment status of a zero-total order', function (float $total, float $paid, OrderPaymentStatus $expected): void {
    expect(OrderPaymentStatus::forOrder($total, $paid))->toBe($expected);
})->with([
    'nothing paid'   => [0.0, 0.0, OrderPaymentStatus::NOT_APPLICABLE],
    'paid, all lost' => [0.0, 250.0, OrderPaymentStatus::CREDIT],
    'normal partial' => [100.0, 50.0, OrderPaymentStatus::PARTIALLY_PAID],
]);

// 5. Sales stage update accepted a stage from another pipeline
test('sales stage update rejects a stage from another pipeline', function (): void {
    $this->actingAs(getDefaultAdmin(), 'user');
    $sales = reviewSales(reviewLead('Privatescan'), PipelineStage::SALES_IN_BEHANDELING->id());

    $this->putJson(route('admin.sales-leads.stage.update', $sales->id), [
        'lead_pipeline_stage_id' => PipelineStage::SALES_DOCTOR_ASSESSMENT_HERNIA->id(),
    ])->assertStatus(422);

    expect($sales->fresh()->pipeline_stage_id)->toBe(PipelineStage::SALES_IN_BEHANDELING->id());
});

test('sales stage update within the same pipeline still works', function (): void {
    $this->actingAs(getDefaultAdmin(), 'user');
    $sales = reviewSales(reviewLead('Privatescan'), PipelineStage::SALES_IN_BEHANDELING->id());
    $target = Webkul\Lead\Models\Stage::where('lead_pipeline_id', PipelineDefaultKeys::PIPELINE_PRIVATESCAN_SALES_ID->value)
        ->where('id', '!=', $sales->pipeline_stage_id)
        ->where('is_lost', false)
        ->where('is_won', false)
        ->firstOrFail();

    $this->putJson(route('admin.sales-leads.stage.update', $sales->id), [
        'lead_pipeline_stage_id' => $target->id,
    ])->assertOk();
});
