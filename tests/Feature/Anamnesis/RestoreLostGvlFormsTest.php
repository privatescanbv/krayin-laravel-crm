<?php

use App\Enums\FormStatus;
use App\Models\Anamnesis;
use App\Models\AnamnesisGvlForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

uses(RefreshDatabase::class);

function gvlAuditActivity(Lead $lead, Person $person, string $anamnesisId, string $verb): Activity
{
    return Activity::create([
        'type'       => 'system',
        'is_done'    => 1,
        'title'      => "GVL {$verb}: {$person->name}",
        'lead_id'    => $lead->id,
        'additional' => [
            'gvl_form_id'     => '555',
            'gvl_form_type'   => 'privatescan',
            'anamnesis_id'    => $anamnesisId,
            'anamnesis_level' => 'lead',
            'person_id'       => $person->id,
        ],
    ]);
}

test('it reattaches a completed gvl form whose anamnesis was deleted by a merge', function () {
    $lead = Lead::factory()->create();
    $person = Person::factory()->create();
    $survivor = Anamnesis::factory()->create(['lead_id' => $lead->id, 'person_id' => $person->id]);

    $deletedAnamnesisId = (string) Str::uuid();
    gvlAuditActivity($lead, $person, $deletedAnamnesisId, 'aangemaakt');
    gvlAuditActivity($lead, $person, $deletedAnamnesisId, 'afgerond');

    $this->artisan('gvl:restore-lost-forms', ['--dry-run' => true])->assertSuccessful();
    expect(AnamnesisGvlForm::count())->toBe(0);

    $this->artisan('gvl:restore-lost-forms')->assertSuccessful();

    $form = AnamnesisGvlForm::where('gvl_form_id', '555')->sole();

    expect($form->anamnesis_id)->toBe($survivor->id)
        ->and($form->gvl_form_status)->toBe(FormStatus::Completed)
        ->and($form->completed_at)->not->toBeNull();

    // Idempotent: a second run finds nothing to restore.
    $this->artisan('gvl:restore-lost-forms')->assertSuccessful();
    expect(AnamnesisGvlForm::count())->toBe(1);
});

test('it leaves a form alone when its anamnesis still exists', function () {
    $lead = Lead::factory()->create();
    $person = Person::factory()->create();
    $anamnesis = Anamnesis::factory()->create(['lead_id' => $lead->id, 'person_id' => $person->id]);

    gvlAuditActivity($lead, $person, $anamnesis->id, 'afgerond');

    $this->artisan('gvl:restore-lost-forms')->assertSuccessful();

    expect(AnamnesisGvlForm::count())->toBe(0);
});
