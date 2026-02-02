<?php

namespace Webkul\Installer\Database\Seeders\EmailTemplate;

use App\Enums\EmailTemplateType;
use App\Enums\EmailTemplateLanguage;
use App\Enums\Departments;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @param  array  $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        DB::table('email_templates')->delete();

        $now = Carbon::now();

        $defaultLocale = $parameters['locale'] ?? config('app.locale');

        $defaultDepartments = json_encode([Departments::PRIVATESCAN->value]);
        $allDepartments = json_encode([Departments::PRIVATESCAN->value, Departments::HERNIA->value]);

        DB::table('email_templates')->insert([
            [
                'id'          => 1,
                'name'        => trans('installer::app.seeders.email.activity-created', [], $defaultLocale),
                'code'        => 'activity-created',
                'type'        => EmailTemplateType::ALGEMEEN->value,
                'language'    => EmailTemplateLanguage::NEDERLANDS->value,
                'departments' => $allDepartments,
                'subject'     => trans('installer::app.seeders.email.activity-created', [], $defaultLocale).': {%activities.title%}',
                'created_at'  => $now,
                'updated_at'  => $now,
                'content'    => '<p style="font-size: 16px; color: #5e5e5e;">'.trans('installer::app.seeders.email.new-activity', [], $defaultLocale).':</p>
                                <p><strong style="font-size: 16px;">Details</strong></p>
                                <table style="height: 97px; width: 952px;">
                                    <tbody>
                                        <tr>
                                            <td style="width: 116.953px; color: #546e7a; font-size: 16px;">'.trans('installer::app.seeders.email.title', [], $defaultLocale).'</td>
                                            <td style="width: 770.047px; font-size: 16px;">{%activities.title%}</td>
                                        </tr>
                                        <tr>
                                            <td style="width: 116.953px; color: #546e7a; font-size: 16px;">'.trans('installer::app.seeders.email.type', [], $defaultLocale).'</td>
                                                <td style="width: 770.047px; font-size: 16px;">{%activities.type%}</td>
                                        </tr>
                                        <tr>
                                            <td style="width: 116.953px; color: #546e7a; font-size: 16px;">'.trans('installer::app.seeders.email.date', [], $defaultLocale).'</td>
                                            <td style="width: 770.047px; font-size: 16px;">{%activities.schedule_from%} to&nbsp;{%activities.schedule_to%}</td>
                                        </tr>
                                        <tr>
                                            <td style="width: 116.953px; color: #546e7a; font-size: 16px; vertical-align: text-top;">'.trans('installer::app.seeders.email.participants', [], $defaultLocale).'</td>
                                            <td style="width: 770.047px; font-size: 16px;">{%activities.participants%}</td>
                                        </tr>
                                    </tbody>
                                </table>',
            ], [
                'id'          => 2,
                'name'        => trans('installer::app.seeders.email.activity-modified', [], $defaultLocale),
                'code'        => 'activity-modified',
                'type'        => EmailTemplateType::ALGEMEEN->value,
                'language'    => EmailTemplateLanguage::NEDERLANDS->value,
                'departments' => $allDepartments,
                'subject'     => trans('installer::app.seeders.email.activity-modified', [], $defaultLocale).': {%activities.title%}',
                'created_at'  => $now,
                'updated_at'  => $now,
                'content'    => '<p style="font-size: 16px; color: #5e5e5e;">'.trans('installer::app.seeders.email.new-activity-modified', [], $defaultLocale).':</p>
                                <p><strong style="font-size: 16px;">Details</strong></p>
                                <table style="height: 97px; width: 952px;">
                                    <tbody>
                                        <tr>
                                            <td style="width: 116.953px; color: #546e7a; font-size: 16px;">'.trans('installer::app.seeders.email.title', [], $defaultLocale).'</td>
                                            <td style="width: 770.047px; font-size: 16px;">{%activities.title%}</td>
                                        </tr>
                                        <tr>
                                            <td style="width: 116.953px; color: #546e7a; font-size: 16px;">'.trans('installer::app.seeders.email.type', [], $defaultLocale).'</td>
                                            <td style="width: 770.047px; font-size: 16px;">{%activities.type%}</td>
                                        </tr>
                                        <tr>
                                            <td style="width: 116.953px; color: #546e7a; font-size: 16px;">'.trans('installer::app.seeders.email.date', [], $defaultLocale).'</td>
                                            <td style="width: 770.047px; font-size: 16px;">{%activities.schedule_from%} to&nbsp;{%activities.schedule_to%}</td>
                                        </tr>
                                        <tr>
                                            <td style="width: 116.953px; color: #546e7a; font-size: 16px; vertical-align: text-top;">'.trans('installer::app.seeders.email.participants', [], $defaultLocale).'</td>
                                            <td style="width: 770.047px; font-size: 16px;">{%activities.participants%}</td>
                                        </tr>
                                    </tbody>
                                </table>',
            ], [
                'id'          => 3,
                'name'        => 'reply',
                'code'        => 'reply',
                'type'        => EmailTemplateType::ALGEMEEN->value,
                'language'    => EmailTemplateLanguage::NEDERLANDS->value,
                'departments' => $allDepartments,
                'subject'     => 'Re: {%lead.name%}',
                'created_at'  => $now,
                'updated_at'  => $now,
                'content'    => '<p>Geachte heer/mevrouw {{ $lastname }},</p>

    <p>
        [Geef hier je reactie]
    </p>

    <p><br></p>',
            ], [
                'id'          => 4,
                'name'        => 'reply [de]',
                'code'        => 'reply-de',
                'type'        => EmailTemplateType::ALGEMEEN->value,
                'language'    => EmailTemplateLanguage::DUITS->value,
                'departments' => $allDepartments,
                'subject'     => 'Re: {%lead.name%}',
                'created_at'  => $now,
                'updated_at'  => $now,
                'content'    => '<p>Sehr geehrte(r) Herr/Frau {{ $lastname }},</p>

    <p>
        [Dies ist eine andere Vorlage]
    </p>

    <p><br></p>',
            ], [
                'id'          => 5,
                'name'        => 'reply [en]',
                'code'        => 'reply-en',
                'type'        => EmailTemplateType::ALGEMEEN->value,
                'language'    => EmailTemplateLanguage::ENGELS->value,
                'departments' => $allDepartments,
                'subject'     => 'Re: {%lead.name%}',
                'created_at'  => $now,
                'updated_at'  => $now,
                'content'    => '<p>Dear Mr./Ms. {{ $lastname }},</p>

    <p>
        [This is another template]
    </p>

    <p><br></p>',
            ], [
                'id'          => 6,
                'name'        => 'Afspraak bevestiging',
                'code'        => 'appointment-confirmation',
                'type'        => EmailTemplateType::ORDER->value,
                'language'    => EmailTemplateLanguage::NEDERLANDS->value,
                'departments' => $allDepartments,
                'subject'     => 'Orderbevestiging - Ordernummer: {%order.id%}',
                'created_at'  => $now,
                'updated_at'  => $now,
                'content'    => '<p>Geachte heer {{ $lastname }},</p>

    <p>
        Hierbij bevestig ik de afspraak/afspraken voor het laten uitvoeren van medische onderzoeken {{ $afspraken_tabel }}
    </p>

    <p>
        Graag ontvangen wij uiterlijk <strong>{{ $datum_bevestiging }}</strong>
        een definitieve bevestiging van u, zodat ik uw schriftelijke akkoord heb
        en de afspraak definitief kan bevestigen bij de kliniek.
    </p>

    <p>
        In de bijlagen treft u ook een onderzoekkaart aan, waarin u kunt lezen
        hoe een onderzoek als deze zal verlopen en wat u ervan mag verwachten.
        De gezondheidsvragenlijst dient u in te vullen en uiterlijk
        <strong>{{ $datum_vragenlijst }}</strong> aan ons terug te sturen.
    </p>

    <p>
        De afspraak vindt plaats bij de <strong>Ambulante Kardiologie Augusta Klinik</strong>
        aan de Bergstrasse 26, 44791 te Bochum.
    </p>

    <p>
        Wanneer u in de parkeergarage (P1 of P2 – hiervoor krijgt u een uitrijkaartje) bent aangekomen,
        dient u contact op te nemen met de begeleid(st)er. Dit mag op nummer
        <strong>{{ $telefoon_begeleider }}</strong>.
        Hij/zij zal u dan ophalen.
    </p>

    <p>
        Voor na bloed- en urineafname dient u zelf wat te eten mee te nemen.
        Wij zorgen wel voor koffie/thee.
    </p>

    <p>
        U dient nuchter te zijn vanaf <strong>{{ $tijd_nuchter }}</strong> die ochtend.
        Voor dit tijdstip adviseren wij u om wel wat te eten. In de tussentijd mag u wel water drinken,
        maar geen cafeïne houdende dranken en/of etenswaren nuttigen.
    </p>

    <p>
        Ter voorbereiding adviseren wij u een extra setje gemakkelijk zittende kleding mee te nemen,
        zodat u zich kunt omkleden voor de inspanning-ECG.
        Ook is het verzoek om een (kleine) handdoek mee te nemen, zodat u zich even kunt opfrissen
        na de fietstest (inspannings-ECG).
    </p>

    <p>
        Houd rekening met mogelijke verkeersdrukte en vertrek op tijd.
        Als u nog vragen heeft kunt u ons altijd even bellen op nummer
        <strong>{{ $telefoon_kantoor }}</strong>.
    </p>

    <p>
        Wij vernemen graag uw akkoord voor dit onderzoek en wensen u alvast
        veel succes op uw onderzoeksdag.
    </p>

    <p><br></p>',
            ], [
                'id'          => 7,
                'name'        => 'Informatief met GVL',
                'code'        => 'informatief-met-gvl',
                'type'        => EmailTemplateType::GVL->value,
                'language'    => EmailTemplateLanguage::NEDERLANDS->value,
                'departments' => $allDepartments,
                'subject'     => 'GVL staat klaar',
                'created_at'  => $now,
                'updated_at'  => $now,
                'content'    => '<p>Geachte client,</p>

    <p>
        Privatescan / Herniapoli heeft een <a href="{{ $gvl_form_link }}" target="_blank" style="color: #2563eb; text-decoration: underline;">document</a> voor u klaargezet in uw portaal. Deze is van belang voor uw onderzoek, zodat de arts zich alvast kan voorbereiden op uw komst.
    </p>
    <p>Graag zien wij deze uiterlijk {{ $gvl_deadline }} voor 10.00 uur retour.</p>
    <p>
        U kunt deze gegevens invullen via het onderstaande GVL-formulier. Dit formulier helpt ons om een beter beeld te krijgen van uw situatie en om u de best mogelijke zorg te kunnen bieden.
    </p>
    <p>
        Mocht u vragen hebben of hulp nodig hebben bij het invullen van het formulier, neem dan gerust contact met ons op.
    </p>
    <p>Privatescan / Herniapoli</p>',
            ], [
                'id'          => 8,
                'name'        => 'Patiëntportaal notificatie',
                'code'        => 'patient-portal-notification',
                'type'        => EmailTemplateType::PATIENT->value,
                'language'    => EmailTemplateLanguage::NEDERLANDS->value,
                'departments' => $allDepartments,
                'subject'     => 'Nieuwe melding in uw patiëntportaal',
                'created_at'  => $now,
                'updated_at'  => $now,
                'content'     => '<p>Geachte heer/mevrouw {{ $lastname }},</p>

<p>Er staat een nieuwe melding voor u klaar in uw patiëntportaal. Om privacy-redenen vermelden we de inhoud niet per e-mail.</p>

<p>U kunt inloggen via: <a href="{%portal_url%}" target="_blank" style="color: #2563eb; text-decoration: underline;">{%portal_url%}</a></p>

<p>Met vriendelijke groet,<br>Privatescan</p>',
            ],
        ]);
    }
}
