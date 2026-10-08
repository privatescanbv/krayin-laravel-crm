<?php

use App\Enums\CustomerType;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\DB;
use Webkul\Admin\DataGrids\Lead\LeadDataGrid;
use Webkul\DataGrid\Column;
use Webkul\Lead\Models\Lead;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    $this->actingAs(getDefaultAdmin(), 'user');
    $this->withoutVite();
});

function leadGridColumn(string $index): Column
{
    $grid = app(LeadDataGrid::class);
    $grid->prepareColumns();

    return collect($grid->getColumns())->first(fn (Column $column) => $column->getIndex() === $index);
}

function leadWithSnapshot(array $columns): Lead
{
    $lead = Lead::factory()->create();
    DB::table('leads')->where('id', $lead->id)->update($columns);

    return $lead;
}

test('the lead grid offers every customer type as a filter and shows its label', function () {
    $column = leadGridColumn('customer_type');

    expect($column->getFilterableType())->toBe('dropdown')
        ->and(collect($column->getFilterableOptions())->pluck('value')->all())
        ->toBe(['new', 'existing_buyer', 'existing_non_buyer'])
        ->and(($column->getClosure())((object) ['customer_type' => 'existing_non_buyer']))
        ->toBe('Bestaande klant, nooit gekocht')
        ->and(($column->getClosure())((object) ['customer_type' => null]))->toBe('--');
});

test('the lead grid shows the contact outcome as yes, no or unknown', function () {
    $closure = leadGridColumn('had_contact')->getClosure();

    expect($closure((object) ['had_contact' => 1]))->toBe(trans('admin::app.leads.index.datagrid.yes'))
        ->and($closure((object) ['had_contact' => 0]))->toBe(trans('admin::app.leads.index.datagrid.no'))
        ->and($closure((object) ['had_contact' => null]))->toBe('--');
});

test('the lead view shows an earlier buyer with purchases and last purchase date', function () {
    $lead = leadWithSnapshot([
        'customer_type'        => CustomerType::ExistingBuyer->value,
        'prior_purchase_count' => 2,
        'last_purchase_at'     => '2026-02-14',
    ]);

    $this->get(route('admin.leads.view', $lead->id))
        ->assertOk()
        ->assertSee('Bestaande klant · 2 aankopen, laatste 14-02-2026');
});

test('the lead view shows a returning non-buyer with the number of earlier leads', function () {
    $lead = leadWithSnapshot(['customer_type' => CustomerType::ExistingNonBuyer->value, 'prior_lead_count' => 1]);

    $this->get(route('admin.leads.view', $lead->id))
        ->assertOk()
        ->assertSee('Terugkerend · nooit gekocht (1 eerdere lead)');
});

test('the lead view flags a lost lead that never had contact', function () {
    $lead = leadWithSnapshot(['customer_type' => CustomerType::New->value, 'had_contact' => false]);

    $this->get(route('admin.leads.view', $lead->id))
        ->assertOk()
        ->assertSee('Nieuwe lead')
        ->assertSee('Nooit contact gehad');
});

test('the lead view shows no badge before the lead is classified', function () {
    $lead = leadWithSnapshot(['customer_type' => null]);

    $this->get(route('admin.leads.view', $lead->id))
        ->assertOk()
        ->assertDontSee('data-testid="lead-customer-type"', false);
});
