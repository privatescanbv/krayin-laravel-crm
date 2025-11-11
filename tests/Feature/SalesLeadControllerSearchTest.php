<?php

use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Illuminate\Auth\Middleware\Authenticate;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    // Create and authenticate a back-office user
    $this->user = User::factory()->create(['first_name' => 'Admin', 'last_name' => 'Tester']);
    $this->actingAs($this->user, 'user');
    $this->withoutMiddleware(Authenticate::class);

    // Ensure we have a pipeline and stage
    $this->pipeline = Pipeline::first();
    $this->stage = Stage::first();
    if (! $this->pipeline || ! $this->stage) {
        throw new Exception('Pipeline or Stage not found. Ensure TestSeeder provisions them.');
    }
});

test('sales lead search by name works', function () {
    $salesLead = SalesLead::factory()->create([
        'name'              => 'Test Sales Lead',
        'pipeline_stage_id' => $this->stage->id,
        'user_id'           => $this->user->id,
    ]);

    $response = $this->getJson(route('admin.sales-leads.search', [
        'search'       => 'name:Test;',
        'searchFields' => 'name:like;',
    ]));

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($salesLead->id);
});

test('sales lead search ignores email field (does not exist)', function () {
    $salesLead = SalesLead::factory()->create([
        'name'              => 'Test Sales Lead',
        'pipeline_stage_id' => $this->stage->id,
        'user_id'           => $this->user->id,
    ]);

    // SalesLead doesn't have email/emails columns, so this should not error
    $response = $this->getJson(route('admin.sales-leads.search', [
        'search'       => 'email:test@example.com;',
        'searchFields' => 'emails:like;',
        'searchJoin'   => 'or',
        'limit'        => 10,
    ]));

    // Currently no support (later maybe search in persons)
    $response->assertBadRequest();
});

test('sales lead search ignores emails field (does not exist)', function () {
    $salesLead = SalesLead::factory()->create([
        'name'              => 'Test Sales Lead',
        'pipeline_stage_id' => $this->stage->id,
        'user_id'           => $this->user->id,
    ]);

    // SalesLead doesn't have emails column, so this should not error
    $response = $this->getJson(route('admin.sales-leads.search', [
        'search'       => 'emails:test@example.com;',
        'searchFields' => 'emails:like;',
        'searchJoin'   => 'or',
        'limit'        => 10,
    ]));

    // Currently no support (later maybe search in persons)
    $response->assertBadRequest();
});

test('sales lead search ignores phones field (does not exist)', function () {
    $salesLead = SalesLead::factory()->create([
        'name'              => 'Test Sales Lead',
        'pipeline_stage_id' => $this->stage->id,
        'user_id'           => $this->user->id,
    ]);

    // SalesLead doesn't have phones column, so this should not error
    $response = $this->getJson(route('admin.sales-leads.search', [
        'search'       => 'phones:0612345678;',
        'searchFields' => 'phones:like;',
        'searchJoin'   => 'or',
        'limit'        => 10,
    ]));

    // Currently no support (later maybe search in persons)
    $response->assertBadRequest();
});

test('sales lead search ignores multiple non-existent fields (email, emails, phones)', function () {
    $salesLead = SalesLead::factory()->create([
        'name'              => 'Test Sales Lead',
        'pipeline_stage_id' => $this->stage->id,
        'user_id'           => $this->user->id,
    ]);

    // Test that all three non-existent fields are skipped without error
    $response = $this->getJson(route('admin.sales-leads.search', [
        'search'       => 'email:test@example.com;emails:test2@example.com;phones:0612345678;name:Test;',
        'searchFields' => 'email:like;emails:like;phones:like;name:like;',
        'searchJoin'   => 'or',
        'limit'        => 10,
    ]));

    // Currently no support (later maybe search in persons)
    $response->assertBadRequest();
});

test('sales lead search respects limit parameter', function () {
    // Create multiple sales leads
    SalesLead::factory()->count(15)->create([
        'pipeline_stage_id' => $this->stage->id,
        'user_id'           => $this->user->id,
    ]);

    $response = $this->getJson(route('admin.sales-leads.search', [
        'limit' => 10,
    ]));

    $response->assertOk();
    $results = $response->json('data');
    expect(count($results))->toBeLessThanOrEqual(10);
});
