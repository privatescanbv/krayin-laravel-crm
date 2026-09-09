<?php

namespace Tests\Feature\Persons;

use App\Exceptions\CannotMergePersonWithPortalException;
use App\Models\Address;
use App\Models\Anamnesis;
use App\Models\Clinic;
use App\Models\Inkoop\InkoopInvoice;
use App\Models\Inkoop\InkoopPerson;
use App\Models\Order;
use App\Models\PatientMessage;
use App\Models\PatientNotification;
use App\Models\SalesLead;
use App\Services\PersonKeycloakService;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Email\Enums\EmailFolderEnum;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\Group;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);
    Person::unsetEventDispatcher();
    Activity::unsetEventDispatcher();
    $this->personRepository = app(PersonRepository::class);
});

test('merging persons transfers lead_persons pivot to primary person', function () {
    $primary = Person::factory()->create(['first_name' => 'Primary', 'last_name' => 'Person']);
    $duplicate = Person::factory()->create(['first_name' => 'Duplicate', 'last_name' => 'Person']);

    $lead = Lead::factory()->create();

    DB::table('lead_persons')->insert([
        'lead_id'   => $lead->id,
        'person_id' => $duplicate->id,
    ]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect(DB::table('lead_persons')->where('lead_id', $lead->id)->where('person_id', $primary->id)->exists())->toBeTrue();
    expect(DB::table('lead_persons')->where('lead_id', $lead->id)->where('person_id', $duplicate->id)->exists())->toBeFalse();
    expect(Person::withTrashed()->find($duplicate->id)->trashed())->toBeTrue();
});

test('merging persons transfers saleslead_persons pivot to primary person', function () {
    $primary = Person::factory()->create(['first_name' => 'Primary', 'last_name' => 'Person']);
    $duplicate = Person::factory()->create(['first_name' => 'Duplicate', 'last_name' => 'Person']);

    $salesLead = SalesLead::factory()->create();

    DB::table('saleslead_persons')->insert([
        'saleslead_id' => $salesLead->id,
        'person_id'    => $duplicate->id,
    ]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect(DB::table('saleslead_persons')->where('saleslead_id', $salesLead->id)->where('person_id', $primary->id)->exists())->toBeTrue();
    expect(DB::table('saleslead_persons')->where('saleslead_id', $salesLead->id)->where('person_id', $duplicate->id)->exists())->toBeFalse();
});

test('merging persons transfers contact_person_id on leads', function () {
    $primary = Person::factory()->create(['first_name' => 'Primary', 'last_name' => 'Person']);
    $duplicate = Person::factory()->create(['first_name' => 'Duplicate', 'last_name' => 'Person']);

    $lead = Lead::factory()->create(['contact_person_id' => $duplicate->id]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect($lead->fresh()->contact_person_id)->toBe($primary->id);
});

test('merging persons transfers contact_person_id on salesleads', function () {
    $primary = Person::factory()->create(['first_name' => 'Primary', 'last_name' => 'Person']);
    $duplicate = Person::factory()->create(['first_name' => 'Duplicate', 'last_name' => 'Person']);

    $salesLead = SalesLead::factory()->create(['contact_person_id' => $duplicate->id]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect($salesLead->fresh()->contact_person_id)->toBe($primary->id);
});

test('merging persons does not create duplicate lead_persons when both already linked', function () {
    $primary = Person::factory()->create(['first_name' => 'Primary', 'last_name' => 'Person']);
    $duplicate = Person::factory()->create(['first_name' => 'Duplicate', 'last_name' => 'Person']);

    $lead = Lead::factory()->create();

    DB::table('lead_persons')->insert(['lead_id' => $lead->id, 'person_id' => $primary->id]);
    DB::table('lead_persons')->insert(['lead_id' => $lead->id, 'person_id' => $duplicate->id]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    $count = DB::table('lead_persons')->where('lead_id', $lead->id)->where('person_id', $primary->id)->count();
    expect($count)->toBe(1);
});

test('merging persons transfers anamnesis rows to primary person', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();
    $lead = Lead::factory()->create();

    $anamnesis = Anamnesis::factory()->create([
        'lead_id'   => $lead->id,
        'person_id' => $duplicate->id,
    ]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect($anamnesis->fresh()->person_id)->toBe($primary->id);
});

test('merging persons keeps newest anamnesis when primary and duplicate share the same lead', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();
    $lead = Lead::factory()->create();

    $olderOnPrimary = Anamnesis::factory()->create([
        'lead_id'    => $lead->id,
        'person_id'  => $primary->id,
        'updated_at' => now()->subDays(2),
    ]);

    $newerOnDuplicate = Anamnesis::factory()->create([
        'lead_id'    => $lead->id,
        'person_id'  => $duplicate->id,
        'updated_at' => now(),
    ]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect(Anamnesis::query()->where('lead_id', $lead->id)->where('person_id', $primary->id)->count())->toBe(1);
    expect(Anamnesis::query()->find($olderOnPrimary->id))->toBeNull();
    expect($newerOnDuplicate->fresh()->person_id)->toBe($primary->id);
});

test('merging persons keeps newest anamnesis when primary and duplicate share the same sales lead', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();
    $salesLead = SalesLead::factory()->create();

    $olderOnPrimary = Anamnesis::factory()->create([
        'lead_id'   => null,
        'sales_id'  => $salesLead->id,
        'person_id' => $primary->id,
        'updated_at'=> now()->subDay(),
    ]);

    $newerOnDuplicate = Anamnesis::factory()->create([
        'lead_id'   => null,
        'sales_id'  => $salesLead->id,
        'person_id' => $duplicate->id,
        'updated_at'=> now(),
    ]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect(Anamnesis::query()->where('sales_id', $salesLead->id)->where('person_id', $primary->id)->count())->toBe(1);
    expect(Anamnesis::query()->find($olderOnPrimary->id))->toBeNull();
    expect($newerOnDuplicate->fresh()->person_id)->toBe($primary->id);
});

test('merging persons transfers patient messages to primary person', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();

    $message = PatientMessage::factory()->create(['person_id' => $duplicate->id]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect($message->fresh()->person_id)->toBe($primary->id);
});

test('merging persons transfers activities with person_id to primary person', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();
    $group = Group::firstOrFail();

    $activity = Activity::query()->create([
        'type'          => 'note',
        'title'         => 'Direct person activity',
        'group_id'      => $group->id,
        'person_id'     => $duplicate->id,
        'schedule_from' => now(),
        'schedule_to'   => now()->addHour(),
        'is_done'       => false,
    ]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect($activity->fresh()->person_id)->toBe($primary->id);
});

test('merging persons skips transferring an activity that duplicates one the primary already has', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();
    $group = Group::firstOrFail();

    $activityData = fn (int $personId) => [
        'type'      => 'note',
        'title'     => 'Gebeld met patiënt',
        'status'    => 'active',
        'group_id'  => $group->id,
        'person_id' => $personId,
    ];

    $primaryMatch = Activity::query()->create($activityData($primary->id));
    $duplicateMatch = Activity::query()->create($activityData($duplicate->id));

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect($duplicateMatch->fresh()->person_id)->toBe($duplicate->id)
        ->and($primaryMatch->fresh()->person_id)->toBe($primary->id)
        ->and(Activity::where('person_id', $primary->id)->where('title', 'Gebeld met patiënt')->count())->toBe(1);
});

test('merging persons transfers email rows to primary person', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();

    $folder = Folder::firstOrCreate(['name' => EmailFolderEnum::INBOX->getFolderName()]);

    $email = Email::create([
        'subject'    => 'Merge test',
        'message_id' => (string) Str::uuid(),
        'source'     => 'system',
        'user_type'  => 'user',
        'is_read'    => 0,
        'folder_id'  => $folder->id,
        'person_id'  => $duplicate->id,
        'reply'      => 'Body',
        'from'       => json_encode(['merge@example.com']),
        'reply_to'   => json_encode(['merge@example.com']),
        'cc'         => json_encode([]),
        'bcc'        => json_encode([]),
    ]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect($email->fresh()->person_id)->toBe($primary->id);
});

test('merging persons transfers person_tags without duplicating tag links', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();
    $user = User::factory()->create();

    $tagId = DB::table('tags')->insertGetId([
        'name'       => 'merge-test-tag',
        'color'      => '#000000',
        'user_id'    => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('person_tags')->insert([
        ['tag_id' => $tagId, 'person_id' => $primary->id],
        ['tag_id' => $tagId, 'person_id' => $duplicate->id],
    ]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    $count = DB::table('person_tags')->where('tag_id', $tagId)->where('person_id', $primary->id)->count();
    expect($count)->toBe(1);
});

test('merging persons transfers activity_portal_persons without duplicate activity links', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();
    $group = Group::firstOrFail();

    $activity = Activity::query()->create([
        'type'          => 'note',
        'title'         => 'Portal activity',
        'group_id'      => $group->id,
        'schedule_from' => now(),
        'schedule_to'   => now()->addHour(),
        'is_done'       => false,
    ]);

    DB::table('activity_portal_persons')->insert([
        ['activity_id' => $activity->id, 'person_id' => $primary->id, 'created_at' => now(), 'updated_at' => now()],
        ['activity_id' => $activity->id, 'person_id' => $duplicate->id, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    $count = DB::table('activity_portal_persons')->where('activity_id', $activity->id)->where('person_id', $primary->id)->count();
    expect($count)->toBe(1);
});

test('merging persons transfers attribute_values with primary winning on conflicts', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();

    $sharedAttributeId = DB::table('attributes')->insertGetId([
        'code'        => 'merge_shared_'.uniqid(),
        'name'        => 'Shared',
        'type'        => 'text',
        'entity_type' => 'persons',
        'is_required' => 0,
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $onlyDuplicateAttributeId = DB::table('attributes')->insertGetId([
        'code'        => 'merge_dup_'.uniqid(),
        'name'        => 'Only Dup',
        'type'        => 'text',
        'entity_type' => 'persons',
        'is_required' => 0,
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    DB::table('attribute_values')->insert([
        ['entity_type' => 'persons', 'entity_id' => $primary->id, 'attribute_id' => $sharedAttributeId, 'text_value' => 'primary-wins'],
        ['entity_type' => 'persons', 'entity_id' => $duplicate->id, 'attribute_id' => $sharedAttributeId, 'text_value' => 'duplicate-loses'],
        ['entity_type' => 'persons', 'entity_id' => $duplicate->id, 'attribute_id' => $onlyDuplicateAttributeId, 'text_value' => 'moved'],
    ]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect(DB::table('attribute_values')->where('entity_type', 'persons')->where('entity_id', $primary->id)->where('attribute_id', $sharedAttributeId)->value('text_value'))
        ->toBe('primary-wins')
        ->and(DB::table('attribute_values')->where('entity_type', 'persons')->where('entity_id', $primary->id)->where('attribute_id', $onlyDuplicateAttributeId)->value('text_value'))
        ->toBe('moved')
        ->and(DB::table('attribute_values')->where('entity_type', 'persons')->where('entity_id', $duplicate->id)->count())
        ->toBe(0);
});

test('merging persons transfers patient notifications to primary person', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();

    $notification = PatientNotification::factory()->create(['patient_id' => $duplicate->id]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect($notification->fresh()->patient_id)->toBe($primary->id);
});

test('merging persons drops false positive pairs that reference the duplicate', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();
    $other = Person::factory()->create();

    DB::table('duplicates_false_positives')->insert([
        'entity_type' => 'person',
        'entity_id_1' => $duplicate->id,
        'entity_id_2' => $other->id,
    ]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect(DB::table('duplicates_false_positives')->count())->toBe(0);
});

test('merging persons keeps newest anamnesis when primary and duplicate share the same order', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();
    $order = Order::factory()->create(['order_number' => 'MRG-'.substr(uniqid(), -5)]);

    $olderOnPrimary = Anamnesis::factory()->create([
        'lead_id'    => null,
        'sales_id'   => null,
        'order_id'   => $order->id,
        'person_id'  => $primary->id,
        'updated_at' => now()->subDay(),
    ]);

    $newerOnDuplicate = Anamnesis::factory()->create([
        'lead_id'    => null,
        'sales_id'   => null,
        'order_id'   => $order->id,
        'person_id'  => $duplicate->id,
        'updated_at' => now(),
    ]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect(Anamnesis::query()->where('order_id', $order->id)->where('person_id', $primary->id)->count())->toBe(1);
    expect(Anamnesis::query()->find($olderOnPrimary->id))->toBeNull();
    expect($newerOnDuplicate->fresh()->person_id)->toBe($primary->id);
});

test('merging persons unions emails and phones instead of replacing them', function () {
    $primary = Person::factory()->create([
        'emails' => [['label' => 'eigen', 'value' => 'primary@example.com', 'is_default' => true]],
        'phones' => [['label' => 'eigen', 'value' => '+31600000001', 'is_default' => true]],
    ]);

    $duplicate = Person::factory()->create([
        'emails' => [
            ['label' => 'eigen', 'value' => 'primary@example.com', 'is_default' => true],
            ['label' => 'werk', 'value' => 'duplicate@example.com', 'is_default' => false],
        ],
        'phones' => [['label' => 'werk', 'value' => '+31600000002', 'is_default' => true]],
    ]);

    $merged = $this->personRepository->mergePersons($primary->id, [$duplicate->id], [
        'emails' => $duplicate->id,
        'phones' => $duplicate->id,
    ]);

    expect(array_column($merged->emails, 'value'))
        ->toBe(['primary@example.com', 'duplicate@example.com'])
        ->and(array_column($merged->phones, 'value'))
        ->toBe(['+31600000001', '+31600000002'])
        ->and(array_column($merged->phones, 'is_default'))->toBe([true, false]);
});

test('merging persons ignores the primary when it is listed as its own duplicate', function () {
    $primary = Person::factory()->create();

    $this->personRepository->mergePersons($primary->id, [$primary->id]);

    expect(Person::find($primary->id))->not->toBeNull();
});

test('merging persons records an audit activity the report command can parse', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    $system = Activity::where('person_id', $primary->id)
        ->where('type', 'system')
        ->where('title', 'System: Duplicate Person Removed')
        ->firstOrFail();

    $note = Activity::where('person_id', $primary->id)
        ->where('type', 'note')
        ->where('title', 'Person Merged')
        ->firstOrFail();

    expect($system->comment)->toContain("(ID: {$duplicate->id})")
        ->and($note->comment)->toStartWith("Person #{$duplicate->id} ");
});

test('merging persons adopts address from mapping without treating address as a column', function () {
    $primary = Person::factory()->create(['address_id' => null]);
    $duplicateAddress = Address::factory()->create([
        'street'       => 'Duplicaatstraat',
        'house_number' => '1',
        'postal_code'  => '1234AB',
        'city'         => 'Amsterdam',
    ]);
    $duplicate = Person::factory()->create(['address_id' => $duplicateAddress->id]);

    $merged = $this->personRepository->mergePersons($primary->id, [$duplicate->id], [
        'address' => $duplicate->id,
    ]);

    expect($merged->fresh()->address)->not->toBeNull()
        ->and($merged->fresh()->address->street)->toBe('Duplicaatstraat');
});

test('merging persons refuses to archive a duplicate with a portal account', function () {
    Person::setEventDispatcher(app('events'));
    Config::set('services.keycloak.client_id', 'test-client');

    $keycloakService = Mockery::mock(PersonKeycloakService::class);
    $keycloakService->shouldNotReceive('delete');
    $this->app->instance(PersonKeycloakService::class, $keycloakService);

    $primary = Person::factory()->create(['keycloak_user_id' => null]);
    $duplicate = Person::factory()->create(['keycloak_user_id' => 'kc-from-duplicate']);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);
})->throws(CannotMergePersonWithPortalException::class);

test('merging persons refuses when both primary and duplicate have a portal account', function () {
    Person::setEventDispatcher(app('events'));
    Config::set('services.keycloak.client_id', 'test-client');

    $keycloakService = Mockery::mock(PersonKeycloakService::class);
    $keycloakService->shouldNotReceive('delete');
    $this->app->instance(PersonKeycloakService::class, $keycloakService);

    $primary = Person::factory()->create(['keycloak_user_id' => 'kc-primary']);
    $duplicate = Person::factory()->create(['keycloak_user_id' => 'kc-duplicate']);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);
})->throws(CannotMergePersonWithPortalException::class);

test('merging persons keeps the primary portal account when the duplicate has none', function () {
    Person::setEventDispatcher(app('events'));
    Config::set('services.keycloak.client_id', 'test-client');

    $keycloakService = Mockery::mock(PersonKeycloakService::class);
    $keycloakService->shouldNotReceive('delete');
    $this->app->instance(PersonKeycloakService::class, $keycloakService);

    $primary = Person::factory()->create(['keycloak_user_id' => 'kc-primary']);
    $duplicate = Person::factory()->create(['keycloak_user_id' => null]);

    $merged = $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect($merged->fresh()->keycloak_user_id)->toBe('kc-primary')
        ->and(Person::withTrashed()->find($duplicate->id)->trashed())->toBeTrue();
});

test('merging persons re-points inkoop_persons crm_id to the primary person', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();

    $clinic = Clinic::factory()->create();
    $invoice = InkoopInvoice::create([
        'clinic_id' => $clinic->id,
        'pdf_path'  => 'test/merge.pdf',
    ]);

    $inkoopPerson = InkoopPerson::create([
        'clinic_id'  => $clinic->id,
        'invoice_id' => $invoice->id,
        'firstname'  => 'Ritske',
        'lastname'   => 'Clewits',
        'crm_id'     => (string) $duplicate->id,
    ]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect($inkoopPerson->fresh()->crm_id)->toBe((string) $primary->id);
});

test('merging persons skips inkoop_persons crm_id when the primary is already linked on that invoice', function () {
    $primary = Person::factory()->create();
    $duplicate = Person::factory()->create();

    $clinic = Clinic::factory()->create();
    $sharedInvoice = InkoopInvoice::create([
        'clinic_id' => $clinic->id,
        'pdf_path'  => 'test/shared.pdf',
    ]);
    $otherInvoice = InkoopInvoice::create([
        'clinic_id' => $clinic->id,
        'pdf_path'  => 'test/other.pdf',
    ]);

    InkoopPerson::create([
        'clinic_id'  => $clinic->id,
        'invoice_id' => $sharedInvoice->id,
        'firstname'  => 'Primary',
        'lastname'   => 'Link',
        'crm_id'     => (string) $primary->id,
    ]);

    $colliding = InkoopPerson::create([
        'clinic_id'  => $clinic->id,
        'invoice_id' => $sharedInvoice->id,
        'firstname'  => 'Duplicate',
        'lastname'   => 'Link',
        'crm_id'     => (string) $duplicate->id,
    ]);

    $transferable = InkoopPerson::create([
        'clinic_id'  => $clinic->id,
        'invoice_id' => $otherInvoice->id,
        'firstname'  => 'Other',
        'lastname'   => 'Invoice',
        'crm_id'     => (string) $duplicate->id,
    ]);

    $this->personRepository->mergePersons($primary->id, [$duplicate->id]);

    expect($colliding->fresh()->crm_id)->toBe((string) $duplicate->id)
        ->and($transferable->fresh()->crm_id)->toBe((string) $primary->id);
});
