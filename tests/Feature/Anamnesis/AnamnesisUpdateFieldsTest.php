<?php

use App\Models\Anamnesis;
use App\Models\User;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

beforeEach(function () {
    $this->admin = User::factory()->create();
    actingAs($this->admin);
});

/**
 * A fully valid payload for the anamnesis update endpoint. Every yes/no field
 * is required, so tests start from this baseline and override what they need.
 *
 * @return array<string, mixed>
 */
function validAnamnesisPayload(array $overrides = []): array
{
    $yesNoFields = [
        'metals', 'medications', 'glaucoma', 'claustrophobia', 'dormicum',
        'heart_surgery', 'implant', 'surgeries', 'hereditary_heart',
        'hereditary_vascular', 'hereditary_tumors', 'allergies', 'back_problems',
        'heart_problems', 'smoking', 'diabetes', 'infectious_disease',
        'digestive_problems', 'active',
    ];

    // back_problems uses an inverted rule: a "Nee" (0) answer requires a note,
    // so default it to "Ja" (1) to keep the baseline payload valid.
    return array_merge(array_fill_keys($yesNoFields, 0), ['back_problems' => 1], $overrides);
}

function makeAnamnesis(): Anamnesis
{
    $person = Person::factory()->create();
    $lead = Lead::factory()->create();
    $lead->attachPersons([$person->id]);

    return Anamnesis::where('lead_id', $lead->id)->where('person_id', $person->id)->firstOrFail();
}

it('persists the infectious disease answer and its notes', function () {
    $anamnesis = makeAnamnesis();

    put(route('admin.anamnesis.update', $anamnesis->id), validAnamnesisPayload([
        'infectious_disease'       => 1,
        'infectious_disease_notes' => 'Hepatitis B',
    ]))->assertRedirect();

    $anamnesis->refresh();

    expect($anamnesis->infectious_disease)->toBeTrue()
        ->and($anamnesis->infectious_disease_notes)->toBe('Hepatitis B');
});

it('requires a note when the infectious disease answer is yes', function () {
    $anamnesis = makeAnamnesis();

    put(route('admin.anamnesis.update', $anamnesis->id), validAnamnesisPayload([
        'infectious_disease'       => 1,
        'infectious_disease_notes' => '',
    ]))->assertSessionHasErrors('infectious_disease_notes');
});

it('requires an answer for the infectious disease question', function () {
    $anamnesis = makeAnamnesis();

    $payload = validAnamnesisPayload();
    unset($payload['infectious_disease']);

    put(route('admin.anamnesis.update', $anamnesis->id), $payload)
        ->assertSessionHasErrors('infectious_disease');
});

it('shows the infectious disease question and no longer shows the advice field', function () {
    $anamnesis = makeAnamnesis();

    $response = get(route('admin.anamnesis.edit', $anamnesis->id));

    $response->assertOk()
        ->assertSee('Heeft u een infectieziekte (HIV/Hepatitis)?')
        ->assertDontSee('name="advice_notes"', false);
});
