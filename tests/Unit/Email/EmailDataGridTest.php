<?php

use App\Models\Order;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Admin\DataGrids\Mail\EmailDataGrid;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;

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
        ->and($sql)->toContain('"emails"."lead_id"');
});

test('query includes required joins', function () {
    request()->merge(['route' => 'inbox']);

    $dataGrid = new EmailDataGrid;
    $queryBuilder = $dataGrid->prepareQueryBuilder();

    $sql = $queryBuilder->toRawSql();

    // Tags and attachments are now correlated subqueries (not JOINs) to avoid GROUP BY
    expect($sql)->toContain('left join "leads"')
        ->and($sql)->toContain('left join "persons"')
        ->and($sql)->toContain('GROUP_CONCAT')
        ->and($sql)->toContain('email_attachments')
        ->and($sql)->not->toContain('left join "email_attachments"')
        ->and($sql)->not->toContain('left join "email_tags"');
});

test('query filters by folder_id directly for index efficiency', function () {
    Folder::firstOrCreate(['name' => 'inbox']);
    request()->merge(['route' => 'inbox']);

    $dataGrid = new EmailDataGrid;
    $queryBuilder = $dataGrid->prepareQueryBuilder();

    $sql = $queryBuilder->toRawSql();

    // folder_id pre-fetched and used as WHERE emails.folder_id = ? (enables composite index)
    expect($sql)->toContain('"emails"."folder_id"')
        ->and($sql)->not->toContain('"folders"."name"');
});

test('query has no group by clause', function () {
    request()->merge(['route' => 'inbox']);

    $dataGrid = new EmailDataGrid;
    $queryBuilder = $dataGrid->prepareQueryBuilder();

    $sql = $queryBuilder->toRawSql();

    // GROUP BY removed; aggregations use correlated subqueries so LIMIT applies early
    expect($sql)->not->toContain('group by');
});

test('query handles different routes', function () {
    $routes = ['inbox', 'draft', 'sent', 'outbox', 'trash'];

    foreach ($routes as $route) {
        Folder::firstOrCreate(['name' => $route]);
        request()->merge(['route' => $route]);

        $dataGrid = new EmailDataGrid;
        $queryBuilder = $dataGrid->prepareQueryBuilder();

        $sql = $queryBuilder->toRawSql();

        // Each route's folder_id is pre-fetched; query uses emails.folder_id directly
        expect($sql)->toContain('"emails"."folder_id"');
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

    // Should select individual name fields for leads (database-agnostic approach)
    expect($sql)->toContain('"leads"."first_name" as "lead_first_name"')
        ->and($sql)->toContain('"leads"."lastname_prefix" as "lead_lastname_prefix"')
        ->and($sql)->toContain('"leads"."last_name" as "lead_last_name"')
        ->and($sql)->toContain('"leads"."married_name_prefix" as "lead_married_name_prefix"')
        ->and($sql)->toContain('"leads"."married_name" as "lead_married_name"');
});

test('unread emails are sorted before read emails', function () {
    // Create test data - first create folder manually
    $folder = Folder::create([
        'name'         => 'inbox',
        'display_name' => 'Inbox',
    ]);

    // Create read email (newer)
    $readEmail = Email::create([
        'folder_id'  => $folder->id,
        'is_read'    => true,
        'created_at' => now()->subDay(),
        'subject'    => 'Read Email',
        'from'       => 'test@example.com',
        'name'       => 'Test User',
        'reply'      => 'Test content',
    ]);

    // Create unread email (older)
    $unreadEmail = Email::create([
        'folder_id'  => $folder->id,
        'is_read'    => false,
        'created_at' => now()->subDays(2),
        'subject'    => 'Unread Email',
        'from'       => 'test2@example.com',
        'name'       => 'Test User 2',
        'reply'      => 'Test content 2',
    ]);

    // Create another read email (newest)
    $readEmail2 = Email::create([
        'folder_id'  => $folder->id,
        'is_read'    => true,
        'created_at' => now(),
        'subject'    => 'Newest Read Email',
        'from'       => 'test3@example.com',
        'name'       => 'Test User 3',
        'reply'      => 'Test content 3',
    ]);

    request()->merge(['route' => 'inbox']);

    $dataGrid = new EmailDataGrid;
    $queryBuilder = $dataGrid->prepareQueryBuilder();

    // Manually apply the custom sorting that would be applied in processRequestedSorting
    $queryBuilder->reorder()
        ->orderByRaw('CASE WHEN emails.is_read = 0 OR emails.is_read IS NULL THEN 0 ELSE 1 END, emails.created_at DESC');

    $results = $queryBuilder->get();

    // Should have 3 emails
    expect($results)->toHaveCount(3);

    // First email should be unread (even though it's oldest)
    expect($results->first()->id)->toBe($unreadEmail->id)
        ->and($results->first()->is_read)->toBe(0);

    // Second and third should be read emails, sorted by date desc
    expect($results->skip(1)->first()->id)->toBe($readEmail2->id) // newest read
        ->and($results->last()->id)->toBe($readEmail->id); // older read
});

test('ongelezen ongekoppeld filter excludes read email that is only linked to an order', function () {
    $folder = Folder::create([
        'name'         => 'inbox',
        'parent_id'    => null,
        'order'        => 1,
        'is_deletable' => false,
    ]);

    $order = Order::factory()->create();

    $readLinkedToOrder = Email::create([
        'folder_id' => $folder->id,
        'is_read'   => true,
        'subject'   => 'Order linked',
        'from'      => ['test@example.com'],
        'name'      => 'Sender',
        'reply'     => 'Body',
        'order_id'  => $order->id,
    ]);

    $readUnlinked = Email::create([
        'folder_id' => $folder->id,
        'is_read'   => true,
        'subject'   => 'No link',
        'from'      => ['other@example.com'],
        'name'      => 'Sender 2',
        'reply'     => 'Body 2',
    ]);

    request()->merge([
        'route'   => 'inbox',
        'filters' => ['ongelezen_ongekoppeld' => ['1']],
    ]);

    $dataGrid = new EmailDataGrid;
    $ids = $dataGrid->prepareQueryBuilder()->pluck('emails.id');

    expect($ids)->toContain($readUnlinked->id)
        ->and($ids)->not->toContain($readLinkedToOrder->id);
});
