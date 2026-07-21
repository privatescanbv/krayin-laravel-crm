<?php

use App\Models\LeadAiFeedback;
use App\Models\LeadAiSummary;
use App\Models\LeadAiSummaryGeneration;
use App\Models\Order;
use App\Models\SalesLead;
use App\Services\Ai\LeadAiSummaryService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Webkul\Lead\Models\Lead;

beforeEach(function () {
    config([
        'services.llm.base_url'                       => 'https://llm.test/v1',
        'services.llm.api_key'                        => 'test-key',
        'services.llm.model'                          => 'test-model',
        'services.llm.temperature'                    => 0.0,
        'services.llm.response_format_json'           => true,
        'services.llm.lead_summary.prompt_version'    => 'test-v1',
    ]);
});

test('stores a validated summary generation and marks active feedback as included', function () {
    $lead = Lead::factory()->create([
        'description' => 'Klant wacht op een offerte.',
    ]);
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create([
        'sales_lead_id'        => $salesLead->id,
        'order_number'         => 'ORD-123',
        'first_examination_at' => now()->subDays(2),
        'closed_at'            => now()->subDay(),
    ]);
    $activeFeedback = LeadAiFeedback::factory()->create([
        'lead_id'  => $lead->id,
        'feedback' => 'Deze klant wil in de ochtend worden gebeld.',
    ]);
    LeadAiFeedback::factory()->create([
        'lead_id'   => $lead->id,
        'feedback'  => 'Niet meer geldig.',
        'is_active' => false,
    ]);

    Http::fake([
        'https://llm.test/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => json_encode([
                    'summary'     => 'De klant wacht op opvolging van de offerte.',
                    'next_action' => [
                        'title'    => 'Bel de klant in de ochtend',
                        'reason'   => 'De klant wacht op een reactie en gaf een voorkeursmoment door.',
                        'priority' => 'high',
                    ],
                    'highlights' => [
                        ['label' => 'Status', 'value' => 'Offerte open'],
                    ],
                    'attention_points' => [
                        [
                            'text'       => 'Bestelde scan is al uitgevoerd.',
                            'source_ref' => "order:{$order->id}:examination",
                        ],
                    ],
                ], JSON_THROW_ON_ERROR)],
            ]],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
        ]),
    ]);

    $summary = app(LeadAiSummaryService::class)->generate($lead, 'test');

    expect($summary->status)->toBe('completed')
        ->and($summary->summary)->toBe('De klant wacht op opvolging van de offerte.')
        ->and($summary->next_action_title)->toBe('Bel de klant in de ochtend')
        ->and($summary->highlights)->toHaveCount(1)
        ->and($summary->attention_points)->toHaveCount(1)
        ->and($summary->attention_points[0]['text'])->toBe('Bestelde scan is al uitgevoerd.')
        ->and($summary->attention_points[0]['source']['type'])->toBe('order')
        ->and($summary->attention_points[0]['source']['entity_id'])->toBe($order->id)
        ->and($summary->attention_points[0]['source']['date_label'])->toBe('Onderzoeksdatum')
        ->and($summary->attention_points[0]['source']['date'])->not->toBeEmpty()
        ->and($summary->generations)->toHaveCount(1)
        ->and($summary->generations->first()->status)->toBe('completed')
        ->and($summary->generations->first()->input_hash)->toHaveLength(64)
        ->and($summary->generations->first()->context_snapshot)->not->toHaveKey('active_feedback')
        ->and($summary->generations->first()->tokens_input)->toBe(100)
        ->and($summary->generations->first()->tokens_output)->toBe(50)
        ->and($activeFeedback->fresh()->included_in_generation_at)->not->toBeNull();

    Http::assertSent(function ($request) use ($activeFeedback, $order) {
        $payload = json_decode($request->data()['messages'][1]['content'], true);

        return $request->data()['response_format'] === ['type' => 'json_object']
            && ($payload['feedback'][0]['ref'] ?? null) === "feedback:{$activeFeedback->id}"
            && ($payload['current_order']['ref'] ?? null) === "order:{$order->id}:examination"
            && ! array_key_exists('sources', $payload)
            && count($payload['feedback']) === 1;
    });
});

test('omits the sources catalog from the outgoing llm payload but keeps inline refs', function () {
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create([
        'sales_lead_id' => $salesLead->id,
        'order_number'  => 'ORD-456',
    ]);

    Http::fake([
        'https://llm.test/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => json_encode([
                    'summary'          => 'Samenvatting.',
                    'next_action'      => [],
                    'highlights'       => [],
                    'attention_points' => [],
                ], JSON_THROW_ON_ERROR)],
            ]],
        ]),
    ]);

    app(LeadAiSummaryService::class)->generate($lead, 'test');

    Http::assertSent(function ($request) use ($order) {
        $payload = json_decode($request->data()['messages'][1]['content'], true);

        return ! str_contains($request->data()['messages'][1]['content'], '_source')
            && ! array_key_exists('sources', $payload)
            && ($payload['current_order']['ref'] ?? null) === "order:{$order->id}:created";
    });
});

test('keeps the previous valid summary visible when the ai response is invalid', function () {
    $lead = Lead::factory()->create();
    $existing = LeadAiSummary::factory()->create([
        'lead_id' => $lead->id,
        'summary' => 'Eerder geldige samenvatting.',
        'status'  => 'completed',
    ]);

    Http::fake([
        'https://llm.test/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => 'Dit is geen JSON.'],
            ]],
        ]),
    ]);

    $summary = app(LeadAiSummaryService::class)->generate($lead, 'test');

    expect($summary->id)->toBe($existing->id)
        ->and($summary->summary)->toBe('Eerder geldige samenvatting.')
        ->and($summary->status)->toBe('failed')
        ->and($summary->last_error)->not->toBeEmpty()
        ->and($summary->generations)->toHaveCount(1)
        ->and($summary->generations->first()->status)->toBe('failed')
        ->and($summary->generations->first()->raw_response)->toBe('Dit is geen JSON.')
        ->and(DB::table('lead_ai_summary_generations')->value('raw_response'))->not->toBe('Dit is geen JSON.');
});

test('accepts an empty next_action for leads that need no follow-up', function () {
    $lead = Lead::factory()->create();

    Http::fake([
        'https://llm.test/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => json_encode([
                    'summary'          => 'Deal is gewonnen, geen verdere actie nodig.',
                    'next_action'      => [],
                    'highlights'       => [['label' => 'Status', 'value' => 'Gewonnen']],
                    'attention_points' => [],
                ], JSON_THROW_ON_ERROR)],
            ]],
        ]),
    ]);

    $summary = app(LeadAiSummaryService::class)->generate($lead, 'test');

    expect($summary->status)->toBe('completed')
        ->and($summary->summary)->toBe('Deal is gewonnen, geen verdere actie nodig.')
        ->and($summary->next_action_title)->toBeNull()
        ->and($summary->next_action_reason)->toBeNull()
        ->and($summary->priority)->toBeNull();
});

test('rejects responses that exceed the fixed output limits', function () {
    $lead = Lead::factory()->create();

    Http::fake([
        'https://llm.test/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => json_encode([
                    'summary'     => str_repeat('a', 401),
                    'next_action' => [
                        'title'    => str_repeat('b', 81),
                        'reason'   => str_repeat('c', 181),
                        'priority' => 'urgent',
                    ],
                    'highlights'       => array_fill(0, 4, ['label' => 'L', 'value' => 'V']),
                    'attention_points' => array_fill(0, 4, [
                        'text'       => 'Punt',
                        'source_ref' => "lead:{$lead->id}",
                    ]),
                ], JSON_THROW_ON_ERROR)],
            ]],
        ]),
    ]);

    $summary = app(LeadAiSummaryService::class)->generate($lead, 'test');

    expect($summary->status)->toBe('failed')
        ->and($summary->summary)->toBeNull()
        ->and($summary->generations()->latest('id')->first()->status)->toBe('failed');
});

test('drops attention points with an unknown source reference but keeps the summary', function () {
    $lead = Lead::factory()->create();

    Http::fake([
        'https://llm.test/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => json_encode([
                    'summary'     => 'Samenvatting.',
                    'next_action' => [
                        'title'    => '',
                        'reason'   => '',
                        'priority' => 'low',
                    ],
                    'highlights'       => [],
                    'attention_points' => [[
                        'text'       => 'Niet controleerbare bewering.',
                        'source_ref' => 'order:999999',
                    ]],
                ], JSON_THROW_ON_ERROR)],
            ]],
        ]),
    ]);

    $summary = app(LeadAiSummaryService::class)->generate($lead, 'test');

    expect($summary->status)->toBe('completed')
        ->and($summary->summary)->toBe('Samenvatting.')
        ->and($summary->attention_points)->toBe([])
        ->and($summary->last_error)->toBeNull();
});

test('keeps the verifiable attention point when the model also invents one', function () {
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create([
        'sales_lead_id' => $salesLead->id,
        'order_number'  => 'ORD-MIXED',
    ]);

    Http::fake([
        'https://llm.test/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => json_encode([
                    'summary'     => 'Samenvatting.',
                    'next_action' => [
                        'title'    => '',
                        'reason'   => '',
                        'priority' => 'low',
                    ],
                    'highlights'       => [],
                    'attention_points' => [
                        [
                            'text'       => 'Verzonnen bron.',
                            // Two refs glued together, as observed from the real model.
                            'source_ref' => "activity:1:order:{$order->id}:created",
                        ],
                        [
                            'text'       => 'Deze klopt wel.',
                            'source_ref' => "order:{$order->id}:created",
                        ],
                    ],
                ], JSON_THROW_ON_ERROR)],
            ]],
        ]),
    ]);

    $summary = app(LeadAiSummaryService::class)->generate($lead, 'test');

    expect($summary->status)->toBe('completed')
        ->and($summary->attention_points)->toHaveCount(1)
        ->and($summary->attention_points[0]['text'])->toBe('Deze klopt wel.')
        ->and($summary->attention_points[0]['source']['ref'])->toBe("order:{$order->id}:created");
});

test('drops a citation when its order was deleted during generation', function () {
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create([
        'sales_lead_id' => $salesLead->id,
        'order_number'  => 'ORD-DELETED',
    ]);

    Http::fake(function () use ($order) {
        DB::table('orders')->where('id', $order->id)->delete();

        return Http::response([
            'choices' => [[
                'message' => ['content' => json_encode([
                    'summary'     => 'Samenvatting.',
                    'next_action' => [
                        'title'    => '',
                        'reason'   => '',
                        'priority' => 'low',
                    ],
                    'highlights'       => [],
                    'attention_points' => [[
                        'text'       => 'Deze order bestaat nog.',
                        'source_ref' => "order:{$order->id}:created",
                    ]],
                ], JSON_THROW_ON_ERROR)],
            ]],
        ]);
    });

    $summary = app(LeadAiSummaryService::class)->generate($lead, 'test');

    expect($summary->status)->toBe('completed')
        ->and($summary->summary)->toBe('Samenvatting.')
        ->and($summary->attention_points)->toBe([]);
});

test('drops a citation when its source changed during generation', function () {
    $lead = Lead::factory()->create();
    $salesLead = SalesLead::factory()->create(['lead_id' => $lead->id]);
    $order = Order::factory()->create([
        'sales_lead_id' => $salesLead->id,
        'order_number'  => 'ORD-CHANGED',
        'title'         => 'Oude orderinhoud',
    ]);

    Http::fake(function () use ($order) {
        $order->update(['title' => 'Nieuwe orderinhoud']);

        return Http::response([
            'choices' => [[
                'message' => ['content' => json_encode([
                    'summary'     => 'Samenvatting.',
                    'next_action' => [
                        'title'    => '',
                        'reason'   => '',
                        'priority' => 'low',
                    ],
                    'highlights'       => [],
                    'attention_points' => [[
                        'text'       => 'Gebaseerd op oude orderinhoud.',
                        'source_ref' => "order:{$order->id}:created",
                    ]],
                ], JSON_THROW_ON_ERROR)],
            ]],
        ]);
    });

    $summary = app(LeadAiSummaryService::class)->generate($lead, 'test');

    expect($summary->status)->toBe('completed')
        ->and($summary->summary)->toBe('Samenvatting.')
        ->and($summary->attention_points)->toBe([]);
});

test('rethrows connection failures so the queued job can be retried, while keeping the previous summary visible', function () {
    $lead = Lead::factory()->create();
    $existing = LeadAiSummary::factory()->create([
        'lead_id' => $lead->id,
        'summary' => 'Eerder geldige samenvatting.',
        'status'  => 'completed',
    ]);

    Http::fake(function () {
        throw new ConnectionException('cURL error 28: Failed to connect to llm.test port 443 after 10001 ms');
    });

    expect(fn () => app(LeadAiSummaryService::class)->generate($lead, 'test'))
        ->toThrow(ConnectionException::class);

    $existing->refresh();

    expect($existing->status)->toBe('failed')
        ->and($existing->summary)->toBe('Eerder geldige samenvatting.')
        ->and($existing->last_error)->toContain('Failed to connect')
        ->and($existing->generations)->toHaveCount(1)
        ->and($existing->generations->first()->status)->toBe('failed');
});

test('prunes older generation records for the lead once a new one completes', function () {
    $lead = Lead::factory()->create();
    $summary = LeadAiSummary::factory()->create(['lead_id' => $lead->id, 'status' => 'failed']);
    $staleGeneration = LeadAiSummaryGeneration::factory()->create([
        'lead_id'            => $lead->id,
        'lead_ai_summary_id' => $summary->id,
        'status'             => 'failed',
    ]);

    Http::fake([
        'https://llm.test/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => json_encode([
                    'summary'          => 'Nieuwe samenvatting.',
                    'next_action'      => ['title' => '', 'reason' => '', 'priority' => 'low'],
                    'highlights'       => [['label' => 'Status', 'value' => 'Open']],
                    'attention_points' => [],
                ], JSON_THROW_ON_ERROR)],
            ]],
        ]),
    ]);

    $result = app(LeadAiSummaryService::class)->generate($lead, 'test');

    expect($result->generations)->toHaveCount(1)
        ->and($result->generations->first()->status)->toBe('completed')
        ->and(LeadAiSummaryGeneration::query()->find($staleGeneration->id))->toBeNull();
});

test('stores payload size observability on the generation snapshot', function () {
    $lead = Lead::factory()->create();

    Http::fake([
        'https://llm.test/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => json_encode([
                    'summary'          => 'Samenvatting.',
                    'next_action'      => [],
                    'highlights'       => [],
                    'attention_points' => [],
                ], JSON_THROW_ON_ERROR)],
            ]],
            'usage' => ['prompt_tokens' => 321, 'completion_tokens' => 44],
        ]),
    ]);

    $summary = app(LeadAiSummaryService::class)->generate($lead, 'test');
    $snapshot = $summary->generations->first()->context_snapshot;

    expect($snapshot['system_prompt_bytes'])->toBeGreaterThan(0)
        ->and($snapshot['user_payload_bytes'])->toBeGreaterThan(0)
        ->and($snapshot['payload_bytes'])->toBe($snapshot['user_payload_bytes'])
        ->and($summary->generations->first()->tokens_input)->toBe(321);
});

test('does not mark feedback as included when it changed during generation', function () {
    $lead = Lead::factory()->create();
    $feedback = LeadAiFeedback::factory()->create([
        'lead_id'    => $lead->id,
        'feedback'   => 'Oude correctie',
        'updated_at' => now()->subMinute(),
    ]);

    Http::fake(function () use ($feedback) {
        $feedback->update(['feedback' => 'Nieuwe correctie']);

        return Http::response([
            'choices' => [[
                'message' => ['content' => json_encode([
                    'summary'     => 'Samenvatting.',
                    'next_action' => [
                        'title'    => '',
                        'reason'   => '',
                        'priority' => 'low',
                    ],
                    'highlights'       => [],
                    'attention_points' => [],
                ], JSON_THROW_ON_ERROR)],
            ]],
        ]);
    });

    app(LeadAiSummaryService::class)->generate($lead, 'test');

    expect($feedback->fresh()->feedback)->toBe('Nieuwe correctie')
        ->and($feedback->fresh()->included_in_generation_at)->toBeNull();
});
