<?php

use App\Models\Department;
use Database\Seeders\TestSeeder;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->seed(TestSeeder::class);

    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'user');
});

test('create lead page prefills the personal fields from the mail query parameters', function (): void {
    $response = $this->get(route('admin.leads.create', [
        'email'      => 'sender@example.test',
        'first_name' => 'Jan',
        'last_name'  => 'Jansen',
    ]));

    $response->assertOk();
    $response->assertSee('name="first_name"', false);
    $response->assertSee('value="Jan"', false);
    $response->assertSee('value="Jansen"', false);
    $response->assertSee('sender@example.test', false);
});

test('the submit handler never overwrites a field the rendered form already submits', function (): void {
    // Guards the regression where every key of the Vue `formData` object was written
    // over the FormData built from the form, so the prefilled defaults from the mail
    // view always won over whatever the user typed.
    $response = $this->get(route('admin.leads.create', ['first_name' => 'Jan']));

    $response->assertOk();
    $response->assertSee('if (formData.has(key)) return;', false);
});

test('storing a lead uses the submitted values instead of the prefilled defaults', function (): void {
    $departmentId = Department::query()->value('id');
    expect($departmentId)->not->toBeNull();

    $response = $this->post(route('admin.leads.store'), [
        'first_name'    => 'Pieter',
        'last_name'     => 'Vermeulen',
        'department_id' => $departmentId,
        'emails'        => [
            ['value' => 'pieter@example.test', 'label' => 'eigen'],
        ],
    ]);

    $response->assertStatus(302);

    $lead = Lead::query()->latest('id')->firstOrFail();

    expect($lead->first_name)->toBe('Pieter')
        ->and($lead->last_name)->toBe('Vermeulen');
});

test('storing a lead from the mail view links the originating email and keeps the submitted values', function (): void {
    $departmentId = Department::query()->value('id');

    $emailId = DB::table('emails')->insertGetId([
        'subject'    => 'Vraag over een scan',
        'source'     => 'test',
        'user_type'  => 'person',
        'unique_id'  => uniqid('msg-', true),
        'message_id' => uniqid('msg-', true),
        'name'       => 'Jan Jansen',
        'reply_to'   => json_encode(['sender@example.test']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // The mail view prefills first_name=Jan / last_name=Jansen, the user corrects them
    // in the form before saving. The corrections must win.
    $response = $this->post(route('admin.leads.store'), [
        'first_name'    => 'Johan',
        'last_name'     => 'Janssen',
        'department_id' => $departmentId,
        'emails'        => [
            ['value' => 'corrected@example.test', 'label' => 'eigen'],
        ],
        'link_email_id' => $emailId,
    ]);

    $response->assertStatus(302);

    $lead = Lead::query()->latest('id')->firstOrFail();

    expect($lead->first_name)->toBe('Johan')
        ->and($lead->last_name)->toBe('Janssen')
        ->and(DB::table('emails')->where('id', $emailId)->value('lead_id'))->toBe($lead->id);

});
