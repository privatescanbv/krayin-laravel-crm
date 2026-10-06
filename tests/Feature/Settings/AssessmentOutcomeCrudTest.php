<?php

use App\Enums\PipelineStage;
use App\Models\AssessmentOutcome;
use App\Models\SalesLead;
use Database\Seeders\TestSeeder;
use Webkul\Installer\Http\Middleware\CanInstall;
use Webkul\Lead\Models\Lead;

beforeEach(function () {
    test()->withoutMiddleware(CanInstall::class);
    $this->seed(TestSeeder::class);
    $this->actingAs(getDefaultAdmin(), 'user');
});

test('index lists the outcomes in sort order', function () {
    AssessmentOutcome::where('code', 'tlif_2')->update(['sort_order' => 0]);

    $this->get(route('admin.settings.assessment_outcomes.index'))
        ->assertOk()
        ->assertSeeInOrder(['TLIF 2 niv.', 'PTED 1 niv.']);
});

test('reorder stores the dragged order and the dropdowns follow it', function () {
    $ids = AssessmentOutcome::ordered()->pluck('id')->reverse()->values()->all();

    $this->putJson(route('admin.settings.assessment_outcomes.reorder'), ['ids' => $ids])->assertOk();

    expect(AssessmentOutcome::ordered()->pluck('id')->all())->toBe($ids)
        ->and(AssessmentOutcome::find($ids[0])->sort_order)->toBe(1);
});

test('reorder rejects unknown ids', function () {
    $this->putJson(route('admin.settings.assessment_outcomes.reorder'), ['ids' => [999999]])
        ->assertUnprocessable();
});

test('index and forms render', function () {
    $outcome = AssessmentOutcome::factory()->create();

    $this->get(route('admin.settings.assessment_outcomes.index'))->assertOk();
    $this->get(route('admin.settings.assessment_outcomes.create'))->assertOk();
    $this->get(route('admin.settings.assessment_outcomes.edit', $outcome->id))->assertOk()->assertSee($outcome->code);
});

test('creating derives the code from the label', function () {
    $this->post(route('admin.settings.assessment_outcomes.store'), [
        'label'             => 'PRT 1 niv.',
        'is_surgery_advice' => '0',
    ])->assertRedirect(route('admin.settings.assessment_outcomes.index'));

    $this->assertDatabaseHas('assessment_outcomes', [
        'code'              => 'prt_1_niv',
        'label'             => 'PRT 1 niv.',
        'is_surgery_advice' => false,
        'sort_order'        => 13, // appended after the 12 migrated outcomes
    ]);
});

test('a duplicate label is rejected', function () {
    $this->post(route('admin.settings.assessment_outcomes.store'), ['label' => 'PTED 1 niv.'])
        ->assertSessionHasErrors('label');
});

test('updating changes the label but never the code', function () {
    $outcome = AssessmentOutcome::where('code', 'pted_1')->firstOrFail();

    $this->put(route('admin.settings.assessment_outcomes.update', $outcome->id), [
        'code'              => 'hacked',
        'label'             => 'PTED één niveau',
        'is_surgery_advice' => '1',
    ])->assertRedirect(route('admin.settings.assessment_outcomes.index'));

    expect($outcome->fresh())->code->toBe('pted_1')->label->toBe('PTED één niveau');
});

test('an unused outcome can be deleted', function () {
    $outcome = AssessmentOutcome::factory()->create();

    $this->deleteJson(route('admin.settings.assessment_outcomes.delete', $outcome->id))->assertOk();

    $this->assertDatabaseMissing('assessment_outcomes', ['id' => $outcome->id]);
});

test('an outcome used by a sales lead cannot be deleted', function () {
    $outcome = AssessmentOutcome::factory()->create();
    SalesLead::create([
        'name'               => 'Hernia sales',
        'lead_id'            => Lead::factory()->create()->id,
        'pipeline_stage_id'  => PipelineStage::SALES_ASSESSMENT_DONE_HERNIA->id(),
        'user_id'            => getDefaultAdmin()->id,
        'assessment_outcome' => $outcome->code,
    ]);

    $this->deleteJson(route('admin.settings.assessment_outcomes.delete', $outcome->id))->assertStatus(400);

    $this->assertDatabaseHas('assessment_outcomes', ['id' => $outcome->id]);
});
