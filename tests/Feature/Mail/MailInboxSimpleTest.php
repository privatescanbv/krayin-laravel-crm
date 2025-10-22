<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Admin\DataGrids\Mail\EmailDataGrid;
use Webkul\Email\Models\Email;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'user');
});

test('email datagrid query executes without errors', function () {
    request()->merge(['route' => 'inbox']);

    $dataGrid = new EmailDataGrid;
    $queryBuilder = $dataGrid->prepareQueryBuilder();
    $sql = $queryBuilder->toRawSql();

    expect($sql)->toContain('folders.name')
        ->and($sql)->toContain('inbox');
});

test('email datagrid handles different routes', function () {
    $routes = ['inbox', 'draft', 'sent', 'outbox', 'trash'];

    foreach ($routes as $route) {
        request()->merge(['route' => $route]);

        $dataGrid = new EmailDataGrid;
        $queryBuilder = $dataGrid->prepareQueryBuilder();
        $sql = $queryBuilder->toRawSql();

        expect($sql)->toContain('folders.name')
            ->and($sql)->toContain($route);
    }
});

test('email datagrid doesnt use nonexistent columns', function () {
    request()->merge(['route' => 'inbox']);

    $dataGrid = new EmailDataGrid;
    $queryBuilder = $dataGrid->prepareQueryBuilder();
    $sql = $queryBuilder->toRawSql();

    // Check that we don't use leads.name directly (we use CONCAT instead)
    // But salesleads.name is allowed for sales lead functionality
    expect($sql)->not->toMatch('/\bleads\.name\b/')
        ->and($sql)->not->toContain('folders_inbox')
        ->and($sql)->not->toContain('folders_draft');
});

test('email datagrid uses correct lead name concatenation', function () {
    request()->merge(['route' => 'inbox']);

    $dataGrid = new EmailDataGrid;
    $queryBuilder = $dataGrid->prepareQueryBuilder();
    $sql = $queryBuilder->toRawSql();

    expect($sql)->toContain('CONCAT(leads.first_name, " ", leads.last_name)');
});

test('email datagrid includes required joins', function () {
    request()->merge(['route' => 'inbox']);

    $dataGrid = new EmailDataGrid;
    $queryBuilder = $dataGrid->prepareQueryBuilder();
    $sql = $queryBuilder->toRawSql();

    $requiredJoins = [
        'left join "email_attachments"',
        'left join "email_tags"',
        'left join "tags"',
        'left join "leads"',
        'left join "persons"',
        'left join "activities"',
    ];

    foreach ($requiredJoins as $join) {
        expect($sql)->toContain($join);
    }
});

test('email datagrid columns can be prepared', function () {
    request()->merge(['route' => 'inbox']);

    $dataGrid = new EmailDataGrid;
    $dataGrid->prepareColumns();

    expect(true)->toBeTrue();
});

test('email datagrid actions can be prepared', function () {
    request()->merge(['route' => 'inbox']);

    $dataGrid = new EmailDataGrid;
    $dataGrid->prepareActions();

    expect(true)->toBeTrue();
});

test('email datagrid mass actions can be prepared', function () {
    request()->merge(['route' => 'inbox']);

    $dataGrid = new EmailDataGrid;
    $dataGrid->prepareMassActions();

    expect(true)->toBeTrue();
});

test('email model handles null created_at in time_ago attribute', function () {
    // Create inbox folder first
    $folder = \Webkul\Email\Models\Folder::create(['name' => 'inbox']);

    $email = new Email([
        'subject'    => 'Test Email',
        'from'       => ['test@example.com'],
        'reply'      => 'Test content',
        'folder_id'  => $folder->id,
        'created_at' => null,
    ]);

    expect($email->time_ago)->toBe('Unknown');
});

test('email model time_ago works with valid created_at', function () {
    // Create inbox folder first
    $folder = \Webkul\Email\Models\Folder::create(['name' => 'inbox']);

    $email = new Email([
        'subject'    => 'Test Email',
        'from'       => ['test@example.com'],
        'reply'      => 'Test content',
        'folder_id'  => $folder->id,
        'created_at' => now(),
    ]);

    expect($email->time_ago)->not->toBe('Unknown')
        ->and($email->time_ago)->toBeString();
});

test('mail inbox route loads successfully', function () {
    $response = $this->get('/admin/mail/inbox');
    $response->assertStatus(200);
});
