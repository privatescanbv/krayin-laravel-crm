<?php

use App\Models\Order;
use App\Models\SalesLead;
use App\Services\Ai\LlmService;
use App\Services\Mail\EmailEntityLinker;
use App\Services\Mail\EmailLlmLinkingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Webkul\Contact\Models\Person;
use Webkul\Email\Models\Email;
use Webkul\Email\Repositories\EmailRepository;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.llm.base_url'      => 'https://llm.test/v1',
        'services.llm.api_key'       => 'test-key',
        'services.llm.temperature'   => 0.7,
        'services.llm.timeout'       => 180,
        'services.llm.model'         => 'local-llama',
    ]);
});

test('extract and link stores metadata and links person', function () {
    $person = Person::factory()->create([
        'emails' => [['value' => 'patient@example.com', 'is_default' => true]],
    ]);

    $email = Email::create([
        'subject' => 'FW: Patient vraag',
        'from'    => ['name' => 'Medewerker', 'email' => 'medewerker@privatescan.nl'],
        'reply'   => 'Doorgestuurd bericht Van: patient@example.com',
    ]);

    Http::fake([
        'https://llm.test/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => '{"senders":[{"email":"patient@example.com","name":"Jan","confidence":0.95,"role":"original_sender"}]}']],
            ],
        ], 200),
    ]);

    $service = new EmailLlmLinkingService(
        new LlmService,
        new EmailEntityLinker,
        app(EmailRepository::class),
    );

    $result = $service->extractAndLink($email, trigger: 'manual');

    $email->refresh();

    expect($result['status'])->toBe('linked')
        ->and($email->person_id)->toBe($person->id)
        ->and($email->llm_metadata['status'])->toBe('linked')
        ->and($email->llm_metadata['senders'][0]['email'])->toBe('patient@example.com')
        ->and($email->llm_metadata['trigger'])->toBe('manual');
});

test('extract and link stores no match metadata', function () {
    $email = Email::create([
        'subject' => 'FW: Onbekend',
        'from'    => ['name' => 'Medewerker', 'email' => 'medewerker@privatescan.nl'],
        'reply'   => 'Doorgestuurd bericht',
    ]);

    Http::fake([
        'https://llm.test/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => '{"senders":[{"email":"unknown@example.com","name":"X","confidence":0.8,"role":"original_sender"}]}']],
            ],
        ], 200),
    ]);

    $service = new EmailLlmLinkingService(
        new LlmService,
        new EmailEntityLinker,
        app(EmailRepository::class),
    );

    $result = $service->extractAndLink($email, trigger: 'manual');

    $email->refresh();

    expect($result['status'])->toBe('no_match')
        ->and($email->llm_metadata['status'])->toBe('no_match')
        ->and($email->person_id)->toBeNull();
});

test('stores raw llm content when json parsing fails', function () {
    $email = Email::create([
        'subject' => 'FW: Test',
        'from'    => ['name' => 'Medewerker', 'email' => 'medewerker@privatescan.nl'],
        'reply'   => 'Doorgestuurd bericht',
    ]);

    Http::fake([
        'https://llm.test/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => 'Sorry, ik kan dit niet als JSON formatteren.']],
            ],
        ], 200),
    ]);

    $service = new EmailLlmLinkingService(
        new LlmService,
        new EmailEntityLinker,
        app(EmailRepository::class),
    );

    $result = $service->extractAndLink($email, trigger: 'manual');

    $email->refresh();

    expect($result['status'])->toBe('error')
        ->and($result['llm_raw_content'])->toContain('Sorry, ik kan dit niet')
        ->and($email->llm_metadata['llm_raw_content'])->toContain('Sorry, ik kan dit niet');
});

test('custom system prompt is forwarded to llm service', function () {
    $email = Email::create([
        'subject' => 'FW: Test',
        'from'    => ['name' => 'Medewerker', 'email' => 'medewerker@privatescan.nl'],
        'reply'   => 'Doorgestuurd bericht',
    ]);

    Http::fake([
        'https://llm.test/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => '{"senders":[]}']],
            ],
        ], 200),
    ]);

    $service = new EmailLlmLinkingService(
        new LlmService,
        new EmailEntityLinker,
        app(EmailRepository::class),
    );

    $service->extractAndLink($email, systemPrompt: 'Custom prompt for testing', trigger: 'manual');

    Http::assertSent(function ($request) {
        return $request->data()['messages'][0]['content'] === 'Custom prompt for testing';
    });
});

function emailLlmLinkingService(): EmailLlmLinkingService
{
    return new EmailLlmLinkingService(
        new LlmService,
        new EmailEntityLinker,
        app(EmailRepository::class),
    );
}

function createOrderStage(bool $won = false, bool $lost = false): Stage
{
    $pipeline = Pipeline::first() ?? Pipeline::create([
        'name'        => 'Default Pipeline',
        'is_default'  => 1,
        'rotten_days' => 30,
    ]);

    $factory = Stage::factory();

    if ($won) {
        $factory = $factory->won();
    } elseif ($lost) {
        $factory = $factory->lost();
    }

    return $factory->create(['lead_pipeline_id' => $pipeline->id]);
}

test('active order suggestions include only non-won non-lost orders', function () {
    $activeStage = createOrderStage();
    $wonStage = createOrderStage(won: true);
    $lostStage = createOrderStage(lost: true);

    $salesLead = SalesLead::factory()->create();
    $activeOrder = Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => $activeStage->id,
        'title'             => 'Actieve order',
    ]);
    Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => $wonStage->id,
        'title'             => 'Gewonnen order',
    ]);
    Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => $lostStage->id,
        'title'             => 'Verloren order',
    ]);

    $suggestions = emailLlmLinkingService()->activeOrderSuggestionsForSalesLead($salesLead->id);

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0]['type'])->toBe('order')
        ->and($suggestions[0]['label'])->toBe('Order: Actieve order')
        ->and($suggestions[0]['links'])->toEqual([
            'order_id'      => $activeOrder->id,
            'sales_lead_id' => $salesLead->id,
        ]);
});

test('suggestions for email view returns active orders when linked to sales', function () {
    $activeStage = createOrderStage();
    $salesLead = SalesLead::factory()->create();
    Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => $activeStage->id,
        'title'             => 'Order A',
    ]);

    $email = Email::create([
        'subject'       => 'Vraag patient',
        'from'          => ['name' => 'Patient', 'email' => 'patient@example.com'],
        'reply'         => 'Body',
        'sales_lead_id' => $salesLead->id,
    ]);

    $suggestions = emailLlmLinkingService()->suggestionsForEmailView($email);

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0]['type'])->toBe('order')
        ->and($suggestions[0]['label'])->toBe('Order: Order A');
});

test('suggestions for email view excludes current order when relinking', function () {
    $activeStage = createOrderStage();
    $salesLead = SalesLead::factory()->create();
    $linkedOrder = Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => $activeStage->id,
        'title'             => 'Huidige order',
    ]);
    Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => $activeStage->id,
        'title'             => 'Andere order',
    ]);

    $email = Email::create([
        'subject'       => 'Vraag',
        'from'          => ['name' => 'Patient', 'email' => 'patient@example.com'],
        'reply'         => 'Body',
        'sales_lead_id' => $salesLead->id,
        'order_id'      => $linkedOrder->id,
    ]);

    $suggestions = emailLlmLinkingService()->suggestionsForEmailView($email);

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0]['label'])->toBe('Order: Andere order');
});

test('manual extraction uses cached metadata without calling llm', function () {
    $email = Email::create([
        'subject'      => 'FW: Test',
        'from'         => ['name' => 'Medewerker', 'email' => 'medewerker@privatescan.nl'],
        'reply'        => 'Body',
        'llm_metadata' => [
            'status'  => 'matched',
            'senders' => [['email' => 'patient@example.com', 'name' => 'Jan', 'confidence' => 0.9, 'role' => 'original_sender']],
            'links'   => [],
        ],
    ]);

    Http::fake();

    $result = emailLlmLinkingService()->extractAndLink(
        $email,
        trigger: 'manual',
        forceRefresh: false,
    );

    Http::assertNothingSent();

    expect($result['from_cache'])->toBeTrue()
        ->and($result['status'])->toBe('matched');
});

test('suggestions after extraction falls back to active orders when linked to sales and no links', function () {
    $activeStage = createOrderStage();
    $salesLead = SalesLead::factory()->create();
    Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => $activeStage->id,
        'title'             => 'Fallback order',
    ]);

    $email = Email::create([
        'subject'       => 'Vraag',
        'from'          => ['name' => 'Patient', 'email' => 'patient@example.com'],
        'reply'         => 'Body',
        'sales_lead_id' => $salesLead->id,
    ]);

    $suggestions = emailLlmLinkingService()->suggestionsAfterExtraction($email, []);

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0]['label'])->toBe('Order: Fallback order');
});

test('enrich with suggestions includes sales and active orders but linker never sets order_id', function () {
    $activeStage = createOrderStage();
    $salesLead = SalesLead::factory()->create();
    Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => $activeStage->id,
        'title'             => 'Order 1',
    ]);

    $linkData = (new EmailEntityLinker)->link([], 'unknown@example.com');
    expect($linkData)->not->toHaveKey('order_id');

    $suggestions = emailLlmLinkingService()->enrichWithSuggestions(['sales_lead_id' => $salesLead->id]);

    expect($suggestions)->toHaveCount(2)
        ->and($suggestions[0]['type'])->toBe('sales')
        ->and($suggestions[1]['type'])->toBe('order');
});
