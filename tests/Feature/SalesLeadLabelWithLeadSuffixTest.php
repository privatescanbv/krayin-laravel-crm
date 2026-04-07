<?php

use App\Models\SalesLead;
use Webkul\Lead\Models\Lead;

test('labelWithLeadSuffix returns only sales name when lead resolved name equals sales name', function () {
    $lead = Lead::factory()->create([
        'first_name' => 'Mark',
        'last_name'  => 'Bulthuis',
    ]);

    $salesLead = SalesLead::factory()->create([
        'name'    => $lead->name,
        'lead_id' => $lead->id,
    ]);

    expect($salesLead->fresh()->load('lead')->labelWithLeadSuffix())->toBe('Mark Bulthuis');
});

test('labelWithLeadSuffix includes lead name in parentheses when different from sales name', function () {
    $lead = Lead::factory()->create([
        'first_name' => 'Lead',
        'last_name'  => 'Person',
    ]);

    $salesLead = SalesLead::factory()->create([
        'name'    => 'Sales Label',
        'lead_id' => $lead->id,
    ]);

    expect($salesLead->fresh()->load('lead')->labelWithLeadSuffix())->toBe('Sales Label (Lead Person)');
});

test('labelWithLeadSuffix appends Geen lead when no lead linked', function () {
    $salesLead = SalesLead::factory()->create([
        'name'    => 'Orphan Sales',
        'lead_id' => null,
    ]);

    expect($salesLead->fresh()->labelWithLeadSuffix())->toBe('Orphan Sales (Geen lead)');
});
