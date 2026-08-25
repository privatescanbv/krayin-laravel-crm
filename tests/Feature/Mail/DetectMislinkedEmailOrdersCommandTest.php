<?php

use App\Console\Commands\DetectMislinkedEmailOrders;
use App\Models\Order;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Email\Repositories\EmailRepository;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
});

test('dry run reports a mismatch without touching it', function () {
    $person = Person::factory()->create();
    $order = Order::factory()->create(); // salesLead has no persons attached — guaranteed mismatch

    $email = app(EmailRepository::class)->create([
        'source'    => 'email',
        'user_type' => 'person',
        'person_id' => $person->id,
        'order_id'  => $order->id,
    ]);

    $this->artisan(DetectMislinkedEmailOrders::class)
        ->expectsOutputToContain((string) $email->id)
        ->assertSuccessful();

    expect($email->fresh()->order_id)->toBe($order->id)
        ->and(Activity::where('additional->email_id', $email->id)->exists())->toBeFalse();
});

test('--fix removes the order link and logs a system activity correction', function () {
    $person = Person::factory()->create();
    $order = Order::factory()->create();

    $email = app(EmailRepository::class)->create([
        'source'    => 'email',
        'user_type' => 'person',
        'person_id' => $person->id,
        'order_id'  => $order->id,
    ]);

    $this->artisan(DetectMislinkedEmailOrders::class, ['--fix' => true])
        ->expectsOutputToContain((string) $email->id)
        ->assertSuccessful();

    expect($email->fresh()->order_id)->toBeNull()
        ->and($email->fresh()->person_id)->toBe($person->id); // person link untouched

    $activity = Activity::where('additional->email_id', $email->id)->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->user_id)->toBeNull()
        ->and($activity->title)->toContain('correctie')
        ->and($activity->additional['field'])->toBe('order_id')
        ->and($activity->additional['old_value'])->toBe($order->id)
        ->and($activity->additional['new_value'])->toBeNull()
        ->and($activity->additional['reason'])->toBe('person_not_on_order');
});

test('a person who genuinely belongs to the order is left alone', function () {
    $person = Person::factory()->create();
    $order = Order::factory()->create();
    $order->salesLead->persons()->attach($person->id);

    $email = app(EmailRepository::class)->create([
        'source'    => 'email',
        'user_type' => 'person',
        'person_id' => $person->id,
        'order_id'  => $order->id,
    ]);

    $this->artisan(DetectMislinkedEmailOrders::class, ['--fix' => true])
        ->expectsOutputToContain('No mismatches found')
        ->assertSuccessful();

    expect($email->fresh()->order_id)->toBe($order->id);
});

test('--id limits the scan to the given email(s)', function () {
    $person = Person::factory()->create();
    $order = Order::factory()->create();

    $mismatch = app(EmailRepository::class)->create([
        'source' => 'email', 'user_type' => 'person', 'person_id' => $person->id, 'order_id' => $order->id,
    ]);
    $otherMismatch = app(EmailRepository::class)->create([
        'source' => 'email', 'user_type' => 'person', 'person_id' => $person->id, 'order_id' => $order->id,
    ]);

    $this->artisan(DetectMislinkedEmailOrders::class, ['--fix' => true, '--id' => [$mismatch->id]])
        ->assertSuccessful();

    expect($mismatch->fresh()->order_id)->toBeNull()
        ->and($otherMismatch->fresh()->order_id)->toBe($order->id);
});
