<?php

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Models\AiFeedback;
use App\Models\Order;
use App\Models\SalesLead;
use App\Services\Ai\AiSubjectRegistry;
use App\Services\Ai\Context\AiContextBuilder;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Email\Models\Email;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

function leadContextBuilder(): AiContextBuilder
{
    return app(AiSubjectRegistry::class)->builder('leads');
}

test('system created activities are excluded from the llm context and last customer contact', function () {
    $lead = Lead::factory()->create();

    Activity::create([
        'type'    => ActivityType::SYSTEM->value,
        'title'   => 'Aangemaakt',
        'comment' => null,
        'is_done' => true,
        'status'  => ActivityStatus::DONE->value,
        'lead_id' => $lead->id,
    ]);

    $call = Activity::create([
        'type'         => ActivityType::CALL->value,
        'title'        => 'Gebeld over planning',
        'comment'      => 'Patiente wil een rustig tijdsslot.',
        'is_done'      => true,
        'status'       => ActivityStatus::DONE->value,
        'lead_id'      => $lead->id,
        'completed_at' => now()->subDay(),
    ]);

    $context = leadContextBuilder()->build($lead);
    $payload = leadContextBuilder()->forLlm($context);

    expect(collect($payload['timeline'] ?? [])->pluck('ref')->all())
        ->toContain("activity:{$call->id}")
        ->and(collect($payload['timeline'] ?? [])->every(
            fn (array $entry) => ! str_contains(mb_strtolower($entry['text'] ?? ''), 'aangemaakt')
        ))->toBeTrue();

    if (isset($payload['last_customer_contact'])) {
        expect($payload['last_customer_contact']['ref'])->toBe("activity:{$call->id}")
            ->and($payload['last_customer_contact']['text'])->not->toContain('Aangemaakt');
    }
});

test('historical lead sales and order information is not duplicated in the llm payload', function () {
    $owner = User::factory()->create();
    $person = Person::factory()->create();
    $current = Lead::factory()->create(['user_id' => $owner->id]);
    $historical = Lead::factory()->create([
        'user_id'     => $owner->id,
        'description' => 'Controle-MRI knie rechts',
    ]);
    $current->persons()->attach($person);
    $historical->persons()->attach($person);

    $salesLead = SalesLead::factory()->create([
        'lead_id'     => $historical->id,
        'name'        => 'Controle-MRI knie rechts',
        'description' => 'Controle-MRI knie rechts',
    ]);
    $order = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'title'                => 'Controle-MRI knie rechts',
        'total_price'          => 490,
        'first_examination_at' => now()->subMonths(3),
        'closed_at'            => now()->subMonths(3)->addDay(),
    ]);

    Activity::create([
        'type'         => ActivityType::NOTE->value,
        'title'        => 'Scan uitgevoerd, uitslag besproken met patiente.',
        'comment'      => 'Scan uitgevoerd, uitslag besproken met patiente.',
        'is_done'      => true,
        'status'       => ActivityStatus::DONE->value,
        'lead_id'      => $historical->id,
        'sales_lead_id'=> $salesLead->id,
        'order_id'     => $order->id,
        'completed_at' => now()->subMonths(3),
    ]);

    $payload = leadContextBuilder()->forLlm(
        leadContextBuilder()->build($current)
    );
    $json = json_encode($payload, JSON_THROW_ON_ERROR);

    expect($payload)->not->toHaveKey('historical_leads')
        ->and($payload)->not->toHaveKey('sales')
        ->and($payload)->not->toHaveKey('sources')
        ->and($payload['history'] ?? [])->toHaveCount(1)
        ->and($payload['history'][0]['description'])->toBe('Controle-MRI knie rechts')
        ->and($payload['history'][0]['ref'])->toBe("order:{$order->id}:examination")
        ->and(substr_count($json, 'Controle-MRI knie rechts'))->toBe(1)
        ->and(collect($payload['timeline'] ?? [])->pluck('text')->implode(' '))
        ->not->toContain('Scan uitgevoerd');
});

test('old appointment confirmations are filtered while recent customer mail stays', function () {
    $lead = Lead::factory()->create();

    $oldConfirmation = Email::create([
        'lead_id' => $lead->id,
        'subject' => 'Bevestiging afspraak MRI-onderzoek',
        'from'    => ['name' => 'PrivateScan', 'email' => 'planning@privatescan.nl'],
        'reply'   => 'Hierbij bevestigen wij uw afspraak voor het MRI-onderzoek.',
    ]);
    $oldConfirmation->forceFill([
        'created_at' => now()->subMonths(2),
        'updated_at' => now()->subMonths(2),
    ])->saveQuietly();

    $recent = Email::create([
        'lead_id' => $lead->id,
        'subject' => 'Vraag over planning vervolgonderzoek',
        'from'    => ['name' => 'Emma', 'email' => 'emma@example.com'],
        'reply'   => 'Kunnen jullie me laten weten wanneer het vervolgonderzoek kan worden ingepland?',
    ]);
    $recent->forceFill([
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ])->saveQuietly();

    $payload = leadContextBuilder()->forLlm(
        leadContextBuilder()->build($lead)
    );

    $timelineTexts = collect($payload['timeline'] ?? [])->pluck('text')->implode(' ');
    $timelineRefs = collect($payload['timeline'] ?? [])->pluck('ref');

    expect($timelineRefs)->toContain("email:{$recent->id}")
        ->and($timelineTexts)->toContain('vervolgonderzoek')
        ->and($timelineTexts)->not->toContain('Hierbij bevestigen wij uw afspraak');
});

test('open tasks and active feedback remain available with valid source refs', function () {
    $lead = Lead::factory()->create(['description' => 'Open traject']);
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'first_examination_at' => now()->subDays(2),
    ]);

    $task = Activity::create([
        'type'          => ActivityType::TASK->value,
        'title'         => 'Planning benaderen',
        'comment'       => 'Open taak: vervolgonderzoek inplannen.',
        'is_done'       => false,
        'status'        => ActivityStatus::ACTIVE->value,
        'lead_id'       => $lead->id,
        'sales_lead_id' => $salesLead->id,
        'order_id'      => $order->id,
        'schedule_from' => now()->addDay(),
    ]);

    $feedback = AiFeedback::factory()->forSubject($lead)->create([
        'feedback' => 'Eerdere vermelding contrastallergie is onjuist; patiënte heeft GEEN contrastallergie.',
    ]);

    $context = leadContextBuilder()->build($lead);
    $payload = leadContextBuilder()->forLlm($context);

    expect(collect($payload['timeline'] ?? [])->pluck('ref'))->toContain("activity:{$task->id}")
        ->and($payload['feedback'][0]['ref'])->toBe("feedback:{$feedback->id}")
        ->and($payload['feedback'][0]['text'])->toContain('GEEN contrastallergie')
        ->and($payload['current_order']['ref'])->toBe("order:{$order->id}:examination")
        ->and(collect($context['sources'])->pluck('ref')->all())
        ->toContain("activity:{$task->id}")
        ->toContain("feedback:{$feedback->id}")
        ->toContain("order:{$order->id}:examination");
});

test('includes historical leads for the same person regardless of owner or stage', function () {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();
    $person = Person::factory()->create();
    $pipeline = Pipeline::first() ?? Pipeline::create([
        'name'        => 'Test pipeline',
        'is_default'  => 1,
        'rotten_days' => 30,
    ]);
    $wonStage = Stage::create([
        'name'             => 'Gewonnen historie',
        'code'             => 'won-hist-'.uniqid(),
        'lead_pipeline_id' => $pipeline->id,
        'sort_order'       => 80,
        'is_won'           => true,
        'is_lost'          => false,
    ]);
    $lostStage = Stage::create([
        'name'             => 'Verloren historie',
        'code'             => 'lost-hist-'.uniqid(),
        'lead_pipeline_id' => $pipeline->id,
        'sort_order'       => 81,
        'is_won'           => false,
        'is_lost'          => true,
    ]);

    $lead = Lead::factory()->create(['user_id' => $owner->id]);
    $wonHistorical = Lead::factory()->create([
        'user_id'                => $otherOwner->id,
        'lead_pipeline_stage_id' => $wonStage->id,
        'description'            => 'Eerdere gewonnen MRI-wervelkolom.',
    ]);
    $lostHistorical = Lead::factory()->create([
        'user_id'                => null,
        'lead_pipeline_stage_id' => $lostStage->id,
        'description'            => 'Eerdere verloren lead wegens prijs.',
        'lost_reason'            => 'prijs',
    ]);
    $unrelated = Lead::factory()->create(['user_id' => $owner->id]);

    $lead->persons()->attach($person);
    $wonHistorical->persons()->attach($person);
    $lostHistorical->persons()->attach($person);

    $salesLead = SalesLead::factory()->create([
        'lead_id'     => $wonHistorical->id,
        'name'        => 'MRI wervelkolom',
        'description' => 'Eerdere gewonnen MRI-wervelkolom.',
    ]);
    $order = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'title'                => 'MRI wervelkolom',
        'total_price'          => 1159,
        'first_examination_at' => now()->subMonths(2),
        'closed_at'            => now()->subMonth(),
    ]);

    $currentEmail = Email::create([
        'lead_id' => $lead->id,
        'subject' => 'Zichtbare e-mail',
        'reply'   => 'Zichtbare inhoud over planning',
        'from'    => ['name' => 'Emma', 'email' => 'emma@example.com'],
    ]);
    Email::create([
        'lead_id' => $wonHistorical->id,
        'subject' => 'Historische e-mail hoort niet in timeline',
        'reply'   => 'Oude afspraakbevestiging inhoud',
        'from'    => ['name' => 'Andere', 'email' => 'other@example.com'],
    ]);

    $context = leadContextBuilder()->build($lead);
    $payload = leadContextBuilder()->forLlm($context);

    expect($context['historical_lead_ids'])
        ->toContain($wonHistorical->id)
        ->toContain($lostHistorical->id)
        ->not->toContain($lead->id)
        ->not->toContain($unrelated->id)
        ->and(collect($payload['history'] ?? [])->pluck('ref')->all())
        ->toContain("order:{$order->id}:examination")
        ->toContain("lead:{$lostHistorical->id}")
        ->and(collect($payload['history'] ?? [])->firstWhere('ref', "lead:{$lostHistorical->id}")['lost_reason'] ?? null)
        ->not->toBeNull()
        ->and(collect($payload['timeline'] ?? [])->pluck('ref'))
        ->toContain("email:{$currentEmail->id}")
        ->and(collect($payload['timeline'] ?? [])->pluck('text')->implode(' '))
        ->not->toContain('Historische e-mail');
});
