<?php

use App\Enums\PipelineStage;
use App\Models\AssessmentOutcome;
use App\Models\Department;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Lead\Models\Lead;

beforeEach(function (): void {
    test()->withoutMiddleware(CanInstall::class);
    $this->seed(TestSeeder::class);
    $this->actingAs(getDefaultAdmin(), 'user');
});

function herniaSales(PipelineStage $stage, ?string $outcome = null): SalesLead
{
    $department = Department::firstOrCreate(['name' => 'Herniapoli']);
    $lead = Lead::factory()->create(['department_id' => $department->id]);

    return SalesLead::create([
        'name'               => 'Hernia sales',
        'lead_id'            => $lead->id,
        'pipeline_stage_id'  => $stage->id(),
        'department_id'      => $department->id,
        'user_id'            => getDefaultAdmin()->id,
        'assessment_outcome' => $outcome,
    ]);
}

test('moving to Beoordeling gereed without an outcome is rejected', function (): void {
    $sales = herniaSales(PipelineStage::SALES_DOCTOR_ASSESSMENT_HERNIA);

    $this->putJson(route('admin.sales-leads.stage.update', $sales->id), [
        'lead_pipeline_stage_id' => PipelineStage::SALES_ASSESSMENT_DONE_HERNIA->id(),
    ])->assertUnprocessable()->assertJsonValidationErrors('assessment_outcome');

    expect($sales->fresh()->pipeline_stage_id)->toBe(PipelineStage::SALES_DOCTOR_ASSESSMENT_HERNIA->id());
});

test('the outcome can be given together with the stage change', function (): void {
    $sales = herniaSales(PipelineStage::SALES_DOCTOR_ASSESSMENT_HERNIA);

    $this->putJson(route('admin.sales-leads.stage.update', $sales->id), [
        'lead_pipeline_stage_id' => PipelineStage::SALES_ASSESSMENT_DONE_HERNIA->id(),
        'assessment_outcome'     => 'pted_1',
    ])->assertOk();

    expect($sales->fresh())
        ->pipeline_stage_id->toBe(PipelineStage::SALES_ASSESSMENT_DONE_HERNIA->id())
        ->assessment_outcome->toBe('pted_1');
});

test('a sales with an outcome can move on to later stages', function (): void {
    $sales = herniaSales(PipelineStage::SALES_ASSESSMENT_DONE_HERNIA, 'geen_op_indicatie');

    $this->putJson(route('admin.sales-leads.stage.update', $sales->id), [
        'lead_pipeline_stage_id' => PipelineStage::SALES_TREATMENT_PLANNED_HERNIA->id(),
    ])->assertOk();
});

test('stages before the assessment and lost do not require an outcome', function (PipelineStage $target): void {
    $sales = herniaSales(PipelineStage::SALES_DOCTOR_ASSESSMENT_HERNIA);

    $this->putJson(route('admin.sales-leads.stage.update', $sales->id), [
        'lead_pipeline_stage_id' => $target->id(),
        'lost_reason'            => 'prijs',
    ])->assertOk();
})->with([
    'onderzoek via privatescan' => PipelineStage::SALES_ORDER_PREVENTIE_HERNIA,
    'niet succesvol afgerond'   => PipelineStage::SALES_COMPLETE_NOT_SUCCESSFULLY_HERNIA,
]);

test('an invalid outcome value is rejected', function (): void {
    $sales = herniaSales(PipelineStage::SALES_DOCTOR_ASSESSMENT_HERNIA);

    $this->putJson(route('admin.sales-leads.stage.update', $sales->id), [
        'lead_pipeline_stage_id' => PipelineStage::SALES_ASSESSMENT_DONE_HERNIA->id(),
        'assessment_outcome'     => 'bestaat_niet',
    ])->assertUnprocessable()->assertJsonValidationErrors('assessment_outcome');
});

test('editing an existing sales without outcome in a later stage keeps working', function (): void {
    $sales = herniaSales(PipelineStage::SALES_PATIENT_REFLECTION_TIME_HERNIA);

    $this->put(route('admin.sales-leads.update', $sales->id), [
        'name'       => 'Nieuwe naam',
        'person_ids' => [$sales->lead->persons()->first()?->id ?? Person::factory()->create()->id],
    ])->assertRedirect();

    expect($sales->fresh()->name)->toBe('Nieuwe naam');
});

test('changing the outcome is logged in the logboek with labels', function (): void {
    $sales = herniaSales(PipelineStage::SALES_ASSESSMENT_DONE_HERNIA, 'micro_1');

    $sales->update(['assessment_outcome' => 'acdf_2']);

    $activity = Activity::where('sales_lead_id', $sales->id)->where('title', 'Uitkomst beoordeling gewijzigd')->firstOrFail();

    expect($activity->additional['old']['label'])->toBe('Mikro 1 niv.')
        ->and($activity->additional['new']['label'])->toBe('ACDF 2 niv.');
});

test('the edit form shows the outcome field only for Herniapoli', function (): void {
    $hernia = herniaSales(PipelineStage::SALES_DOCTOR_ASSESSMENT_HERNIA);
    $privatescan = SalesLead::create([
        'name'              => 'PS sales',
        'lead_id'           => Lead::factory()->create(['department_id' => Department::firstOrCreate(['name' => 'Privatescan'])->id])->id,
        'pipeline_stage_id' => PipelineStage::SALES_IN_BEHANDELING->id(),
        'user_id'           => getDefaultAdmin()->id,
    ]);

    $this->get(route('admin.sales-leads.edit', $hernia->id))->assertOk()->assertSee('Uitkomst beoordeling')->assertSee('Geen OP indicatie');
    $this->get(route('admin.sales-leads.edit', $privatescan->id))->assertOk()->assertDontSee('Uitkomst beoordeling');
});

test('the migrated outcomes keep surgery advice off for no-surgery and injections', function (): void {
    expect(AssessmentOutcome::where('code', 'pted_1')->value('is_surgery_advice'))->toBeTrue()
        ->and(AssessmentOutcome::where('code', 'geen_op_indicatie')->value('is_surgery_advice'))->toBeFalse()
        ->and(AssessmentOutcome::where('code', 'injecties_infiltraties')->value('is_surgery_advice'))->toBeFalse();
});

test('an outcome added in settings can be chosen and is shown', function (): void {
    AssessmentOutcome::factory()->create(['code' => 'prt', 'label' => 'PRT behandeling']);
    $sales = herniaSales(PipelineStage::SALES_DOCTOR_ASSESSMENT_HERNIA);

    $this->get(route('admin.sales-leads.edit', $sales->id))->assertOk()->assertSee('PRT behandeling');

    $this->putJson(route('admin.sales-leads.stage.update', $sales->id), [
        'lead_pipeline_stage_id' => PipelineStage::SALES_ASSESSMENT_DONE_HERNIA->id(),
        'assessment_outcome'     => 'prt',
    ])->assertOk();

    $this->get(route('admin.sales-leads.view', $sales->id))->assertOk()->assertSee('PRT behandeling');
});

test('the sales view shows the outcome for Herniapoli', function (): void {
    $sales = herniaSales(PipelineStage::SALES_ASSESSMENT_DONE_HERNIA, 'micro_2');

    $this->get(route('admin.sales-leads.view', $sales->id))
        ->assertOk()
        ->assertSee('Uitkomst beoordeling')
        ->assertSee('Mikro 2 niv.');
});

test('the kanban payload carries the outcome so the board knows when to ask for it', function (): void {
    $sales = herniaSales(PipelineStage::SALES_ASSESSMENT_DONE_HERNIA, 'tlif_1');

    $response = $this->getJson(route('admin.sales-leads.get', [
        'pipeline_id'       => $sales->stage->lead_pipeline_id,
        'pipeline_stage_id' => $sales->pipeline_stage_id,
    ]))->assertOk();

    $card = collect($response->json())->flatMap(fn ($stage) => $stage['leads']['data'] ?? [])->firstWhere('id', $sales->id);

    expect($card['assessment_outcome'])->toBe('tlif_1');
});

test('a new sales does not require additional research by default', function (): void {
    expect(herniaSales(PipelineStage::SALES_DOCTOR_ASSESSMENT_HERNIA)->fresh()->additional_research_required)->toBeFalse();
});

test('with additional research the outcome may stay empty up to Gepland voor aanvullend onderzoek', function (PipelineStage $target): void {
    $sales = herniaSales(PipelineStage::SALES_DOCTOR_ASSESSMENT_HERNIA);

    $this->putJson(route('admin.sales-leads.stage.update', $sales->id), [
        'lead_pipeline_stage_id'       => $target->id(),
        'additional_research_required' => 1,
    ])->assertOk();

    expect($sales->fresh())
        ->pipeline_stage_id->toBe($target->id())
        ->additional_research_required->toBeTrue()
        ->assessment_outcome->toBeNull();
})->with([
    'beoordeling gereed'                => PipelineStage::SALES_ASSESSMENT_DONE_HERNIA,
    'patient bedenktijd'                => PipelineStage::SALES_PATIENT_REFLECTION_TIME_HERNIA,
    'gepland voor aanvullend onderzoek' => PipelineStage::SALES_PLANNED_FOR_ADDITIONAL_RESEARCH_HERNIA,
]);

test('after Gepland voor aanvullend onderzoek additional research does not waive the outcome', function (): void {
    $sales = herniaSales(PipelineStage::SALES_PLANNED_FOR_ADDITIONAL_RESEARCH_HERNIA);
    $sales->update(['additional_research_required' => true]);

    $this->putJson(route('admin.sales-leads.stage.update', $sales->id), [
        'lead_pipeline_stage_id'       => PipelineStage::SALES_WAIT_HEALTH_INSURER_HERNIA->id(),
        'additional_research_required' => 1,
    ])->assertUnprocessable()->assertJsonValidationErrors('assessment_outcome');

    expect($sales->fresh()->pipeline_stage_id)->toBe(PipelineStage::SALES_PLANNED_FOR_ADDITIONAL_RESEARCH_HERNIA->id());
});

test('moving past Gepland voor aanvullend onderzoek resets additional research to nee', function (): void {
    $outcome = AssessmentOutcome::ordered()->firstOrFail()->code;
    $sales = herniaSales(PipelineStage::SALES_PLANNED_FOR_ADDITIONAL_RESEARCH_HERNIA, $outcome);
    $sales->update(['additional_research_required' => true]);

    $this->putJson(route('admin.sales-leads.stage.update', $sales->id), [
        'lead_pipeline_stage_id' => PipelineStage::SALES_WAIT_HEALTH_INSURER_HERNIA->id(),
    ])->assertOk();

    expect($sales->fresh()->additional_research_required)->toBeFalse();

    $activity = Activity::where('sales_lead_id', $sales->id)->where('title', 'Aanvullend onderzoek vereist gewijzigd')->latest('id')->firstOrFail();

    expect($activity->additional['old']['label'])->toBe('Ja')
        ->and($activity->additional['new']['label'])->toBe('Nee');
});

test('the edit form saves additional research and keeps it nee in later stages', function (): void {
    $personId = Person::factory()->create()->id;
    $early = herniaSales(PipelineStage::SALES_PATIENT_REFLECTION_TIME_HERNIA);
    $late = herniaSales(PipelineStage::SALES_TREATMENT_PLANNED_HERNIA, AssessmentOutcome::ordered()->firstOrFail()->code);

    foreach ([$early, $late] as $sales) {
        $this->put(route('admin.sales-leads.update', $sales->id), [
            'name'                         => 'Hernia sales',
            'person_ids'                   => [$personId],
            'additional_research_required' => '1',
        ])->assertRedirect();
    }

    expect($early->fresh()->additional_research_required)->toBeTrue()
        ->and($late->fresh()->additional_research_required)->toBeFalse();
});

test('the kanban payload carries the additional research flag', function (): void {
    $sales = herniaSales(PipelineStage::SALES_PLANNED_FOR_ADDITIONAL_RESEARCH_HERNIA);
    $sales->update(['additional_research_required' => true]);

    $response = $this->getJson(route('admin.sales-leads.get', [
        'pipeline_id'       => $sales->stage->lead_pipeline_id,
        'pipeline_stage_id' => $sales->pipeline_stage_id,
    ]))->assertOk();

    $card = collect($response->json())->flatMap(fn ($stage) => $stage['leads']['data'] ?? [])->firstWhere('id', $sales->id);

    expect($card['additional_research_required'])->toBeTrue();
});
