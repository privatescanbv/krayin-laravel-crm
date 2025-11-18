<?php

namespace Tests\Feature\Mail;

use App\Enums\EmailTemplateLanguage;
use App\Enums\EmailTemplateType;
use App\Models\Anamnesis;
use App\Models\SalesLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Webkul\Contact\Models\Person;
use Webkul\EmailTemplate\Models\EmailTemplate;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

class EmailTemplateContentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user and authenticate
        $user = User::factory()->create();
        $this->actingAs($user, 'user');
    }

    /** @test */
    public function it_returns_template_body_with_lead_entity()
    {
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

        $this->assertStringContainsString('Doe', $response->json('data.content'));
    }

    /** @test */
    public function it_resolves_dollar_sign_variables_in_template()
    {
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

        // Should resolve $lastname to Smith
        $this->assertStringContainsString('Smith', $content);
        $this->assertStringNotContainsString('{{ $lastname }}', $content);
        $this->assertStringNotContainsString('$lastname', $content);
    }

    /** @test */
    public function it_returns_template_subject_with_lead_entity()
    {
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
        $this->assertStringContainsString('Jane', $subject);
        $this->assertStringContainsString('Smith', $subject);
    }

    /** @test */
    public function it_returns_template_body_with_person_entity()
    {
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
        $this->assertStringContainsString('Johnson', $response->json('data.content'));
    }

    /** @test */
    public function it_returns_template_body_with_sales_lead_entity()
    {
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
    }

    /** @test */
    public function it_returns_template_body_with_multiple_entities()
    {
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
        // Should contain at least one of the lastnames
        $this->assertTrue(
            str_contains($content, 'Doe') || str_contains($content, 'Johnson')
        );
    }

    /** @test */
    public function it_returns_error_when_template_not_found()
    {
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
    }

    /** @test */
    public function it_returns_error_when_email_template_identifier_missing()
    {
        $response = $this->postJson(route('admin.mail.template_content_body'), [
            'entities' => [
                'lead' => 1,
            ],
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'email_template_identifier is required',
            ]);
    }

    /** @test */
    public function it_returns_error_when_entities_missing()
    {
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
    }

    /** @test */
    public function it_returns_error_when_entities_empty()
    {
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
    }

    /** @test */
    public function it_handles_nested_properties_in_subject()
    {
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
        $this->assertStringNotContainsString('{%lead.name%}', $subject);
        $this->assertStringContainsString('John', $subject);
        $this->assertStringContainsString('Doe', $subject);
    }

    /** @test */
    public function it_returns_template_body_with_gvl_template_and_person_entity()
    {
        $person = Person::factory()->create([
            'first_name' => 'Test',
            'last_name'  => 'Person',
        ]);

        $lead = Lead::factory()->create([
            'first_name' => 'Test',
            'last_name'  => 'Lead',
        ]);

        // Create anamnesis with GVL form link
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
        $this->assertStringContainsString('Person', $content);
        $this->assertStringContainsString('https://example.com/gvl-form/12345', $content);
        $this->assertStringNotContainsString('{{ $gvl_form_link }}', $content);
    }
}
