<?php

use App\Models\Department;
use App\Models\Order;
use App\Models\SalesLead;
use App\Services\Mail\MailboxResolver;
use Webkul\Lead\Models\Lead;

beforeEach(function () {
    config([
        'mail.mailboxes' => [
            'privatescan' => [
                'address'      => 'service@privatescan.nl',
                'display_name' => 'PrivateScan',
                'graph'        => ['tenant_id' => 't', 'client_id' => 'c', 'client_secret' => 's'],
            ],
            'herniapoli' => [
                'address'      => 'service@herniapoli.nl',
                'display_name' => 'HerniaPoli',
                'graph'        => ['tenant_id' => 't', 'client_id' => 'c', 'client_secret' => 's'],
            ],
        ],
    ]);
});

// ---------------------------------------------------------------------------
// Lead
// ---------------------------------------------------------------------------

test('resolves herniapoli for Lead with herniapoli department', function () {
    $dept = new Department;
    $dept->name = 'Herniapoli';

    $lead = new Lead;
    $lead->setRawAttributes(['department_id' => 1]);
    $lead->setRelation('department', $dept);

    expect(app(MailboxResolver::class)->resolveKeyFromEntity($lead))->toBe('herniapoli');
});

test('resolves privatescan for Lead with privatescan department', function () {
    $dept = new Department;
    $dept->name = 'Privatescan';

    $lead = new Lead;
    $lead->setRawAttributes(['department_id' => 1]);
    $lead->setRelation('department', $dept);

    expect(app(MailboxResolver::class)->resolveKeyFromEntity($lead))->toBe('privatescan');
});

test('returns null for Lead without department_id', function () {
    $lead = new Lead;

    expect(app(MailboxResolver::class)->resolveKeyFromEntity($lead))->toBeNull();
});

// ---------------------------------------------------------------------------
// Order
// ---------------------------------------------------------------------------

test('resolves herniapoli for Order with hernia pipeline department', function () {
    $dept = new Department;
    $dept->name = 'Herniapoli';

    $order = Mockery::mock(Order::class);
    $order->shouldReceive('getPipelineDepartment')->andReturn($dept);

    expect(app(MailboxResolver::class)->resolveKeyFromEntity($order))->toBe('herniapoli');
});

test('resolves privatescan for Order with privatescan pipeline department', function () {
    $dept = new Department;
    $dept->name = 'Privatescan';

    $order = Mockery::mock(Order::class);
    $order->shouldReceive('getPipelineDepartment')->andReturn($dept);

    expect(app(MailboxResolver::class)->resolveKeyFromEntity($order))->toBe('privatescan');
});

// ---------------------------------------------------------------------------
// SalesLead
// ---------------------------------------------------------------------------

test('resolves herniapoli for SalesLead with own herniapoli department', function () {
    $dept = new Department;
    $dept->name = 'Herniapoli';

    $salesLead = new SalesLead;
    $salesLead->setRawAttributes(['department_id' => 1]);
    $salesLead->setRelation('department', $dept);

    expect(app(MailboxResolver::class)->resolveKeyFromEntity($salesLead))->toBe('herniapoli');
});

test('resolves privatescan for SalesLead with own privatescan department', function () {
    $dept = new Department;
    $dept->name = 'Privatescan';

    $salesLead = new SalesLead;
    $salesLead->setRawAttributes(['department_id' => 1]);
    $salesLead->setRelation('department', $dept);

    expect(app(MailboxResolver::class)->resolveKeyFromEntity($salesLead))->toBe('privatescan');
});

test('resolves herniapoli for SalesLead falling back to lead department', function () {
    $dept = new Department;
    $dept->name = 'Herniapoli';

    $lead = new Lead;
    $lead->setRelation('department', $dept);

    $salesLead = new SalesLead;
    // no department_id → falls back to lead->department
    $salesLead->setRelation('lead', $lead);

    expect(app(MailboxResolver::class)->resolveKeyFromEntity($salesLead))->toBe('herniapoli');
});

test('returns null for SalesLead without department and without lead', function () {
    $salesLead = new SalesLead;

    expect(app(MailboxResolver::class)->resolveKeyFromEntity($salesLead))->toBeNull();
});

// ---------------------------------------------------------------------------
// Null / unrecognised entity
// ---------------------------------------------------------------------------

test('returns null for null entity', function () {
    expect(app(MailboxResolver::class)->resolveKeyFromEntity(null))->toBeNull();
});
