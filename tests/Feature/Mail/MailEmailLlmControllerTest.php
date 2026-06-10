<?php

use App\Models\Order;
use App\Models\SalesLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Webkul\Contact\Models\Person;
use Webkul\Email\Enums\EmailFolderEnum;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.llm.base_url'      => 'https://llm.test/v1',
        'services.llm.api_key'       => 'test-key',
        'services.llm.temperature'   => 0.7,
        'services.llm.timeout'       => 180,
        'services.llm.model'         => 'local-llama',
    ]);

    $role = Role::factory()->create([
        'permission_type' => 'all',
        'permissions'     => null,
    ]);

    test()->admin = User::factory()->create([
        'role_id'         => $role->id,
        'view_permission' => 'global',
        'status'          => 1,
    ]);
});

test('admin can run llm sender extraction manually', function () {
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

    $response = $this->actingAs(test()->admin, 'user')
        ->postJson(route('admin.mail.llm_sender_extraction', $email->id), [
            'system_prompt' => 'Gebruik custom prompt',
            'apply_links'   => true,
        ]);

    $response->assertOk()
        ->assertJsonPath('result.status', 'linked')
        ->assertJsonPath('result.senders.0.email', 'patient@example.com')
        ->assertJsonPath('email.llm_metadata.status', 'linked')
        ->assertJsonPath('result.suggestions.0.type', 'person');

    $email->refresh();

    expect($email->person_id)->toBe($person->id)
        ->and($email->llm_metadata['system_prompt'])->toBe('Gebruik custom prompt');
});

test('manual llm extraction returns order suggestions when already linked to sales', function () {
    $pipeline = Pipeline::first() ?? Pipeline::create([
        'name'        => 'Default Pipeline',
        'is_default'  => 1,
        'rotten_days' => 30,
    ]);
    $activeStage = Stage::factory()->create(['lead_pipeline_id' => $pipeline->id]);

    $person = Person::factory()->create([
        'emails' => [['value' => 'patient@example.com', 'is_default' => true]],
    ]);

    $salesLead = SalesLead::factory()->create(['pipeline_stage_id' => $activeStage->id]);
    $salesLead->persons()->attach($person->id);

    Order::factory()->create([
        'sales_lead_id'     => $salesLead->id,
        'pipeline_stage_id' => $activeStage->id,
        'title'             => 'Order voor patient',
    ]);

    $email = Email::create([
        'subject'       => 'FW: Patient vraag',
        'from'          => ['name' => 'Medewerker', 'email' => 'medewerker@privatescan.nl'],
        'reply'         => 'Doorgestuurd bericht Van: patient@example.com',
        'sales_lead_id' => $salesLead->id,
    ]);

    Http::fake([
        'https://llm.test/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => '{"senders":[{"email":"patient@example.com","name":"Jan","confidence":0.95,"role":"original_sender"}]}']],
            ],
        ], 200),
    ]);

    $response = $this->actingAs(test()->admin, 'user')
        ->postJson(route('admin.mail.llm_sender_extraction', $email->id), [
            'apply_links' => false,
        ]);

    $response->assertOk()
        ->assertJsonPath('result.status', 'matched')
        ->assertJsonCount(2, 'result.suggestions')
        ->assertJsonPath('result.suggestions.0.type', 'sales')
        ->assertJsonPath('result.suggestions.1.type', 'order')
        ->assertJsonPath('result.suggestions.1.label', 'Order: Order voor patient');

    expect($email->fresh()->sales_lead_id)->toBe($salesLead->id);
});

test('manual llm extraction returns cached result without calling llm', function () {
    $email = Email::create([
        'subject'      => 'FW: Cached',
        'from'         => ['name' => 'Medewerker', 'email' => 'medewerker@privatescan.nl'],
        'reply'        => 'Body',
        'llm_metadata' => [
            'status'  => 'no_match',
            'senders' => [['email' => 'unknown@example.com', 'name' => 'X', 'confidence' => 0.8, 'role' => 'original_sender']],
            'links'   => [],
        ],
    ]);

    Http::fake();

    $response = $this->actingAs(test()->admin, 'user')
        ->postJson(route('admin.mail.llm_sender_extraction', $email->id));

    Http::assertNothingSent();

    $response->assertOk()
        ->assertJsonPath('result.from_cache', true)
        ->assertJsonPath('message', 'Eerdere analyse: geen CRM-match voor de afzenders.');
});

test('manual llm extraction returns metadata when no match found', function () {
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

    $response = $this->actingAs(test()->admin, 'user')
        ->postJson(route('admin.mail.llm_sender_extraction', $email->id));

    $response->assertOk()
        ->assertJsonPath('result.status', 'no_match')
        ->assertJsonPath('result.senders.0.email', 'unknown@example.com');
});

test('apply suggestion moves email from inbox to Verwerkt folder', function () {
    $inboxFolder = Folder::create([
        'name'         => EmailFolderEnum::INBOX->value,
        'parent_id'    => null,
        'order'        => 1,
        'is_deletable' => false,
    ]);

    $verwerktFolder = Folder::create([
        'name'         => EmailFolderEnum::PROCESSED->value,
        'parent_id'    => null,
        'order'        => 3,
        'is_deletable' => false,
    ]);

    $person = Person::factory()->create();

    $email = Email::create([
        'subject'   => 'Order bevestiging',
        'from'      => ['name' => 'Patient', 'email' => 'patient@example.com'],
        'reply'     => 'Body',
        'folder_id' => $inboxFolder->id,
    ]);

    $response = $this->actingAs(test()->admin, 'user')
        ->postJson(route('admin.mail.apply_suggestion', $email->id), [
            'links' => ['person_id' => $person->id],
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'E-mail gekoppeld.');

    expect($email->fresh()->folder_id)->toBe($verwerktFolder->id)
        ->and($email->fresh()->person_id)->toBe($person->id);
});

test('apply suggestion with order link moves email from inbox to Verwerkt folder', function () {
    $inboxFolder = Folder::create([
        'name'         => EmailFolderEnum::INBOX->value,
        'parent_id'    => null,
        'order'        => 1,
        'is_deletable' => false,
    ]);

    $verwerktFolder = Folder::create([
        'name'         => EmailFolderEnum::PROCESSED->value,
        'parent_id'    => null,
        'order'        => 3,
        'is_deletable' => false,
    ]);

    $pipeline = Pipeline::first() ?? Pipeline::create([
        'name'        => 'Default Pipeline',
        'is_default'  => 1,
        'rotten_days' => 30,
    ]);
    $activeStage = Stage::factory()->create(['lead_pipeline_id' => $pipeline->id]);

    $order = Order::factory()->create(['pipeline_stage_id' => $activeStage->id]);

    $email = Email::create([
        'subject'   => 'Order bevestiging',
        'from'      => ['name' => 'Patient', 'email' => 'patient@example.com'],
        'reply'     => 'Body',
        'folder_id' => $inboxFolder->id,
    ]);

    $response = $this->actingAs(test()->admin, 'user')
        ->postJson(route('admin.mail.apply_suggestion', $email->id), [
            'links' => ['order_id' => $order->id],
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'E-mail gekoppeld.');

    expect($email->fresh()->folder_id)->toBe($verwerktFolder->id)
        ->and($email->fresh()->order_id)->toBe($order->id);
});
