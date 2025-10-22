<?php

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Admin\DataGrids\Mail\EmailDataGrid;

uses(RefreshDatabase::class);

test('query builder creation', function () {
    request()->merge(['route' => 'inbox']);

    $dataGrid = new EmailDataGrid;
    $queryBuilder = $dataGrid->prepareQueryBuilder();

    expect($queryBuilder)->toBeInstanceOf(Builder::class);
});

test('query includes required select fields', function () {
    request()->merge(['route' => 'inbox']);

    $dataGrid = new EmailDataGrid;
    $queryBuilder = $dataGrid->prepareQueryBuilder();

    $sql = $queryBuilder->toRawSql();

    // Check for required email fields (using double quotes as in SQLite)
    expect($sql)->toContain('"emails"."id"')
        ->and($sql)->toContain('"emails"."name"')
        ->and($sql)->toContain('"emails"."from"')
        ->and($sql)->toContain('"emails"."subject"')
        ->and($sql)->toContain('"emails"."reply"')
        ->and($sql)->toContain('"emails"."is_read"')
        ->and($sql)->toContain('"emails"."created_at"')
        ->and($sql)->toContain('"emails"."parent_id"')
        ->and($sql)->toContain('"emails"."person_id"')
        ->and($sql)->toContain('"emails"."lead_id"')
        ->and($sql)->toContain('"emails"."activity_id"');
});

test('query includes required joins', function () {
    request()->merge(['route' => 'inbox']);

    $dataGrid = new EmailDataGrid;
    $queryBuilder = $dataGrid->prepareQueryBuilder();

    $sql = $queryBuilder->toRawSql();

    // Check for required JOINs (using double quotes as in SQLite)
    expect($sql)->toContain('left join "email_attachments"')
        ->and($sql)->toContain('left join "email_tags"')
        ->and($sql)->toContain('left join "tags"')
        ->and($sql)->toContain('left join "leads"')
        ->and($sql)->toContain('left join "persons"')
        ->and($sql)->toContain('left join "activities"');
});

test('query uses json contains for folder filtering', function () {
    request()->merge(['route' => 'inbox']);

    $dataGrid = new EmailDataGrid;
    $queryBuilder = $dataGrid->prepareQueryBuilder();

    $sql = $queryBuilder->toRawSql();

    expect($sql)->toContain('JSON_CONTAINS');
    expect($sql)->toContain('folders');
});

test('query includes proper group by', function () {
    request()->merge(['route' => 'inbox']);

    $dataGrid = new EmailDataGrid;
    $queryBuilder = $dataGrid->prepareQueryBuilder();

    $sql = $queryBuilder->toRawSql();

    expect($sql)->toContain('group by "emails"."id"');
});

test('query handles different routes', function () {
    $routes = ['inbox', 'draft', 'sent', 'outbox', 'trash'];

    foreach ($routes as $route) {
        request()->merge(['route' => $route]);

        $dataGrid = new EmailDataGrid;
        $queryBuilder = $dataGrid->prepareQueryBuilder();

        $sql = $queryBuilder->toRawSql();

        // Should contain the route in JSON_CONTAINS
        expect($sql)->toContain('"'.$route.'"');
    }
});

test('columns are properly configured', function () {
    request()->merge(['route' => 'inbox']);

    $dataGrid = new EmailDataGrid;
    $dataGrid->prepareColumns();

    // Test that prepareColumns doesn't throw an exception
    expect(true)->toBeTrue();
});

test('actions are properly configured', function () {
    request()->merge(['route' => 'inbox']);

    $dataGrid = new EmailDataGrid;
    $dataGrid->prepareActions();

    // Test that prepareActions doesn't throw an exception
    expect(true)->toBeTrue();
});

test('mass actions are properly configured', function () {
    request()->merge(['route' => 'inbox']);

    $dataGrid = new EmailDataGrid;
    $dataGrid->prepareMassActions();

    // Test that prepareMassActions doesn't throw an exception
    expect(true)->toBeTrue();
});

test('query uses correct lead name concatenation', function () {
    request()->merge(['route' => 'inbox']);

    $dataGrid = new EmailDataGrid;
    $queryBuilder = $dataGrid->prepareQueryBuilder();

    $sql = $queryBuilder->toRawSql();

    // Should use CONCAT for lead names
    expect($sql)->toContain('CONCAT(leads.first_name, " ", leads.last_name)');
});
