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
<p>{{ datum_afspraak }} {{ tijd_afspraak }}</p>
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
                'code'        => EmailTemplateCode::PATIENT_PORTAL_NOTIFICATION->value,
                'type'        => $patient,
                'language'    => $nl,
                'departments' => $allDepartments,
                'subject'     => 'Welkom bij het Privatescan patiëntportaal',
                'content'     => <<<'HTML'
    <p>Regel uw zorgzaken nu makkelijk en betrouwbaar online via www.privatescan.nl.</p>
    <p>Beste {{ person.first_name }},</p>
    <p>Privatescan nodigt u van harte uit om uw eigen gezondheidsportaal te openen. Hierin kunt u online zelf:</p>
    <ul>
        <li>uw medisch dossier inzien,</li>
        <li>uitslagen en afspraken inzien,</li>
        <li>vragen stellen,</li>
        <li>wijzigingen in uw gegevens (woonadres, e-mailadres en/of telefoonnummer) doorgeven.</li>
    </ul>
    <p><strong>Hoe opent u een gezondheidsportaal op Privatescan.nl?</strong></p>
    <ol>
        <li>Ga naar <a href="{{ portal_url }}" target="_blank" rel="noopener">{{ $loginUrl }}</a>.</li>
        <li>Klik op de knop <strong>Inloggen</strong> op de hoofdpagina.</li>
        <li>Log in met uw gegevens.</li>
        <li>Logt u voor de eerste keer in? Dan wordt u gevraagd het contract met uw zorgverlener digitaal te ondertekenen.</li>
    </ol>
    <p>
        De eerste keer kunt u inloggen met dit tijdelijke wachtwoord, die u daarna aanpast naar een eigen wachtwoord.
        <strong>Tijdelijk wachtwoord</strong>:<br>
        <strong>{{ $temporaryPassword }}</strong>
    </p>
    <p>Dankzij deze akkoordverklaring bent u ervan verzekerd dat de uitwisseling van uw gegevens met Privatescan/Herniapoli zorgvuldig en betrouwbaar is geregeld.</p>
    <p><strong>Heeft u vragen?</strong><br>
        U kunt op de ondersteuningspagina veelgestelde vragen en instructievideo’s raadplegen via
        <a href="https://www.privatescan.nl" target="_blank" rel="noopener">www.privatescan.nl</a>.
        Daarnaast kunt u telefonisch contact met ons opnemen.</p>
    <p>Met vriendelijke groet,<br>
        Privatescan / Herniapoli</p>
HTML,
            ],

            // ── Patiëntportaal: nieuwe content (digest-mail) ───────────────────────
            // Variables: person.first_name, portal_url, portal_link, lastname
            [
                'name'        => 'Patiëntportaal nieuwe content',
                'code'        => EmailTemplateCode::PATIENT_PORTAL_NOTIFICATION_NEW_CONTENT->value,
                'type'        => $patient,
                'language'    => $nl,
                'departments' => $allDepartments,
                'subject'     => 'Nieuwe inhoud in uw patiëntportaal',
                'content'     => <<<'HTML'
<p>Beste {{ person.first_name }},</p>
<p>Er staan nieuwe documenten, formulieren of verzoeken voor je klaar in jouw patiëntportaal.</p>
<p>Klik hier om deze te bekijken:<br><a href="{{ portal_link }}">{{ portal_link }}</a></p>
<p>Met vriendelijke groet,<br>Privatescan</p>
HTML,
            ],

            // ── Patiënt: wachtwoord vergeten ───────────────────────────────────────
            // Variables: person.first_name, reset_url
            [
                'name'        => 'Patiënt wachtwoord vergeten',
                'code'        => EmailTemplateCode::PATIENT_FORGOT_PASSWORD->value,
                'type'        => $patient,
                'language'    => $nl,
                'departments' => $allDepartments,
                'subject'     => 'Wachtwoord resetten voor uw patiëntportaal',
                'content'     => <<<'HTML'
<p>Beste {{ person.first_name }},</p>
<p>U heeft een verzoek ingediend om uw wachtwoord voor het Privatescan patiëntportaal te resetten.</p>
<p>Klik op de onderstaande link om een nieuw wachtwoord in te stellen:</p>
<p><a href="{{ reset_url }}">Wachtwoord resetten</a></p>
<p>Deze link is <strong>1 uur</strong> geldig. Heeft u geen verzoek ingediend? Dan kunt u deze e-mail negeren.</p>
<p>Met vriendelijke groet,<br>Privatescan</p>
HTML,
            ],

            // ── CRM-medewerker: wachtwoord vergeten ────────────────────────────────
            // Variables: user.first_name, reset_url
            [
                'name'        => 'CRM wachtwoord vergeten',
                'code'        => EmailTemplateCode::CRM_FORGOT_PASSWORD->value,
                'type'        => $algemeen,
                'language'    => $nl,
                'departments' => $allDepartments,
                'subject'     => 'Wachtwoord resetten voor uw CRM account',
                'content'     => <<<'HTML'
<p>Beste {{ user.first_name }},</p>
<p>U heeft een verzoek ingediend om uw wachtwoord voor het Privatescan CRM te resetten.</p>
<p>Klik op de onderstaande link om een nieuw wachtwoord in te stellen:</p>
<p><a href="{{ reset_url }}">Wachtwoord resetten</a></p>
<p>Deze link is <strong>1 uur</strong> geldig. Heeft u geen verzoek ingediend? Dan kunt u deze e-mail negeren.</p>
<p>Met vriendelijke groet,<br>Privatescan</p>
HTML,
            ],

            // ── Medewerker aanmaken ────────────────────────────────────────────────
            // Variables: user.first_name, user.last_name, user.email
            [
                'name'        => 'Medewerker aanmaken',
                'code'        => EmailTemplateCode::CREATE_USER->value,
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
                'name'        => 'Order mail',
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
                'name'        => 'Orderbevestiging TB zonder bloed NL',
                'code'        => 'order-ackn-TB-no-blood',
                'type'        => EmailTemplateType::ORDER_ACKNOWLEDGEMENT->value,
                'language'    => $nl,
                'departments' => $allDepartments,
                'subject'     => 'Orderbevestiging TB zonder bloed NL',
                'content'     => <<<'HTML'
<p>{{ order_aanhef }}<br>{{ address_full }}</p>
<p>&nbsp;</p>
<p style="text-align: right;">Hengelo, {{ current_date }}</p>
<p style="text-align: left;">&nbsp;</p>
<p style="text-align: left;">Onze referentie: {{ order_reference }}</p>
<p style="text-align: left;">Geachte {{ customer_name}},</p>
<p style="text-align: left;">Hierbij bevestigen wij de door u aan Privatescan verstrekte opdracht voor het laten uitvoeren van een Total Bodyscan zonder aanvullend bloedonderzoek.</p>
<p style="text-align: left;"><strong>Afspraakgegevens:</strong><br>Uw afspraak is tot stand gekomen in overleg met {{ adviseur }} en staat ingepland voor {{ datum_afspraak }} om {{ tijd_afspraak }} uur in &nbsp;{{ plaats_afspraak }}. {{ meldplek }}</p>
<p style="text-align: left;">De volgende onderzoeken worden uitgevoerd:<br>{{ order_summary_table }}</p>
<p>Voor een optimaal verloop en een goede voorbereiding op de onderzoeken verzoeken wij u om de meegestuurde informatie zorgvuldig door te lezen.</p>
<p>Graag ontvangen we zo spoedig mogelijk, bij voorkeur per e-mail, uw akkoord voor de geplande onderzoeken alsmede de door u ingevulde gezondheidsvragenlijst(en). Het totaalbedrag van {{ order_total }} dient v&oacute;&oacute;r de onderzoeksdatum op onze bank te zijn bijgeschreven op ons IBAN rekeningnummer, NL11 INGB 0673 2299 71 &nbsp;o.v.v. onze referentie: {{ order_reference }}. U ontvangt separaat de factuur.</p>
<p>Met dank voor uw opdracht wens ik u alvast een aangenaam verblijf in de kliniek.</p>
<p>Met vriendelijke groet,</p>
<table style="border-collapse: collapse; width: 100%; border-width: 0px;" border="1"><colgroup><col style="width: 50%;"><col style="width: 50%;"></colgroup>
<tbody>
<tr>
<td style="border-width: 0px;">{{ adviseur }}</td>
<td style="border-width: 0px;">voor akkoord:</td>
</tr>
<tr>
<td style="border-width: 0px;">Binnendienst adviseur</td>
<td style="border-width: 0px;">{{ person.salutation }} {{ person.initials }} {{ person.last_name }}</td>
</tr>
</tbody>
</table>
<!-- pagebreak -->
<p><strong>Rapportage, vertaling en begeleiding</strong><br>Na afloop van de onderzoeken (MRI en/of CT) krijgt u een CD/DVD en/of QR-code mee van de gemaakte beelden. Van de resultaten ontvangt u een schriftelijk Duits verslag. Een vertaling van het verslag maakt geen onderdeel uit van het onderzoeksprogramma. Uit ervaring is gebleken dat de meerderheid van onze cli&euml;nten geen behoefte heeft aan een vertaling indien er bij de onderzoeken geen bijzonderheden zijn aangetroffen. Een vertaling van het verslag (volledig of samenvatting) kunt u bestellen bij de begeleiding in de kliniek of achteraf bij Privatescan. Namens Privatescan is op de onderzoeksdag Nederlandstalige begeleiding aanwezig.</p>
<p><strong>Aankomst kliniek</strong><br>Ten behoeve van uw onderzoeken heeft Privatescan tijd op apparatuur (MRI, CT etc.) en bij artsen gereserveerd. Om te voorkomen dat er op uw onderzoeksdag (te) lange vertragingen en wachttijden ontstaan, is het van belang dat u uiterlijk op de overeengekomen tijd aanwezig bent in de kliniek. Houdt daarom rekening met mogelijke files op uw route. Bij vertraging van meer dan 30 minuten, bestaat de mogelijkheid dat uw onderzoek dient te worden verplaatst om verder oplopende wachttijden voor de mensen die na u staan ingepland te voorkomen. Wij vragen u dan ook vriendelijk om bij vertraging direct contact met ons op te nemen. Vanaf &rsquo;s morgens 8:30 uur zijn we hiervoor bereikbaar via 074 &ndash; 255 2 680.</p>
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
            [
                'name'        => 'Patient notificatie er staat nieuwe content klaar',
                'code'        => EmailTemplateCode::PATIENT_PORTAL_NOTIFICATION_NEW_CONTENT,
                'type'        => EmailTemplateType::PATIENT->value,
                'language'    => $nl,
                'departments' => $allDepartments,
                'subject'     => 'Nieuw bericht beschikbaar in uw patiëntenportaal',
                'content'     => <<<'HTML'
<p>Beste {{ customer_name }},</p>
<p>Er staan nieuwe documenten, formulieren of verzoeken voor je klaar in jouw patiëntportaal.</p>
<p>Klik hier om deze te bekijken:<br>
{portal_link}</p>
<p>Met vriendelijke groet,
<br>{{ company_signature }}
</p>
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
