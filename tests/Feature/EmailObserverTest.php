<?php

use App\Models\Order;
use App\Services\Mail\EmailLinkAuditLog;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Email\Repositories\EmailRepository;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

test('auto-linking an email without an authenticated admin logs nothing (absence = automatic)', function () {
    $person = Person::factory()->create();

    $email = app(EmailRepository::class)->create([
        'source'    => 'email',
        'user_type' => 'person',
        'person_id' => $person->id,
    ]);

    // Scope by additional->email_id, not person_id: creating a Person on its own already
    // logs an unrelated "created" activity with person_id set (Person uses LogsActivity).
    expect(Activity::where('additional->email_id', $email->id)->exists())->toBeFalse();
});

test('manually (re)linking an email to an order while authenticated logs it with the acting user', function () {
    $admin = getDefaultAdmin();
    test()->actingAs($admin, 'user');
    $order = Order::factory()->create();

    $email = app(EmailRepository::class)->create([
        'source'    => 'email',
        'user_type' => 'person',
    ]);

    app(EmailRepository::class)->update(['order_id' => $order->id], $email->id);

    $activity = Activity::where('additional->email_id', $email->id)->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->user_id)->toBe($admin->id)
        ->and($activity->order_id)->toBeNull() // not attached to the order's own timeline — email-scoped only
        ->and($activity->title)->toContain('gekoppeld')
        ->and($activity->additional['field'])->toBe('order_id')
        ->and($activity->additional['old_value'])->toBeNull()
        ->and($activity->additional['new_value'])->toBe($order->id);
});

test('unlinking an entity while authenticated logs an ontkoppeld activity carrying the old value', function () {
    $admin = getDefaultAdmin();
    test()->actingAs($admin, 'user');
    $order = Order::factory()->create();

    $email = app(EmailRepository::class)->create([
        'source'    => 'email',
        'user_type' => 'person',
        'order_id'  => $order->id,
    ]);

    app(EmailRepository::class)->update(['order_id' => null], $email->id);

    $activity = Activity::where('additional->email_id', $email->id)
        ->where('title', 'like', '%ontkoppeld%')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->additional['old_value'])->toBe($order->id)
        ->and($activity->additional['new_value'])->toBeNull();
});

test('saving an email without changing any link field logs nothing, even when authenticated', function () {
    $admin = getDefaultAdmin();
    test()->actingAs($admin, 'user');

    $email = app(EmailRepository::class)->create([
        'source'    => 'email',
        'user_type' => 'person',
    ]);

    $countBefore = Activity::count();

    app(EmailRepository::class)->update(['subject' => 'Bijgewerkt onderwerp'], $email->id);

    expect(Activity::count())->toBe($countBefore);
});

test('EmailLinkAuditLog returns only this email\'s log entries, newest first, with the user eager-loaded', function () {
    $admin = getDefaultAdmin();
    test()->actingAs($admin, 'user');
    $order = Order::factory()->create();
    $otherOrder = Order::factory()->create();

    $email = app(EmailRepository::class)->create(['source' => 'email', 'user_type' => 'person']);
    $otherEmail = app(EmailRepository::class)->create(['source' => 'email', 'user_type' => 'person']);

    app(EmailRepository::class)->update(['order_id' => $order->id], $email->id);
    app(EmailRepository::class)->update(['order_id' => $otherOrder->id], $otherEmail->id);
    app(EmailRepository::class)->update(['order_id' => null], $email->id);

    $log = app(EmailLinkAuditLog::class)->forEmail($email->fresh());

    expect($log)->toHaveCount(2)
        ->and($log->first()->additional['new_value'])->toBeNull() // most recent (unlink) first
        ->and($log->last()->additional['new_value'])->toBe($order->id)
        ->and($log->first()->relationLoaded('user'))->toBeTrue()
        ->and($log->pluck('id'))->not->toContain(
            Activity::where('additional->email_id', $otherEmail->id)->value('id')
        );
});
