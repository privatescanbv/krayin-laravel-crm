<?php

use App\Jobs\GenerateAiSummaryJob;
use App\Models\AiFeedback;
use App\Models\AiSummary;
use App\Models\Order;
use App\Models\SalesLead;
use Illuminate\Support\Facades\Queue;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'user');
    $this->lead = Lead::factory()->create(['user_id' => $this->user->id]);
});

test('a user can add edit and remove separate ai feedback', function () {
    $createResponse = $this->postJson(
        route('admin.ai-feedback.store', ['leads', $this->lead->id]),
        ['feedback' => 'Deze klant wil in de ochtend worden gebeld.'],
    );

    $createResponse
        ->assertCreated()
        ->assertJsonPath('data.author', $this->user->name);

    $feedback = AiFeedback::query()->firstOrFail();

    expect($feedback->subject_type)->toBe('leads')
        ->and((int) $feedback->subject_id)->toBe($this->lead->id)
        ->and($feedback->is_active)->toBeTrue();

    $this->putJson(
        route('admin.ai-feedback.update', ['leads', $this->lead->id, $feedback->id]),
        ['feedback' => 'Bel deze klant juist na 16:00 uur.'],
    )
        ->assertOk()
        ->assertJsonPath('data.feedback', 'Bel deze klant juist na 16:00 uur.');

    $this->getJson(route('admin.ai-summary.show', ['leads', $this->lead->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data.feedback')
        ->assertJsonPath('data.feedback.0.feedback', 'Bel deze klant juist na 16:00 uur.');

    $this->deleteJson(route('admin.ai-feedback.destroy', ['leads', $this->lead->id, $feedback->id]))
        ->assertOk();

    $deleted = AiFeedback::withTrashed()->findOrFail($feedback->id);

    expect($deleted->is_active)->toBeFalse()
        ->and($deleted->deleted_at)->not->toBeNull();

    $this->getJson(route('admin.ai-summary.show', ['leads', $this->lead->id]))
        ->assertOk()
        ->assertJsonCount(0, 'data.feedback');
});

test('feedback validation rejects empty and overly long corrections', function () {
    $this->postJson(
        route('admin.ai-feedback.store', ['leads', $this->lead->id]),
        ['feedback' => ''],
    )->assertUnprocessable();

    $this->postJson(
        route('admin.ai-feedback.store', ['leads', $this->lead->id]),
        ['feedback' => str_repeat('a', 1001)],
    )->assertUnprocessable();
});

test('feedback of one subject cannot be reached through another subject', function () {
    $salesLead = SalesLead::factory()->create(['lead_id' => $this->lead->id]);

    $feedback = AiFeedback::factory()->forSubject($this->lead)->create();

    $this->putJson(
        route('admin.ai-feedback.update', ['sales_leads', $salesLead->id, $feedback->id]),
        ['feedback' => 'Hoort hier niet.'],
    )->assertNotFound();
});

test('owner scoping follows the entity itself: leads are restricted, contacts are not', function () {
    $colleague = User::factory()->create();
    $colleaguesLead = Lead::factory()->create(['user_id' => $colleague->id]);
    $person = Person::factory()->create(['user_id' => $colleague->id]);

    $restrictedRole = Role::factory()->create([
        'permission_type' => 'custom',
        'permissions'     => ['leads', 'leads.view', 'contacts', 'contacts.persons', 'contacts.persons.view'],
    ]);
    $restricted = User::factory()->create([
        'role_id'         => $restrictedRole->id,
        'view_permission' => 'individual',
    ]);
    $this->actingAs($restricted, 'user');

    $this->getJson(route('admin.ai-summary.show', ['leads', $colleaguesLead->id]))
        ->assertForbidden();

    $this->getJson(route('admin.ai-summary.show', ['persons', $person->id]))
        ->assertOk();
});

test('an unknown subject key is rejected', function () {
    $this->getJson(route('admin.ai-summary.show', ['invoices', $this->lead->id]))
        ->assertNotFound();
});

test('summary endpoint exposes the current summary separately from generation history', function () {
    $salesLead = SalesLead::factory()->create(['lead_id' => $this->lead->id]);
    $order = Order::factory()->create([
        'sales_lead_id' => $salesLead->id,
        'order_number'  => 'ORD-123',
    ]);
    $sourceDate = now()->subDay()->startOfSecond();

    AiSummary::factory()->forSubject($this->lead)->create([
        'summary'          => 'Compacte actuele samenvatting.',
        'attention_points' => [[
            'text'   => 'Bestelde scan is al uitgevoerd.',
            'source' => [
                'ref'        => "order:{$order->id}",
                'type'       => 'order',
                'entity_id'  => $order->id,
                'label'      => 'Order: ORD-123',
                'date'       => $sourceDate->toIso8601String(),
                'date_label' => 'Afgesloten',
            ],
        ]],
    ]);

    $this->getJson(route('admin.ai-summary.show', ['leads', $this->lead->id]))
        ->assertOk()
        ->assertJsonPath('data.summary.summary', 'Compacte actuele samenvatting.')
        ->assertJsonPath('data.summary.attention_points.0.text', 'Bestelde scan is al uitgevoerd.')
        ->assertJsonPath('data.summary.attention_points.0.source.label', 'Order: ORD-123')
        ->assertJsonPath('data.summary.attention_points.0.source.date', $sourceDate->toIso8601String())
        ->assertJsonPath('data.summary.attention_points.0.source.url', route('admin.orders.view', $order->id))
        ->assertJsonPath('data.summary.priority', fn ($priority) => in_array($priority, ['low', 'medium', 'high'], true));
});

test('does not expose an order link when a stored citation belongs to another owner', function () {
    $otherUser = User::factory()->create();
    $otherLead = Lead::factory()->create(['user_id' => $otherUser->id]);
    $otherSalesLead = SalesLead::factory()->create(['lead_id' => $otherLead->id]);
    $otherOrder = Order::factory()->create([
        'sales_lead_id' => $otherSalesLead->id,
        'user_id'       => $otherUser->id,
    ]);

    AiSummary::factory()->forSubject($this->lead)->create([
        'attention_points' => [[
            'text'   => 'Niet toegankelijke order.',
            'source' => [
                'ref'        => "order:{$otherOrder->id}:created",
                'type'       => 'order',
                'entity_id'  => $otherOrder->id,
                'label'      => 'Order van andere eigenaar',
                'date'       => now()->toIso8601String(),
                'date_label' => 'Aangemaakt',
            ],
        ]],
    ]);

    $readOnlyRole = Role::factory()->create([
        'permission_type' => 'custom',
        'permissions'     => ['leads', 'leads.view'],
    ]);
    $restricted = User::factory()->create(['role_id' => $readOnlyRole->id, 'view_permission' => 'individual']);
    $this->lead->update(['user_id' => $restricted->id]);
    $this->actingAs($restricted, 'user');

    $this->getJson(route('admin.ai-summary.show', ['leads', $this->lead->id]))
        ->assertOk()
        ->assertJsonPath('data.summary.attention_points.0.source.url', null);
});

test('a user can explicitly request regeneration for every subject', function (string $subjectKey) {
    Queue::fake();
    config(['ai_summaries.enabled' => true]);

    $subject = match ($subjectKey) {
        'leads'       => $this->lead,
        'persons'     => Person::factory()->create(),
        'sales_leads' => SalesLead::factory()->create(['lead_id' => $this->lead->id]),
        'orders'      => Order::factory()->create([
            'sales_lead_id' => SalesLead::factory()->create(['lead_id' => $this->lead->id])->id,
        ]),
    };

    $this->postJson(route('admin.ai-summary.generate', [$subjectKey, $subject->getKey()]))
        ->assertAccepted();

    expect($subject->aiSummary()->firstOrFail()->status)->toBe('queued');

    Queue::assertPushed(
        GenerateAiSummaryJob::class,
        fn (GenerateAiSummaryJob $job) => $job->subjectKey === $subjectKey
            && $job->subjectId === (int) $subject->getKey()
            && $job->trigger === 'manual',
    );
})->with(['leads', 'persons', 'sales_leads', 'orders']);

test('manual regeneration is refused with a clear message while a generation is already in flight', function () {
    Queue::fake();
    config(['ai_summaries.enabled' => true]);

    foreach (['queued', 'processing', 'retrying'] as $inFlightStatus) {
        AiSummary::query()->updateOrCreate(
            ['subject_type' => 'leads', 'subject_id' => $this->lead->id],
            ['status' => $inFlightStatus],
        );

        $this->postJson(route('admin.ai-summary.generate', ['leads', $this->lead->id]))
            ->assertStatus(409)
            ->assertJsonPath('message', 'Er loopt al een verversing voor deze lead.');
    }

    Queue::assertNotPushed(GenerateAiSummaryJob::class);
});

test('manual regeneration is allowed again once a previous attempt has permanently failed', function () {
    Queue::fake();
    config(['ai_summaries.enabled' => true]);

    AiSummary::factory()->forSubject($this->lead)->create(['status' => 'failed']);

    $this->postJson(route('admin.ai-summary.generate', ['leads', $this->lead->id]))
        ->assertAccepted();

    expect($this->lead->aiSummary()->firstOrFail()->status)->toBe('queued');
    Queue::assertPushed(GenerateAiSummaryJob::class);
});

test('manual regeneration fails cleanly while ai summaries are disabled', function () {
    Queue::fake();

    $this->postJson(route('admin.ai-summary.generate', ['leads', $this->lead->id]))
        ->assertStatus(503)
        ->assertJsonPath('message', 'AI-samenvattingen zijn momenteel uitgeschakeld.');

    expect($this->lead->aiSummary)->toBeNull();
    Queue::assertNotPushed(GenerateAiSummaryJob::class);
});

test('opening a lazily generated subject queues its first summary', function () {
    Queue::fake();
    config(['ai_summaries.enabled' => true]);

    $person = Person::factory()->create();

    $this->getJson(route('admin.ai-summary.show', ['persons', $person->id]))
        ->assertOk()
        ->assertJsonPath('data.summary.status', 'queued');

    Queue::assertPushed(
        GenerateAiSummaryJob::class,
        fn (GenerateAiSummaryJob $job) => $job->subjectKey === 'persons'
            && $job->subjectId === $person->id
            && $job->trigger === 'view',
    );
});

test('opening a lead does not queue a summary because leads are refreshed on a schedule', function () {
    Queue::fake();
    config(['ai_summaries.enabled' => true]);

    $this->getJson(route('admin.ai-summary.show', ['leads', $this->lead->id]))
        ->assertOk()
        ->assertJsonPath('data.summary', null);

    Queue::assertNotPushed(GenerateAiSummaryJob::class);
});

test('a stale summary is refreshed when the panel is opened again', function () {
    Queue::fake();
    config(['ai_summaries.enabled' => true]);

    $person = Person::factory()->create();

    AiSummary::factory()->forSubject($person)->create([
        'status'       => 'completed',
        'generated_at' => now()->subDays(3),
    ]);

    $this->getJson(route('admin.ai-summary.show', ['persons', $person->id]))->assertOk();

    Queue::assertPushed(GenerateAiSummaryJob::class);
});

test('a fresh summary is not regenerated when the panel is opened', function () {
    Queue::fake();
    config(['ai_summaries.enabled' => true]);

    $person = Person::factory()->create();

    AiSummary::factory()->forSubject($person)->create([
        'status'       => 'completed',
        'generated_at' => now()->subMinutes(5),
        'summary'      => 'Nog vers.',
    ]);

    $this->getJson(route('admin.ai-summary.show', ['persons', $person->id]))
        ->assertOk()
        ->assertJsonPath('data.summary.summary', 'Nog vers.');

    Queue::assertNotPushed(GenerateAiSummaryJob::class);
});

test('lead view renders the ai summary panel expanded by default', function () {
    $this->get(route('admin.leads.view', $this->lead->id))
        ->assertOk()
        ->assertSee('AI-samenvatting')
        ->assertSee('isRightColumnCollapsed: false', false);
});

test('a read only lead user cannot regenerate summaries or mutate feedback', function () {
    $readOnlyRole = Role::factory()->create([
        'permission_type' => 'custom',
        'permissions'     => ['leads', 'leads.view'],
    ]);
    $readOnlyUser = User::factory()->create([
        'role_id' => $readOnlyRole->id,
    ]);
    $lead = Lead::factory()->create(['user_id' => $readOnlyUser->id]);
    $this->actingAs($readOnlyUser, 'user');

    $this->getJson(route('admin.ai-summary.show', ['leads', $lead->id]))
        ->assertOk();

    // The generic AI routes are not in the route-based ACL map (permissions differ per
    // subject), so the controller refuses them with 403 instead of the middleware's 401.
    $this->postJson(route('admin.ai-summary.generate', ['leads', $lead->id]))
        ->assertForbidden();

    $this->postJson(
        route('admin.ai-feedback.store', ['leads', $lead->id]),
        ['feedback' => 'Niet toegestaan'],
    )->assertForbidden();

    $this->get(route('admin.leads.view', $lead->id))
        ->assertOk()
        ->assertSee(':can-edit="false"', false);
});

test('a pending generation that never reported back can be retried from the button', function () {
    Queue::fake();
    config(['ai_summaries.enabled' => true]);

    $summary = AiSummary::factory()->forSubject($this->lead)->create(['status' => 'processing']);

    // Fresh: the run really is under way, so refuse.
    $this->postJson(route('admin.ai-summary.generate', ['leads', $this->lead->id]))
        ->assertStatus(409);

    // Stale: the worker died without reporting back; the user must be able to retry.
    $summary->forceFill(['updated_at' => now()->subHour()])->saveQuietly();

    $this->postJson(route('admin.ai-summary.generate', ['leads', $this->lead->id]))
        ->assertAccepted();

    Queue::assertPushed(GenerateAiSummaryJob::class);
});
