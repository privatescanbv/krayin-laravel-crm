<?php

use App\Models\Address;
use App\Models\Anamnesis;
use App\Models\AnamnesisGvlForm;
use App\Models\SalesLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Email\Models\Email;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Tag\Models\Tag;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->leadRepository = app(LeadRepository::class);
});

test('it transfers activities from the duplicate to the primary lead', function () {
    $primaryLead = Lead::factory()->create();
    $duplicateLead = Lead::factory()->create();

    $note = Activity::create(['title' => 'Gebeld met patiënt', 'type' => 'note', 'lead_id' => $duplicateLead->id]);
    $task = Activity::create(['title' => 'Terugbellen', 'type' => 'task', 'lead_id' => $duplicateLead->id]);

    $this->leadRepository->mergeLeads($primaryLead->id, [$duplicateLead->id]);

    expect($note->fresh()->lead_id)->toBe($primaryLead->id)
        ->and($task->fresh()->lead_id)->toBe($primaryLead->id)
        ->and(Activity::where('lead_id', $duplicateLead->id)->count())->toBe(0);
});

test('it skips transferring an activity that duplicates one the primary already has', function () {
    $primaryLead = Lead::factory()->create();
    $duplicateLead = Lead::factory()->create();

    $primaryMatch = Activity::create(['title' => 'Gebeld met patiënt', 'type' => 'note', 'status' => 'active', 'lead_id' => $primaryLead->id]);
    $duplicateMatch = Activity::create(['title' => 'Gebeld met patiënt', 'type' => 'note', 'status' => 'active', 'lead_id' => $duplicateLead->id]);
    $unique = Activity::create(['title' => 'Terugbellen', 'type' => 'task', 'lead_id' => $duplicateLead->id]);

    $this->leadRepository->mergeLeads($primaryLead->id, [$duplicateLead->id]);

    // The skipped activity was never re-pointed to the primary, so it goes away with the
    // archived duplicate lead (Lead's LogsActivity trait deletes a lead's own activities on
    // delete) instead of surfacing twice on the primary.
    expect($unique->fresh()->lead_id)->toBe($primaryLead->id)
        ->and(Activity::find($duplicateMatch->id))->toBeNull()
        ->and($primaryMatch->fresh()->lead_id)->toBe($primaryLead->id)
        ->and(Activity::where('lead_id', $primaryLead->id)->where('title', 'Gebeld met patiënt')->count())->toBe(1);
});

test('it merges linked persons without violating the unique index', function () {
    $primaryLead = Lead::factory()->create();
    $duplicateLead = Lead::factory()->create();

    $shared = Person::factory()->create();
    $onlyOnDuplicate = Person::factory()->create();

    $primaryLead->attachPersons([$shared->id]);
    $duplicateLead->attachPersons([$shared->id, $onlyOnDuplicate->id]);

    // Both leads got an empty anamnesis for $shared on linking: dropped without asking.
    $this->leadRepository->mergeLeads($primaryLead->id, [$duplicateLead->id]);

    expect($primaryLead->persons()->pluck('persons.id')->sort()->values()->all())
        ->toBe(collect([$shared->id, $onlyOnDuplicate->id])->sort()->values()->all())
        ->and(DB::table('lead_persons')->where('lead_id', $duplicateLead->id)->count())->toBe(0);
});

test('it keeps the anamnesis the user chose when both leads have one for the same person', function () {
    $primaryLead = Lead::factory()->create();
    $duplicateLead = Lead::factory()->create();

    $shared = Person::factory()->create();
    $onlyOnDuplicate = Person::factory()->create();

    $withGvl = Anamnesis::factory()->create([
        'lead_id'    => $primaryLead->id,
        'person_id'  => $shared->id,
        'updated_at' => now()->subYear(),
    ]);
    AnamnesisGvlForm::create(['anamnesis_id' => $withGvl->id, 'gvl_form_id' => '123', 'gvl_form_status' => 'completed']);

    $newerEmpty = Anamnesis::factory()->create([
        'lead_id'    => $duplicateLead->id,
        'person_id'  => $shared->id,
        'updated_at' => now(),
    ]);

    $other = Anamnesis::factory()->create([
        'lead_id'   => $duplicateLead->id,
        'person_id' => $onlyOnDuplicate->id,
    ]);

    $this->leadRepository->mergeLeads($primaryLead->id, [$duplicateLead->id], [], [$shared->id => $withGvl->id]);

    $log = Activity::where('lead_id', $primaryLead->id)->where('title', 'like', 'Anamnese verwijderd%')->sole();

    expect(Anamnesis::where('lead_id', $primaryLead->id)->where('person_id', $shared->id)->pluck('id')->all())
        ->toBe([$withGvl->id])
        ->and(AnamnesisGvlForm::where('anamnesis_id', $withGvl->id)->count())->toBe(1)
        ->and(Anamnesis::find($newerEmpty->id))->toBeNull()
        ->and($log->additional['anamnesis_id'])->toBe($newerEmpty->id)
        ->and($other->fresh()->lead_id)->toBe($primaryLead->id);
});

test('it drops an empty anamnesis without asking and keeps the one with content', function () {
    $primaryLead = Lead::factory()->create();
    $duplicateLead = Lead::factory()->create();
    $person = Person::factory()->create();

    // Only column defaults, like the anamnesis created when a person is linked to a lead.
    $empty = Anamnesis::factory()->create(array_merge(
        array_fill_keys(array_keys(Anamnesis::getFieldsToCompare()), null),
        ['lead_id' => $primaryLead->id, 'person_id' => $person->id, 'name' => 'Leeg', 'back_problems' => 1, 'spijsverteringsklachten' => 0],
    ));

    $filled = Anamnesis::factory()->create(['lead_id' => $duplicateLead->id, 'person_id' => $person->id]);

    $this->leadRepository->mergeLeads($primaryLead->id, [$duplicateLead->id]);

    expect($empty->fresh())->toBeNull()
        ->and($filled->fresh()->lead_id)->toBe($primaryLead->id)
        ->and(Activity::where('title', 'like', 'Anamnese verwijderd%')->count())->toBe(0);
});

test('it resolves each shared person separately when both leads have the same two persons', function () {
    $primaryLead = Lead::factory()->create();
    $duplicateLead = Lead::factory()->create();
    $personA = Person::factory()->create();
    $personB = Person::factory()->create();

    $primaryLead->attachPersons([$personA->id, $personB->id]);
    $duplicateLead->attachPersons([$personA->id, $personB->id]);

    // A: content on both leads, so the user has to choose. B: both still empty from linking.
    $keepA = Anamnesis::where('lead_id', $duplicateLead->id)->where('person_id', $personA->id)->sole();
    $keepA->update(['height' => 180]);
    Anamnesis::where('lead_id', $primaryLead->id)->where('person_id', $personA->id)->update(['weight' => 80]);

    expect(fn () => $this->leadRepository->mergeLeads($primaryLead->id, [$duplicateLead->id]))
        ->toThrow(Exception::class, 'Kies welke anamnese');

    $this->leadRepository->mergeLeads($primaryLead->id, [$duplicateLead->id], [], [$personA->id => $keepA->id]);

    expect(Anamnesis::where('lead_id', $primaryLead->id)->where('person_id', $personA->id)->pluck('id')->all())->toBe([$keepA->id])
        ->and(Anamnesis::where('lead_id', $primaryLead->id)->where('person_id', $personB->id)->count())->toBe(1)
        ->and(Anamnesis::where('lead_id', $duplicateLead->id)->count())->toBe(0)
        ->and(DB::table('lead_persons')->where('lead_id', $primaryLead->id)->count())->toBe(2)
        ->and(Activity::where('lead_id', $primaryLead->id)->where('title', 'like', 'Anamnese verwijderd%')->count())->toBe(1);
});

test('it refuses to merge when an anamnesis conflict has no choice', function (?string $choice) {
    $primaryLead = Lead::factory()->create();
    $duplicateLead = Lead::factory()->create();
    $person = Person::factory()->create();

    $primaryAnamnesis = Anamnesis::factory()->create(['lead_id' => $primaryLead->id, 'person_id' => $person->id]);
    $duplicateAnamnesis = Anamnesis::factory()->create(['lead_id' => $duplicateLead->id, 'person_id' => $person->id]);

    $choices = $choice === null ? [] : [$person->id => $choice];

    expect(fn () => $this->leadRepository->mergeLeads($primaryLead->id, [$duplicateLead->id], [], $choices))
        ->toThrow(Exception::class, 'Kies welke anamnese');

    expect($primaryAnamnesis->fresh())->not->toBeNull()
        ->and($duplicateAnamnesis->fresh())->not->toBeNull()
        ->and(Lead::find($duplicateLead->id))->not->toBeNull();
})->with([
    'no choice'      => [null],
    'unknown choice' => [(string) Str::uuid()],
]);

test('it refuses to merge a duplicate that already has a sales lead', function () {
    $primaryLead = Lead::factory()->create();
    $duplicateLead = Lead::factory()->create();

    SalesLead::factory()->create(['lead_id' => $duplicateLead->id]);

    expect(fn () => $this->leadRepository->mergeLeads($primaryLead->id, [$duplicateLead->id]))
        ->toThrow(Exception::class);

    // Nothing may have happened: the guard runs before the transaction starts.
    expect(Lead::find($duplicateLead->id))->not->toBeNull()
        ->and(Activity::where('lead_id', $primaryLead->id)->where('title', 'Lead Merged')->count())->toBe(0);
});

test('it records an audit activity the repair command can parse', function () {
    $primaryLead = Lead::factory()->create();
    $duplicateLead = Lead::factory()->create();

    $this->leadRepository->mergeLeads($primaryLead->id, [$duplicateLead->id]);

    $system = Activity::where('lead_id', $primaryLead->id)
        ->where('type', 'system')
        ->where('title', 'System: Duplicate Lead Removed')
        ->firstOrFail();

    $note = Activity::where('lead_id', $primaryLead->id)
        ->where('type', 'note')
        ->where('title', 'Lead Merged')
        ->firstOrFail();

    expect($system->comment)->toContain("(ID: {$duplicateLead->id})")
        ->and($note->comment)->toStartWith("Lead #{$duplicateLead->id} ");
});

test('it deduplicates tags and moves the remaining ones', function () {
    $primaryLead = Lead::factory()->create();
    $duplicateLead = Lead::factory()->create();

    $shared = Tag::create(['name' => 'Spoed', 'user_id' => $primaryLead->user_id]);
    $extra = Tag::create(['name' => 'Terugbellen', 'user_id' => $primaryLead->user_id]);

    $primaryLead->tags()->attach($shared->id);
    $duplicateLead->tags()->attach([$shared->id, $extra->id]);

    $this->leadRepository->mergeLeads($primaryLead->id, [$duplicateLead->id]);

    expect(DB::table('lead_tags')->where('lead_id', $primaryLead->id)->count())->toBe(2)
        ->and(DB::table('lead_tags')->where('lead_id', $duplicateLead->id)->count())->toBe(0);
});

test('it drops false positive pairs that reference the duplicate', function () {
    $primaryLead = Lead::factory()->create();
    $duplicateLead = Lead::factory()->create();
    $otherLead = Lead::factory()->create();

    DB::table('duplicates_false_positives')->insert([
        'entity_type' => 'lead',
        'entity_id_1' => $duplicateLead->id,
        'entity_id_2' => $otherLead->id,
    ]);

    $this->leadRepository->mergeLeads($primaryLead->id, [$duplicateLead->id]);

    expect(DB::table('duplicates_false_positives')->count())->toBe(0);
});

test('it adopts the address of the duplicate when the primary has none', function () {
    $primaryLead = Lead::factory()->create(['address_id' => null]);
    $duplicateLead = Lead::factory()->create([
        'address_id' => Address::factory()->create()->id,
    ]);

    $this->leadRepository->mergeLeads($primaryLead->id, [$duplicateLead->id]);

    expect($primaryLead->fresh()->address_id)->toBe($duplicateLead->address_id);
});

test('it unions emails and phones instead of replacing them', function () {
    $primaryLead = Lead::factory()->create([
        'emails' => [['label' => 'eigen', 'value' => 'primary@example.com', 'is_default' => true]],
        'phones' => [['label' => 'eigen', 'value' => '+31600000001', 'is_default' => true]],
    ]);

    $duplicateLead = Lead::factory()->create([
        'emails' => [
            ['label' => 'eigen', 'value' => 'primary@example.com', 'is_default' => true],
            ['label' => 'werk', 'value' => 'duplicate@example.com', 'is_default' => false],
        ],
        'phones' => [['label' => 'werk', 'value' => '+31600000002', 'is_default' => true]],
    ]);

    $merged = $this->leadRepository->mergeLeads($primaryLead->id, [$duplicateLead->id], [
        'emails' => $duplicateLead->id,
        'phones' => $duplicateLead->id,
    ]);

    expect(array_column($merged->emails, 'value'))
        ->toBe(['primary@example.com', 'duplicate@example.com'])
        ->and(array_column($merged->phones, 'value'))
        ->toBe(['+31600000001', '+31600000002'])
        // Only the primary keeps its default flag.
        ->and(array_column($merged->phones, 'is_default'))->toBe([true, false]);
});

test('it ignores the primary lead when it is listed as its own duplicate', function () {
    $primaryLead = Lead::factory()->create();

    $this->leadRepository->mergeLeads($primaryLead->id, [$primaryLead->id]);

    expect(Lead::find($primaryLead->id))->not->toBeNull();
});

test('it transfers emails and marketing data', function () {
    $primaryLead = Lead::factory()->create();
    $duplicateLead = Lead::factory()->create();

    $email = Email::create([
        'subject'     => 'Vraag over afspraak',
        'source'      => 'test',
        'unique_id'   => (string) Str::uuid(),
        'message_id'  => (string) Str::uuid(),
        'name'        => 'Patiënt',
        'lead_id'     => $duplicateLead->id,
    ]);

    DB::table('lead_marketing_data')->insert([
        'lead_id'    => $duplicateLead->id,
        'key'        => 'utm_source',
        'value'      => 'google',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->leadRepository->mergeLeads($primaryLead->id, [$duplicateLead->id]);

    expect($email->fresh()->lead_id)->toBe($primaryLead->id)
        ->and(DB::table('lead_marketing_data')->where('lead_id', $primaryLead->id)->count())->toBe(1);
});
