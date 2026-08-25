<?php

use App\Console\Commands\DetectMislinkedEmailEntities;
use App\Models\Order;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Email\Repositories\EmailRepository;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TestSeeder::class);
    config(['mail.mailboxes' => []]);
});

function makePersonWithEmail(string $address): Person
{
    return Person::factory()->create([
        'emails' => [['value' => $address, 'label' => 'work', 'is_default' => true]],
    ]);
}

test('a mail linked to only one entity is out of scope, even if it would mismatch', function () {
    $order = Order::factory()->create(); // sales lead has no persons — would mismatch if it were even checked
    $email = app(EmailRepository::class)->create([
        'source'    => 'email',
        'user_type' => 'person',
        'from'      => ['email' => 'nobody@example.com', 'name' => null],
        'order_id'  => $order->id,
    ]);

    $this->artisan(DetectMislinkedEmailEntities::class)
        ->doesntExpectOutputToContain((string) $email->id)
        ->assertSuccessful();

    expect($email->fresh()->order_id)->toBe($order->id);
});

test('dry run reports OK for the matching relation and MISMATCH for the wrong one, without touching either', function () {
    $person = makePersonWithEmail('bob@example.com');
    $order = Order::factory()->create(); // salesLead has no persons attached

    $email = app(EmailRepository::class)->create([
        'source'    => 'email',
        'user_type' => 'person',
        'from'      => ['email' => 'bob@example.com', 'name' => 'Bob'],
        'person_id' => $person->id,
        'order_id'  => $order->id,
    ]);

    // Table rows aren't reliably visible to expectsOutputToContain (Symfony's Table writes
    // via raw writeln calls the console test double doesn't mock line-by-line), so assert
    // on the captured buffer directly instead.
    Artisan::call('emails:detect-mislinked-entities');
    $output = Artisan::output();

    expect($output)->toContain((string) $email->id)
        ->and($output)->toContain('OK')
        ->and($output)->toContain('MISMATCH')
        ->and($email->fresh()->person_id)->toBe($person->id)
        ->and($email->fresh()->order_id)->toBe($order->id)
        ->and(Activity::where('additional->email_id', $email->id)->exists())->toBeFalse();

});

test('--fix removes only the mismatching relation and logs a system activity correction', function () {
    $person = makePersonWithEmail('bob@example.com');
    $order = Order::factory()->create();

    $email = app(EmailRepository::class)->create([
        'source'    => 'email',
        'user_type' => 'person',
        'from'      => ['email' => 'bob@example.com', 'name' => 'Bob'],
        'person_id' => $person->id,
        'order_id'  => $order->id,
    ]);

    $this->artisan(DetectMislinkedEmailEntities::class, ['--fix' => true])->assertSuccessful();

    $fresh = $email->fresh();
    expect($fresh->person_id)->toBe($person->id) // the correct relation survives
        ->and($fresh->order_id)->toBeNull(); // only the wrong one is removed

    $activity = Activity::where('additional->email_id', $email->id)->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->user_id)->toBeNull()
        ->and($activity->title)->toContain('correctie')
        ->and($activity->additional['field'])->toBe('order_id')
        ->and($activity->additional['old_value'])->toBe($order->id)
        ->and($activity->additional['checked_address'])->toBe('bob@example.com')
        ->and($activity->additional['reason'])->toBe('address_not_known_to_entity');
});

test('order_id is checked indirectly via the persons attached to its sales lead', function () {
    $person = makePersonWithEmail('bob@example.com');
    $order = Order::factory()->create();
    $order->salesLead->persons()->attach($person->id);

    $email = app(EmailRepository::class)->create([
        'source'    => 'email',
        'user_type' => 'person',
        'from'      => ['email' => 'bob@example.com', 'name' => 'Bob'],
        'person_id' => $person->id,
        'order_id'  => $order->id,
    ]);

    $this->artisan(DetectMislinkedEmailEntities::class, ['--fix' => true])
        ->expectsOutputToContain('No incorrect relations found')
        ->assertSuccessful();

    expect($email->fresh()->order_id)->toBe($order->id);
});

test('an unresolvable address (own mailbox, no external reply-to) is reported but never auto-corrected', function () {
    config(['mail.mailboxes' => ['privatescan' => ['address' => 'crm@privatescan.nl']]]);

    $person = makePersonWithEmail('bob@example.com');
    $order = Order::factory()->create();

    $email = app(EmailRepository::class)->create([
        'source'    => 'email',
        'user_type' => 'person',
        'from'      => null,
        'reply_to'  => [],
        'person_id' => $person->id,
        'order_id'  => $order->id,
    ]);

    $this->artisan(DetectMislinkedEmailEntities::class, ['--fix' => true])
        ->expectsOutputToContain('UNRESOLVABLE')
        ->assertSuccessful();

    expect($email->fresh()->person_id)->toBe($person->id)
        ->and($email->fresh()->order_id)->toBe($order->id)
        ->and(Activity::where('additional->email_id', $email->id)->exists())->toBeFalse();
});

test('the SugarCRM import placeholder sender is ignored, never reported or corrected', function () {
    $person = makePersonWithEmail('bob@example.com');
    $order = Order::factory()->create(); // salesLead has no persons — would mismatch if evaluated

    $email = app(EmailRepository::class)->create([
        'source'    => 'email',
        'user_type' => 'person',
        'from'      => ['email' => 'import@sugarcrm.local', 'name' => 'Import'],
        'person_id' => $person->id,
        'order_id'  => $order->id,
    ]);

    Artisan::call('emails:detect-mislinked-entities', ['--fix' => true]);
    $output = Artisan::output();

    expect($output)->not->toContain('MISMATCH')
        ->and($output)->toContain('Skipped 1 email');

    expect($email->fresh()->order_id)->toBe($order->id)
        ->and(Activity::where('additional->email_id', $email->id)->exists())->toBeFalse();
});

test('--id limits the scan to the given email(s)', function () {
    $person = makePersonWithEmail('bob@example.com');
    $order = Order::factory()->create();

    $target = app(EmailRepository::class)->create([
        'source'    => 'email', 'user_type' => 'person',
        'from'      => ['email' => 'bob@example.com', 'name' => 'Bob'],
        'person_id' => $person->id, 'order_id' => $order->id,
    ]);
    $other = app(EmailRepository::class)->create([
        'source'    => 'email', 'user_type' => 'person',
        'from'      => ['email' => 'bob@example.com', 'name' => 'Bob'],
        'person_id' => $person->id, 'order_id' => $order->id,
    ]);

    $this->artisan(DetectMislinkedEmailEntities::class, ['--fix' => true, '--id' => [$target->id]])
        ->assertSuccessful();

    expect($target->fresh()->order_id)->toBeNull()
        ->and($other->fresh()->order_id)->toBe($order->id);
});
