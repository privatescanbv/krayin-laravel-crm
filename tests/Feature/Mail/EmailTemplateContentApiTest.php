<?php

use App\Enums\EmailTemplateLanguage;
use App\Enums\EmailTemplateType;
use App\Models\Anamnesis;
use App\Models\SalesLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Contact\Models\Person;
use Webkul\EmailTemplate\Models\EmailTemplate;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'user');
});

test('it returns template body with lead entity', function () {
    $lead = Lead::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
    ]);

    $template = EmailTemplate::factory()->create([
        'name'     => 'test-template',
        'code'     => 'test-template',
        'type'     => EmailTemplateType::LEAD->value,
        'language' => EmailTemplateLanguage::NEDERLANDS->value,
        'subject'  => 'Test Subject',
        'content'  => '<p>Hello {{ lastname }}</p>',
    ]);

    $response = $this->postJson(route('admin.mail.template_content_body'), [
        'email_template_identifier' => 'test-template',
        'entities'                  => [
            'lead' => $lead->id,
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'content',
            ],
        ]);

    expect($response->json('data.content'))->toContain('Doe');
});

test('it resolves dollar sign variables in template', function () {
    $lead = Lead::factory()->create([
        'first_name' => 'Jane',
        'last_name'  => 'Smith',
    ]);

    $template = EmailTemplate::factory()->create([
        'name'     => 'dollar-template',
        'code'     => 'dollar-template',
        'type'     => EmailTemplateType::LEAD->value,
        'language' => EmailTemplateLanguage::NEDERLANDS->value,
        'subject'  => 'Test',
        'content'  => '<p>Geachte heer/mevrouw {{ $lastname }},</p>',
    ]);

    $response = $this->postJson(route('admin.mail.template_content_body'), [
        'email_template_identifier' => 'dollar-template',
        'entities'                  => [
            'lead' => $lead->id,
        ],
    ]);

    $response->assertStatus(200);
    $content = $response->json('data.content');

    expect($content)->toContain('Smith')
        ->not->toContain('{{ $lastname }}')
        ->not->toContain('$lastname');
});

test('it returns template subject with lead entity', function () {
    $lead = Lead::factory()->create([
        'first_name' => 'Jane',
        'last_name'  => 'Smith',
    ]);

    $template = EmailTemplate::factory()->create([
        'name'     => 'test-subject-template',
        'code'     => 'test-subject-template',
        'type'     => EmailTemplateType::LEAD->value,
        'language' => EmailTemplateLanguage::NEDERLANDS->value,
        'subject'  => 'Re: {%lead.name%}',
        'content'  => '<p>Test content</p>',
    ]);

    $response = $this->postJson(route('admin.mail.template_content_subject'), [
        'email_template_identifier' => 'test-subject-template',
        'entities'                  => [
            'lead' => $lead->id,
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'subject',
            ],
        ]);

    $subject = $response->json('data.subject');
    expect($subject)->toContain('Jane')
        ->and($subject)->toContain('Smith');
});

test('it returns template body with person entity', function () {
    $person = Person::factory()->create([
        'first_name' => 'Alice',
        'last_name'  => 'Johnson',
    ]);

    $template = EmailTemplate::factory()->create([
        'name'     => 'person-template',
        'code'     => 'person-template',
        'type'     => EmailTemplateType::ALGEMEEN->value,
        'language' => EmailTemplateLanguage::NEDERLANDS->value,
        'subject'  => 'Test',
        'content'  => '<p>Dear {{ lastname }}</p>',
    ]);

    $response = $this->postJson(route('admin.mail.template_content_body'), [
        'email_template_identifier' => 'person-template',
        'entities'                  => [
            'person' => $person->id,
        ],
    ]);

    $response->assertStatus(200);
    expect($response->json('data.content'))->toContain('Johnson');
});

test('it returns template body with sales lead entity', function () {
    $person = Person::factory()->create([
        'first_name' => 'Sales',
        'last_name'  => 'Lead Person',
    ]);

    $salesLead = SalesLead::factory()->create([
        'contact_person_id' => $person->id,
    ]);

    $template = EmailTemplate::factory()->create([
        'name'     => 'sales-lead-template',
        'code'     => 'sales-lead-template',
        'type'     => EmailTemplateType::ORDER->value,
        'language' => EmailTemplateLanguage::NEDERLANDS->value,
        'subject'  => 'Order Template',
        'content'  => '<p>Order content</p>',
    ]);

    $response = $this->postJson(route('admin.mail.template_content_body'), [
        'email_template_identifier' => 'sales-lead-template',
        'entities'                  => [
            'sales_lead' => $salesLead->id,
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'content',
            ],
        ]);
});

test('it returns template body with multiple entities', function () {
    $lead = Lead::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
    ]);

    $person = Person::factory()->create([
        'first_name' => 'Alice',
        'last_name'  => 'Johnson',
    ]);

    $template = EmailTemplate::factory()->create([
        'name'     => 'multi-entity-template',
        'code'     => 'multi-entity-template',
        'type'     => EmailTemplateType::LEAD->value,
        'language' => EmailTemplateLanguage::NEDERLANDS->value,
        'subject'  => 'Multi Entity',
        'content'  => '<p>Lead: {{ lastname }}, Person: {{ lastname }}</p>',
    ]);

    $response = $this->postJson(route('admin.mail.template_content_body'), [
        'email_template_identifier' => 'multi-entity-template',
        'entities'                  => [
            'lead'   => $lead->id,
            'person' => $person->id,
        ],
    ]);

    $response->assertStatus(200);
    $content = $response->json('data.content');
    expect(str_contains($content, 'Doe') || str_contains($content, 'Johnson'))->toBeTrue();
});

test('it returns error when template not found', function () {
    $lead = Lead::factory()->create();

    $response = $this->postJson(route('admin.mail.template_content_body'), [
        'email_template_identifier' => 'non-existent-template',
        'entities'                  => [
            'lead' => $lead->id,
        ],
    ]);

    $response->assertStatus(404)
        ->assertJson([
            'error' => 'Template not found',
        ]);
});

test('it returns error when email template identifier missing', function () {
    $response = $this->postJson(route('admin.mail.template_content_body'), [
        'entities' => [
            'lead' => 1,
        ],
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'email_template_identifier is required',
        ]);
});

test('it returns error when entities missing', function () {
    $template = EmailTemplate::factory()->create([
        'name'     => 'test-template',
        'code'     => 'test-template',
        'type'     => EmailTemplateType::LEAD->value,
        'language' => EmailTemplateLanguage::NEDERLANDS->value,
        'subject'  => 'Test',
        'content'  => '<p>Test</p>',
    ]);

    $response = $this->postJson(route('admin.mail.template_content_body'), [
        'email_template_identifier' => 'test-template',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'entities array is required',
        ]);
});

test('it returns error when entities empty', function () {
    $template = EmailTemplate::factory()->create([
        'name'     => 'test-template',
        'code'     => 'test-template',
        'type'     => EmailTemplateType::LEAD->value,
        'language' => EmailTemplateLanguage::NEDERLANDS->value,
        'subject'  => 'Test',
        'content'  => '<p>Test</p>',
    ]);

    $response = $this->postJson(route('admin.mail.template_content_body'), [
        'email_template_identifier' => 'test-template',
        'entities'                  => [],
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'error' => 'entities array is required',
        ]);
});

test('it handles nested properties in subject', function () {
    $lead = Lead::factory()->create([
        'first_name' => 'John',
        'last_name'  => 'Doe',
    ]);

    $template = EmailTemplate::factory()->create([
        'name'     => 'nested-template',
        'code'     => 'nested-template',
        'type'     => EmailTemplateType::LEAD->value,
        'language' => EmailTemplateLanguage::NEDERLANDS->value,
        'subject'  => 'Re: {%lead.name%}',
        'content'  => '<p>Test</p>',
    ]);

    $response = $this->postJson(route('admin.mail.template_content_subject'), [
        'email_template_identifier' => 'nested-template',
        'entities'                  => [
            'lead' => $lead->id,
        ],
    ]);

    $response->assertStatus(200);
    $subject = $response->json('data.subject');
    expect($subject)->not->toContain('{%lead.name%}')
        ->and($subject)->toContain('John')
        ->and($subject)->toContain('Doe');
});

test('it returns template body with gvl template and person entity', function () {
    $person = Person::factory()->create([
        'first_name' => 'Test',
        'last_name'  => 'Person',
    ]);

    $lead = Lead::factory()->create([
        'first_name' => 'Test',
        'last_name'  => 'Lead',
    ]);

    $anamnesis = Anamnesis::factory()->create([
        'person_id'     => $person->id,
        'lead_id'       => $lead->id,
        'gvl_form_link' => 'https://example.com/gvl-form/12345',
    ]);

    $template = EmailTemplate::factory()->create([
        'name'     => 'informatief-met-gvl',
        'code'     => 'informatief-met-gvl',
        'type'     => EmailTemplateType::GVL->value,
        'language' => EmailTemplateLanguage::NEDERLANDS->value,
        'subject'  => 'Informatie over uw aanvraag',
        'content'  => '<p>Geachte heer/mevrouw {{ $lastname }},</p>
                <p>GVL-formulier: {{ $gvl_form_link }}</p>',
    ]);

    $response = $this->postJson(route('admin.mail.template_content_body'), [
        'email_template_identifier' => 'informatief-met-gvl',
        'entities'                  => [
            'person' => $person->id,
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'content',
            ],
        ]);

    $content = $response->json('data.content');
    expect($content)->toContain('Person')
        ->and($content)->toContain('https://example.com/gvl-form/12345')
        ->and($content)->not->toContain('{{ $gvl_form_link }}');
});
