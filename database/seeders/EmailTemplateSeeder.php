<?php

namespace Database\Seeders;

use App\Enums\Departments;
use App\Enums\EmailTemplateCode;
use App\Enums\EmailTemplateLanguage;
use App\Enums\EmailTemplateType;
use Illuminate\Database\Seeder;
use Webkul\EmailTemplate\Models\EmailTemplate;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Return all default email templates.
     *
     * Edit subject/content here to change the defaults for a fresh database.
     * In production these can be modified via Instellingen → E-mailsjablonen.
     *
     * Available variables per template are listed in a comment above each entry.
     * See EmailTemplateRenderingService::SUPPORTED_VARIABLES for the full reference.
     */
    private static function templates(): array
    {
        $nl = EmailTemplateLanguage::NEDERLANDS->value;
        $de = EmailTemplateLanguage::DUITS->value;
        $en = EmailTemplateLanguage::ENGELS->value;

        $lead = EmailTemplateType::LEAD->value;
        $algemeen = EmailTemplateType::ALGEMEEN->value;
        $gvl = EmailTemplateType::GVL->value;
        $patient = EmailTemplateType::PATIENT->value;

        $allDepartments = Departments::allValues();

        return [

            // ── Activiteit aangemaakt ─────────────────────────────────────────────
            // Variables: lead.name, lead.title, user.first_name, user.last_name
            [
                'name'        => 'Activiteit aangemaakt',
                'code'        => 'activity-created',
                'type'        => $lead,
                'language'    => $nl,
                'departments' => $allDepartments,
                'subject'     => 'Nieuwe activiteit voor {{ lead.name }}',
                'content'     => <<<'HTML'
<p>Beste {{ user.first_name }},</p>
<p>Er is een nieuwe activiteit aangemaakt voor lead <strong>{{ lead.name }}</strong>.</p>
<p>Activiteit: {{ lead.title }}</p>
<p>Met vriendelijke groet,<br>Privatescan CRM</p>
HTML,
            ],

            // ── Activiteit gewijzigd ──────────────────────────────────────────────
            // Variables: lead.name, lead.title, user.first_name, user.last_name
            [
                'name'        => 'Activiteit gewijzigd',
                'code'        => 'activity-modified',
                'type'        => $lead,
                'language'    => $nl,
                'departments' => $allDepartments,
                'subject'     => 'Activiteit gewijzigd voor {{ lead.name }}',
                'content'     => <<<'HTML'
<p>Beste {{ user.first_name }},</p>
<p>Een activiteit voor lead <strong>{{ lead.name }}</strong> is gewijzigd.</p>
<p>Activiteit: {{ lead.title }}</p>
<p>Met vriendelijke groet,<br>Privatescan CRM</p>
HTML,
            ],

            // ── Antwoord (NL) ─────────────────────────────────────────────────────
            // Variables: (vrij in te vullen — geen vaste entiteit vereist)
            [
                'name'        => 'Antwoord',
                'code'        => 'reply',
                'type'        => $algemeen,
                'language'    => $nl,
                'departments' => $allDepartments,
                'subject'     => 'Re: uw bericht',
                'content'     => <<<'HTML'
<p>Beste,</p>
<p>Hartelijk dank voor uw bericht. Wij nemen zo spoedig mogelijk contact met u op.</p>
<p>Met vriendelijke groet,<br>Privatescan</p>
HTML,
            ],

            // ── Antwoord (DE) ─────────────────────────────────────────────────────
            [
                'name'        => 'Antwort',
                'code'        => 'reply-de',
                'type'        => $algemeen,
                'language'    => $de,
                'departments' => $allDepartments,
                'subject'     => 'Re: Ihre Nachricht',
                'content'     => <<<'HTML'
<p>Sehr geehrte Damen und Herren,</p>
<p>Vielen Dank für Ihre Nachricht. Wir werden uns so bald wie möglich bei Ihnen melden.</p>
<p>Mit freundlichen Grüßen,<br>Privatescan</p>
HTML,
            ],

            // ── Antwoord (EN) ─────────────────────────────────────────────────────
            [
                'name'        => 'Reply',
                'code'        => 'reply-en',
                'type'        => $algemeen,
                'language'    => $en,
                'departments' => $allDepartments,
                'subject'     => 'Re: your message',
                'content'     => <<<'HTML'
<p>Dear,</p>
<p>Thank you for your message. We will get back to you as soon as possible.</p>
<p>Kind regards,<br>Privatescan</p>
HTML,
            ],

            // ── Afspraakbevestiging TB NL ───────────────────────────────────────────────
            // Variables: customer_name, order_reference, order_title, datum_afspraak,
            //            tijd_afspraak, plaats_afspraak, afspraken_tabel
            [
                'name'        => 'Afspraakbevestiging TB plus bloed NL',
                'code'        => 'appointment-tb-blood-email-nl',
                'type'        => EmailTemplateType::ORDER_APPOINTMENT_CONFIRMATION->value,
                'language'    => $nl,
                'departments' => $allDepartments,
                'subject'     => 'Bevestiging afspraak {{ order_number }}',
                'content'     => <<<'HTML'
<<p>Beste {{ customer_name }},</p>
<p>Hierbij bevestigen wij de door u aan Privatescan verstrekte opdracht voor het laten uitvoeren van een Total Bodyscan met aanvullend bloedonderzoek in {{ plaats_afspraak }}.<br>De complete orderbevestiging en de gezondheidsvragenlijst vindt u terug in het pati&euml;ntportaal. Graag ontvangen we zo spoedig mogelijk, bij voorkeur per e-mail, uw akkoord voor de geplande onderzoeken alsmede de door u ingevulde gezondheidsvragenlijst(en).</p>
<p><a title="Inloggen" href="https://patient.dev.privatescan.nl" target="_blank" rel="noopener"><strong>Inloggen pati&euml;ntportaal</strong></a></p>
<p>{{ order.first_examination_at}}</p>
<p>Heeft u vragen? Neem dan gerust contact met ons op.</p>
<p>{{ order_items_table }}</p>
<p>Met vriendelijke groet,<br>Privatescan</p>
HTML,
            ],

            // ── Informatief met GVL ───────────────────────────────────────────────
            // Variables: person.first_name, person.name, gvl_form_link, gvl_deadline
            [
                'name'        => 'Informatief met GVL',
                'code'        => 'informatief-met-gvl',
                'type'        => $gvl,
                'language'    => $nl,
                'departments' => $allDepartments,
                'subject'     => 'Informatie en GVL-formulier voor {{ person.name }}',
                'content'     => <<<'HTML'
<p>Beste {{ person.first_name }},</p>
<p>Bijgaand ontvangt u informatie over uw behandeling.</p>
<p>Om uw behandeling te kunnen verwerken, verzoeken wij u vriendelijk het onderstaande formulier in te vullen vóór <strong>{{ gvl_deadline }}</strong>:</p>
<p><a href="{{ gvl_form_link }}">GVL-formulier invullen</a></p>
<p>Heeft u vragen? Neem dan gerust contact met ons op.</p>
<p>Met vriendelijke groet,<br>Privatescan</p>
HTML,
            ],

            // ── Patiëntportaal notificatie ────────────────────────────────────────
            // Variables: lastname, portal_url, person.first_name
            [
                'name'        => 'Patiëntportaal notificatie',
                'code'        => 'patient-portal-notification',
                'type'        => $patient,
                'language'    => $nl,
                'departments' => $allDepartments,
                'subject'     => 'Uw patiëntportaal is beschikbaar',
                'content'     => <<<'HTML'
<p>Beste {{ person.first_name }},</p>
<p>Uw patiëntportaal is klaar voor gebruik. U kunt inloggen via de onderstaande link:</p>
<p><a href="{{ portal_url }}">Naar het patiëntportaal</a></p>
<p>Heeft u vragen? Neem dan gerust contact met ons op.</p>
<p>Met vriendelijke groet,<br>Privatescan</p>
HTML,
            ],

            // ── Gebruiker aanmaken ────────────────────────────────────────────────
            // Variables: user.first_name, user.last_name, user.email
            [
                'name'        => 'Gebruiker aanmaken',
                'code'        => 'create-user',
                'type'        => $algemeen,
                'language'    => $nl,
                'departments' => $allDepartments,
                'subject'     => 'Welkom bij Privatescan CRM',
                'content'     => <<<'HTML'
<p>Beste {{ user.first_name }},</p>
<p>Uw account voor het Privatescan CRM is aangemaakt.</p>
<p>U kunt inloggen met het volgende e-mailadres: <strong>{{ user.email }}</strong></p>
<p>Met vriendelijke groet,<br>Privatescan</p>
HTML,
            ],

            // ── Order mail (legacy — geen code, wordt ook via OrderMailService aangemaakt) ──
            // Variables: customer_name, order_reference, order_title,
            //            appointments_by_person, form_link_section, approval_instructions,
            //            company_signature
            [
                'name'        => 'order mail',
                'code'        => null,
                'type'        => EmailTemplateType::ORDER_APPOINTMENT_CONFIRMATION->value,
                'language'    => $nl,
                'departments' => $allDepartments,
                'subject'     => 'Order {{ order_reference }} | {{ order_title }}',
                'content'     => <<<'HTML'
<p>Beste {{ customer_name }},</p>
<p>Hierbij bevestigen wij uw afspraak(en) voor order {{ order_reference }} ({{ order_title }}).</p>
{{ appointments_by_person }}
{{ form_link_section }}
<p>{{ approval_instructions }}</p>
<p>Met vriendelijke groet,<br>{{ company_signature }}</p>
HTML,
            ],

            // ── Behandelovereenkomst ───────────────────────────────────────────────
            // Variables: customer_name, order_reference, order_title, datum_afspraak,
            //            tijd_afspraak, plaats_afspraak, afspraken_tabel
            [
                'name'        => 'Behandelovereenkomst',
                'code'        => 'treatment-agreement',
                'type'        => EmailTemplateType::ORDER_ACKNOWLEDGEMENT->value,
                'language'    => $nl,
                'departments' => $allDepartments,
                'subject'     => 'Uw behandelingsovereenkomst {{ order_reference }}',
                'content'     => <<<'HTML'
<p>Beste {{ customer_name }},</p>
<p>Hierbij bevestigen wij uw afspraak voor order <strong>{{ order_reference }}</strong> ({{ order_title }}).</p>
{{ afspraken_tabel }}
<p>Heeft u vragen? Neem dan gerust contact met ons op.</p>
<p>Met vriendelijke groet,<br>Privatescan</p>
HTML,
            ],
            [
                'name'        => 'Afspraak bevestiging',
                'code'        => EmailTemplateCode::ACKNOWLEDGE_ORDER_MAIL,
                'type'        => EmailTemplateType::ORDER_APPOINTMENT_CONFIRMATION->value,
                'language'    => $nl,
                'departments' => $allDepartments,
                'subject'     => 'Order {{ order_reference }} | {{ order_title }}',
                'content'     => <<<'HTML'
<p>Beste {{ customer_name }},</p>
<p>Hierbij bevestigen wij uw afspraak(en) voor order {{ order_reference }} ({{ order_title }}).</p>
{{ appointments_by_person }}
{{ form_link_section }}
<p>{{ approval_instructions }}</p>
<p>Met vriendelijke groet,<br>{{ company_signature }}</p>
HTML,
            ],

        ];
    }

    /**
     * Seed email templates.
     *
     * Uses firstOrCreate keyed on `code` (or `name` for the legacy order-mail template)
     * so this seeder is safe to re-run — existing records (incl. admin edits) are never
     * overwritten.
     */
    public function run(): void
    {
        foreach (self::templates() as $template) {
            $key = isset($template['code'])
                ? ['code' => $template['code']]
                : ['name' => $template['name']];

            EmailTemplate::firstOrCreate($key, $template);
        }
    }
}
