<?php

use App\Enums\EmailTemplateCode;
use App\Enums\EmailTemplateLanguage;
use App\Enums\EmailTemplateType;
use App\Models\Address;
use App\Models\Anamnesis;
use App\Models\ClinicDepartment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Resource;
use App\Models\ResourceOrderItem;
use App\Models\SalesLead;
use App\Models\User as AppUser;
use App\Services\OrderMailService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Webkul\Contact\Models\Organization;
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
        'type'     => EmailTemplateType::ORDER_ACKNOWLEDGEMENT->value,
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
            'error' => __('messages.email.template_not_found'),
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
        'person_id'   => $person->id,
        'lead_id'     => $lead->id,
        'gvl_form_id' => '12345',
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
        ->and($content)->toContain('https://patientdev.local.privatescan.nl/patient/forms/12345/step/1')
        ->and($content)->not->toContain('{{ $gvl_form_link }}');
});

test('it merges acknowledge-order-mail variables from OrderMailService for order entity', function () {
    $person = Person::factory()->create([
        'first_name' => 'Test',
        'last_name'  => 'User',
    ]);
    $salesLead = SalesLead::factory()->create([
        'contact_person_id' => $person->id,
    ]);
    $order = Order::factory()->create([
        'sales_lead_id' => $salesLead->id,
    ]);

    EmailTemplate::factory()->create([
        'name'     => 'Afspraak bevestiging API test',
        'code'     => 'acknowledge-order-mail-augusta',
        'type'     => EmailTemplateType::ORDER_APPOINTMENT_CONFIRMATION->value,
        'language' => EmailTemplateLanguage::NEDERLANDS->value,
        'subject'  => 'Order {{ order_reference }}',
        'content'  => '<p>{{ approval_instructions }}</p>',
    ]);

    $response = $this->postJson(route('admin.mail.template_content_body'), [
        'email_template_identifier' => 'acknowledge-order-mail-augusta',
        'entities'                  => [
            'order' => $order->id,
        ],
    ]);

    $response->assertStatus(200);
    $content = $response->json('data.content');
    expect($content)->toContain('Geef uw akkoord')
        ->and($content)->not->toContain('{{ approval_instructions }}');
});

test('acknowledge order mail resolves adviseur from order assigned user', function () {
    $advisor = AppUser::factory()->create([
        'first_name' => 'Jan',
        'last_name'  => 'Adviseur',
    ]);

    $person = Person::factory()->create();
    $salesLead = SalesLead::factory()->create([
        'contact_person_id' => $person->id,
    ]);

    $order = Order::factory()->create([
        'sales_lead_id' => $salesLead->id,
        'user_id'       => $advisor->id,
    ]);

    $vars = app(OrderMailService::class)->templateVariablesForAcknowledgeOrder($order->fresh());

    expect($vars['adviseur'])->toBe('Jan Adviseur');
});

test('order_gvl_deadline is one week before stored first examination date', function () {
    $person = Person::factory()->create();
    $salesLead = SalesLead::factory()->create(['contact_person_id' => $person->id]);
    $order = Order::factory()->create([
        'sales_lead_id'          => $salesLead->id,
        'first_examination_at'   => '2026-04-09',
        'first_examination_time' => null,
    ]);

    $vars = app(OrderMailService::class)->templateVariablesForAcknowledgeOrder($order->fresh());

    expect($vars['order_gvl_deadline'])->toBe('02-04-2026');
});

test('order_gvl_deadline uses earliest resource slot when examination fields are empty', function () {
    $salesLead = SalesLead::factory()->create();
    $order = Order::factory()->create([
        'sales_lead_id'          => $salesLead->id,
        'first_examination_at'   => null,
        'first_examination_time' => null,
    ]);
    $dept = ClinicDepartment::factory()->create();
    $resource = Resource::factory()->create(['clinic_department_id' => $dept->id]);
    $item = OrderItem::factory()->create(['order_id' => $order->id]);
    ResourceOrderItem::factory()->create([
        'orderitem_id' => $item->id,
        'resource_id'  => $resource->id,
        'from'         => Carbon::parse('2026-05-21 14:30:00', config('app.timezone')),
        'to'           => Carbon::parse('2026-05-21 15:00:00', config('app.timezone')),
    ]);

    $vars = app(OrderMailService::class)->templateVariablesForAcknowledgeOrder($order->fresh());

    expect($vars['order_gvl_deadline'])->toBe('14-05-2026');
});

test('order_gvl_deadline is empty string when no first examination can be resolved', function () {
    $salesLead = SalesLead::factory()->create();
    $order = Order::factory()->create([
        'sales_lead_id'          => $salesLead->id,
        'first_examination_at'   => null,
        'first_examination_time' => null,
    ]);
    OrderItem::factory()->create(['order_id' => $order->id]);

    $vars = app(OrderMailService::class)->templateVariablesForAcknowledgeOrder($order->fresh());

    expect($vars['order_gvl_deadline'])->toBe('');
});

test('address variables resolve to particulier contactpersoon address', function () {
    $address = Address::factory()->create([
        'street'              => 'Dorpsstraat',
        'house_number'        => '10',
        'house_number_suffix' => 'A',
        'postal_code'         => '1234AB',
        'city'                => 'Amsterdam',
        'state'               => 'Noord-Holland',
        'country'             => 'Nederland',
    ]);

    $person = Person::factory()->create(['address_id' => $address->id]);

    $salesLead = SalesLead::factory()->create(['contact_person_id' => $person->id]);

    $order = Order::factory()->create([
        'sales_lead_id' => $salesLead->id,
        'is_business'   => false,
    ]);

    $template = EmailTemplate::factory()->create([
        'name'     => 'adres-particulier',
        'code'     => 'adres-particulier',
        'type'     => EmailTemplateType::ORDER_APPOINTMENT_CONFIRMATION->value,
        'language' => EmailTemplateLanguage::NEDERLANDS->value,
        'subject'  => 'Test',
        'content'  => '{{ address_line1 }} | {{ address_line2 }} | {{ address_state }} | {{ address_country }} | {{ address_full }}',
    ]);

    $response = $this->postJson(route('admin.mail.template_content_body'), [
        'email_template_identifier' => 'adres-particulier',
        'entities'                  => ['order' => $order->id],
    ]);

    $response->assertStatus(200);
    $content = $response->json('data.content');

    $mailVars = app(OrderMailService::class)->templateVariablesForAcknowledgeOrder($order->fresh());
    expect($mailVars['address_full'])->toBe(
        '<span style="display:block;">Dorpsstraat 10 A</span>'
        .'<span style="display:block;">1234 AB Amsterdam</span>'
        .'<span style="display:block;">Nederland</span>',
    );

    $decoded = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    expect($content)
        ->toContain('Dorpsstraat 10 A')
        ->toContain('1234 AB Amsterdam')
        ->toContain('Noord-Holland')
        ->toContain('Nederland')
        ->not->toContain('{{ address_line1 }}');

    expect($decoded)->toMatch('/Dorpsstraat 10 A.*?1234 AB Amsterdam.*?Nederland/s');
});

test('address variables resolve to specific person address when person entity passed with order', function () {
    $address = Address::factory()->create([
        'street'       => 'Kerkstraat',
        'house_number' => '5',
        'postal_code'  => '5678CD',
        'city'         => 'Utrecht',
    ]);

    $person = Person::factory()->create(['address_id' => $address->id]);

    $salesLead = SalesLead::factory()->create(['contact_person_id' => $person->id]);

    $order = Order::factory()->create([
        'sales_lead_id' => $salesLead->id,
        'is_business'   => false,
    ]);

    $template = EmailTemplate::factory()->create([
        'name'     => 'adres-per-persoon',
        'code'     => 'adres-per-persoon',
        'type'     => EmailTemplateType::ORDER_APPOINTMENT_CONFIRMATION->value,
        'language' => EmailTemplateLanguage::NEDERLANDS->value,
        'subject'  => 'Test',
        'content'  => '{{ address_line1 }} | {{ address_line2 }}',
    ]);

    $response = $this->postJson(route('admin.mail.template_content_body'), [
        'email_template_identifier' => 'adres-per-persoon',
        'entities'                  => [
            'order'  => $order->id,
            'person' => $person->id,
        ],
    ]);

    $response->assertStatus(200);
    $content = $response->json('data.content');

    expect($content)
        ->toContain('Kerkstraat 5')
        ->toContain('5678 CD Utrecht');
});

test('address variables resolve to organisation address for zakelijk order', function () {
    $orgAddress = Address::factory()->create([
        'street'       => 'Bedrijvenlaan',
        'house_number' => '99',
        'postal_code'  => '9012EF',
        'city'         => 'Rotterdam',
        'state'        => 'Zuid-Holland',
        'country'      => 'Nederland',
    ]);

    $organization = Organization::factory()->create(['address_id' => $orgAddress->id]);

    $lead = Lead::factory()->create(['organization_id' => $organization->id]);

    $personWithoutAddress = Person::factory()->create();
    $salesLead = SalesLead::factory()->create([
        'lead_id'           => $lead->id,
        'contact_person_id' => $personWithoutAddress->id,
    ]);

    $order = Order::factory()->create([
        'sales_lead_id' => $salesLead->id,
        'is_business'   => true,
    ]);

    $template = EmailTemplate::factory()->create([
        'name'     => 'adres-zakelijk',
        'code'     => 'adres-zakelijk',
        'type'     => EmailTemplateType::ORDER_APPOINTMENT_CONFIRMATION->value,
        'language' => EmailTemplateLanguage::NEDERLANDS->value,
        'subject'  => 'Test',
        'content'  => '{{ address_line1 }} | {{ address_line2 }} | {{ address_state }}',
    ]);

    $response = $this->postJson(route('admin.mail.template_content_body'), [
        'email_template_identifier' => 'adres-zakelijk',
        'entities'                  => ['order' => $order->id],
    ]);

    $response->assertStatus(200);
    $content = $response->json('data.content');

    expect($content)
        ->toContain('Bedrijvenlaan 99')
        ->toContain('9012 EF Rotterdam')
        ->toContain('Zuid-Holland');
});

test('meldplek resolves order_confirmation_note from earliest scheduled clinic department', function () {
    $dept = ClinicDepartment::factory()->create([
        'order_confirmation_note' => 'Meld u aan bij de balie met uw verwijsbrief.',
    ]);
    $resource = Resource::factory()->create(['clinic_department_id' => $dept->id]);

    $salesLead = SalesLead::factory()->create();
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);
    $item = OrderItem::factory()->create(['order_id' => $order->id]);

    ResourceOrderItem::factory()->create([
        'orderitem_id' => $item->id,
        'resource_id'  => $resource->id,
        'from'         => now()->addDays(3),
    ]);

    $vars = app(OrderMailService::class)->templateVariablesForAcknowledgeOrder($order->fresh());

    expect($vars['meldplek'])->toBe('Meld u aan bij de balie met uw verwijsbrief.');
});

test('meldplek picks earliest appointment when multiple order items exist', function () {
    $deptEarly = ClinicDepartment::factory()->create(['order_confirmation_note' => 'Vroegste locatie.']);
    $deptLate = ClinicDepartment::factory()->create(['order_confirmation_note' => 'Late locatie.']);

    $resourceEarly = Resource::factory()->create(['clinic_department_id' => $deptEarly->id]);
    $resourceLate = Resource::factory()->create(['clinic_department_id' => $deptLate->id]);

    $salesLead = SalesLead::factory()->create();
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);

    $itemEarly = OrderItem::factory()->create(['order_id' => $order->id]);
    $itemLate = OrderItem::factory()->create(['order_id' => $order->id]);

    ResourceOrderItem::factory()->create([
        'orderitem_id' => $itemEarly->id,
        'resource_id'  => $resourceEarly->id,
        'from'         => now()->addDays(1),
    ]);
    ResourceOrderItem::factory()->create([
        'orderitem_id' => $itemLate->id,
        'resource_id'  => $resourceLate->id,
        'from'         => now()->addDays(5),
    ]);

    $vars = app(OrderMailService::class)->templateVariablesForAcknowledgeOrder($order->fresh());

    expect($vars['meldplek'])->toBe('Vroegste locatie.');
});

test('meldplek is empty string when no resource order items exist', function () {
    $salesLead = SalesLead::factory()->create();
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);
    OrderItem::factory()->create(['order_id' => $order->id]);

    $vars = app(OrderMailService::class)->templateVariablesForAcknowledgeOrder($order->fresh());

    expect($vars['meldplek'])->toBe('');
});

test('meldplek is empty string when clinic department has no order_confirmation_note', function () {
    $dept = ClinicDepartment::factory()->create(['order_confirmation_note' => null]);
    $resource = Resource::factory()->create(['clinic_department_id' => $dept->id]);

    $salesLead = SalesLead::factory()->create();
    $order = Order::factory()->create(['sales_lead_id' => $salesLead->id]);
    $item = OrderItem::factory()->create(['order_id' => $order->id]);

    ResourceOrderItem::factory()->create([
        'orderitem_id' => $item->id,
        'resource_id'  => $resource->id,
        'from'         => now()->addDays(2),
    ]);

    $vars = app(OrderMailService::class)->templateVariablesForAcknowledgeOrder($order->fresh());

    expect($vars['meldplek'])->toBe('');
});

test('address variables are empty strings when no address is set', function () {
    $person = Person::factory()->create();

    $salesLead = SalesLead::factory()->create(['contact_person_id' => $person->id]);

    $order = Order::factory()->create([
        'sales_lead_id' => $salesLead->id,
        'is_business'   => false,
    ]);

    $template = EmailTemplate::factory()->create([
        'name'     => 'adres-leeg',
        'code'     => 'adres-leeg',
        'type'     => EmailTemplateType::ORDER_APPOINTMENT_CONFIRMATION->value,
        'language' => EmailTemplateLanguage::NEDERLANDS->value,
        'subject'  => 'Test',
        'content'  => 'Adres: [{{ address_line1 }}] Stad: [{{ address_city }}]',
    ]);

    $response = $this->postJson(route('admin.mail.template_content_body'), [
        'email_template_identifier' => 'adres-leeg',
        'entities'                  => ['order' => $order->id],
    ]);

    $response->assertStatus(200);
    $content = $response->json('data.content');

    expect($content)
        ->toContain('Adres: []')
        ->toContain('Stad: []')
        ->not->toContain('{{ address_line1 }}')
        ->not->toContain('{{ address_city }}');
});
