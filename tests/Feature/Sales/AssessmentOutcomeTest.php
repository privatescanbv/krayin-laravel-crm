<?php

use App\Enums\AssessmentOutcome;
use App\Enums\PipelineStage;
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

function herniaSales(PipelineStage $stage, ?AssessmentOutcome $outcome = null): SalesLead
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
        'assessment_outcome'     => AssessmentOutcome::Pted1->value,
    ])->assertOk();

    expect($sales->fresh())
        ->pipeline_stage_id->toBe(PipelineStage::SALES_ASSESSMENT_DONE_HERNIA->id())
        ->assessment_outcome->toBe(AssessmentOutcome::Pted1);
});

test('a sales with an outcome can move on to later stages', function (): void {
    $sales = herniaSales(PipelineStage::SALES_ASSESSMENT_DONE_HERNIA, AssessmentOutcome::NoSurgery);

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
    $sales = herniaSales(PipelineStage::SALES_ASSESSMENT_DONE_HERNIA, AssessmentOutcome::Micro1);

    $sales->update(['assessment_outcome' => AssessmentOutcome::Acdf2]);

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

    $this->get(route('admin.sales-leads.edit', $hernia->id))->assertOk()->assertSee('Uitkomst beoordeling')->assertSee('Kein OP indikation');
    $this->get(route('admin.sales-leads.edit', $privatescan->id))->assertOk()->assertDontSee('Uitkomst beoordeling');
});

test('surgery advice excludes no-surgery and injections', function (): void {
    expect(AssessmentOutcome::Pted1->isSurgeryAdvice())->toBeTrue()
        ->and(AssessmentOutcome::NoSurgery->isSurgeryAdvice())->toBeFalse()
        ->and(AssessmentOutcome::Injections->isSurgeryAdvice())->toBeFalse();
});

test('the sales view shows the outcome for Herniapoli', function (): void {
    $sales = herniaSales(PipelineStage::SALES_ASSESSMENT_DONE_HERNIA, AssessmentOutcome::Micro2);

    $this->get(route('admin.sales-leads.view', $sales->id))
        ->assertOk()
        ->assertSee('Uitkomst beoordeling')
        ->assertSee('Mikro 2 niv.');
});

test('the kanban payload carries the outcome so the board knows when to ask for it', function (): void {
    $sales = herniaSales(PipelineStage::SALES_ASSESSMENT_DONE_HERNIA, AssessmentOutcome::Tlif1);

    $response = $this->getJson(route('admin.sales-leads.get', [
        'pipeline_id'       => $sales->stage->lead_pipeline_id,
        'pipeline_stage_id' => $sales->pipeline_stage_id,
    ]))->assertOk();

    $card = collect($response->json())->flatMap(fn ($stage) => $stage['leads']['data'] ?? [])->firstWhere('id', $sales->id);

    expect($card['assessment_outcome'])->toBe('tlif_1');
});
