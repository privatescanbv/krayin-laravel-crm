<?php

use App\Jobs\GenerateLeadAiSummaryJob;
use App\Models\LeadAiFeedback;
use App\Models\LeadAiSummary;
use App\Models\Order;
use App\Models\SalesLead;
use Illuminate\Support\Facades\Queue;
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
        route('admin.leads.ai-feedback.store', $this->lead->id),
        ['feedback' => 'Deze klant wil in de ochtend worden gebeld.'],
    );

    $createResponse
        ->assertCreated()
        ->assertJsonPath('data.author', $this->user->name);

    $feedback = LeadAiFeedback::query()->firstOrFail();

    expect($feedback->lead_id)->toBe($this->lead->id)
        ->and($feedback->is_active)->toBeTrue();

    $this->putJson(
        route('admin.leads.ai-feedback.update', [$this->lead->id, $feedback->id]),
        ['feedback' => 'Bel deze klant juist na 16:00 uur.'],
    )
        ->assertOk()
        ->assertJsonPath('data.feedback', 'Bel deze klant juist na 16:00 uur.');

    $this->getJson(route('admin.leads.ai-summary.show', $this->lead->id))
        ->assertOk()
        ->assertJsonCount(1, 'data.feedback')
        ->assertJsonPath('data.feedback.0.feedback', 'Bel deze klant juist na 16:00 uur.');

    $this->deleteJson(route('admin.leads.ai-feedback.destroy', [$this->lead->id, $feedback->id]))
        ->assertOk();

    $deleted = LeadAiFeedback::withTrashed()->findOrFail($feedback->id);

    expect($deleted->is_active)->toBeFalse()
        ->and($deleted->deleted_at)->not->toBeNull();

    $this->getJson(route('admin.leads.ai-summary.show', $this->lead->id))
        ->assertOk()
        ->assertJsonCount(0, 'data.feedback');
});

test('feedback validation rejects empty and overly long corrections', function () {
    $this->postJson(
        route('admin.leads.ai-feedback.store', $this->lead->id),
        ['feedback' => ''],
    )->assertUnprocessable();

    $this->postJson(
        route('admin.leads.ai-feedback.store', $this->lead->id),
        ['feedback' => str_repeat('a', 1001)],
    )->assertUnprocessable();
});

test('summary endpoint exposes the current summary separately from generation history', function () {
    $salesLead = SalesLead::factory()->create(['lead_id' => $this->lead->id]);
    $order = Order::factory()->create([
        'sales_lead_id' => $salesLead->id,
        'order_number'  => 'ORD-123',
    ]);
    $sourceDate = now()->subDay()->startOfSecond();

    LeadAiSummary::factory()->create([
        'lead_id'          => $this->lead->id,
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

    $this->getJson(route('admin.leads.ai-summary.show', $this->lead->id))
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
    $otherOrder = Order::factory()->create(['sales_lead_id' => $otherSalesLead->id]);

    LeadAiSummary::factory()->create([
        'lead_id'          => $this->lead->id,
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

    $this->getJson(route('admin.leads.ai-summary.show', $this->lead->id))
        ->assertOk()
        ->assertJsonPath('data.summary.attention_points.0.source.url', null);
});

test('a user can explicitly request regeneration for a closed lead', function () {
    Queue::fake();
    config(['services.llm.lead_summary.enabled' => true]);

    $this->postJson(route('admin.leads.ai-summary.generate', $this->lead->id))
        ->assertAccepted();

    expect($this->lead->aiSummary()->firstOrFail()->status)->toBe('queued');

    Queue::assertPushed(
        GenerateLeadAiSummaryJob::class,
        fn (GenerateLeadAiSummaryJob $job) => $job->leadId === $this->lead->id
            && $job->trigger === 'manual',
    );
});

test('manual regeneration is refused with a clear message while a generation is already in flight', function () {
    Queue::fake();
    config(['services.llm.lead_summary.enabled' => true]);

    foreach (['queued', 'processing', 'retrying'] as $inFlightStatus) {
        LeadAiSummary::query()->updateOrCreate(
            ['lead_id' => $this->lead->id],
            ['status' => $inFlightStatus],
        );

        $this->postJson(route('admin.leads.ai-summary.generate', $this->lead->id))
            ->assertStatus(409)
            ->assertJsonPath('message', 'Er loopt al een verversing voor deze lead.');
    }

    Queue::assertNotPushed(GenerateLeadAiSummaryJob::class);
});

test('manual regeneration is allowed again once a previous attempt has permanently failed', function () {
    Queue::fake();
    config(['services.llm.lead_summary.enabled' => true]);

    LeadAiSummary::factory()->create(['lead_id' => $this->lead->id, 'status' => 'failed']);

    $this->postJson(route('admin.leads.ai-summary.generate', $this->lead->id))
        ->assertAccepted();

    expect($this->lead->aiSummary()->firstOrFail()->status)->toBe('queued');
    Queue::assertPushed(GenerateLeadAiSummaryJob::class);
});

test('manual regeneration fails cleanly while lead summaries are disabled', function () {
    Queue::fake();

    $this->postJson(route('admin.leads.ai-summary.generate', $this->lead->id))
        ->assertStatus(503)
        ->assertJsonPath('message', 'AI-samenvattingen zijn momenteel uitgeschakeld.');

    expect($this->lead->aiSummary)->toBeNull();
    Queue::assertNotPushed(GenerateLeadAiSummaryJob::class);
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

    $this->getJson(route('admin.leads.ai-summary.show', $lead->id))
        ->assertOk();

    $this->postJson(route('admin.leads.ai-summary.generate', $lead->id))
        ->assertUnauthorized();

    $this->postJson(
        route('admin.leads.ai-feedback.store', $lead->id),
        ['feedback' => 'Niet toegestaan'],
    )->assertUnauthorized();

    $this->get(route('admin.leads.view', $lead->id))
        ->assertOk()
        ->assertSee(':can-edit="false"', false);
});
