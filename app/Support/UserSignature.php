<?php

namespace App\Support;

class UserSignature
{
    /**
     * Generate the default email signature HTML for a user.
     */
    public static function generate(string $firstName, string $lastName, string $email): string
    {
        return <<<HTML
<div>
    <table border="0" cellspacing="0" cellpadding="0" width="800" style="width:600.0pt">
        <tbody>
        <tr>
            <td style="padding:12.75pt 22.5pt 22.5pt 0cm">
                <p class="MsoNormal" style="margin-bottom:12.75pt"><span style="font-size:10.5pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#00539f">Met vriendelijke groet,</span></p>
                <p class="MsoNormal"><b><span style="font-size:10.5pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#ee7029">{$firstName} {$lastName}
</span></b><span style="font-size:10.5pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#00539f"><br>
<a href="mailto:{$email}" target="_blank"><span style="color:blue">{$email}</span></a></span></p>
            </td>
        </tr>
        <tr>
            <td style="border-top:solid #acbfd9 1.0pt;border-left:none;border-bottom:solid #acbfd9 1.0pt;border-right:none;padding:22.5pt 0cm 22.5pt 0cm">
                <table border="0" cellspacing="0" cellpadding="0" width="100%" style="width:100.0%">
                    <tbody>
                    <tr>
                        <td colspan="2" style="padding:1.5pt 1.5pt 1.5pt 0cm">
                            <p class="MsoNormal"><b><span style="font-size:12.0pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#ee7029">Privatescan BV</span></b></p>
                        </td>
                        <td valign="bottom" style="padding:1.5pt 1.5pt 1.5pt 1.5pt">
                            <p class="MsoNormal"><span style="font-size:9.0pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#ee7029">Andere websites</span></p>
                        </td>
                        <td width="170" style="width:127.5pt;padding:1.5pt 1.5pt 1.5pt 1.5pt">
                            <p class="MsoNormal"><b><span style="font-size:12.0pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#ee7029">Volg ons ook via</span></b></p>
                        </td>
                    </tr>
                    <tr>
                        <td width="70" style="width:52.5pt;padding:1.5pt 1.5pt 1.5pt 0cm">
                            <p class="MsoNormal"><span style="font-size:9.0pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#00539f">Telefoon:</span></p>
                        </td>
                        <td width="150" style="width:112.5pt;padding:1.5pt 1.5pt 1.5pt 1.5pt">
                            <p class="MsoNormal"><span style="font-size:9.0pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#00539f">+31 (0)74-255 26 80</span></p>
                        </td>
                        <td style="padding:1.5pt 1.5pt 1.5pt 1.5pt">
                            <p class="MsoNormal"><span style="font-size:9.0pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#00539f"><a href="http://www.bodyscan.nl/" target="_blank"><span style="color:#00539f">www.bodyscan.nl</span></a></span></p>
                        </td>
                        <td width="170" rowspan="2" style="width:127.5pt;padding:1.5pt 1.5pt 1.5pt 1.5pt">
                            <table border="0" cellspacing="0" cellpadding="0" width="170" style="width:127.5pt">
                                <tbody>
                                <tr>
                                    <td width="37" style="width:27.75pt;padding:0cm 0cm 0cm 0cm">
                                        <p class="MsoNormal"><a href="http://www.facebook.com/privatescan" target="_blank"><span style="color:blue;text-decoration:none"><img border="0" width="27" height="27" style="width:.2812in;height:.2812in" src="/images/email-signature/fb.gif"></span></a></p>
                                    </td>
                                    <td width="37" style="width:27.75pt;padding:0cm 0cm 0cm 0cm">
                                        <p class="MsoNormal"><a href="http://twitter.com/privatescan" target="_blank"><span style="color:blue;text-decoration:none"><img border="0" width="27" height="27" style="width:.2812in;height:.2812in" src="/images/email-signature/twitter.gif"></span></a></p>
                                    </td>
                                    <td style="padding:0cm 0cm 0cm 0cm">
                                        <p class="MsoNormal"><a href="http://www.privatescan.nl/contact/aanmelden-nieuwsbrief" target="_blank"><span style="color:blue;text-decoration:none"><img border="0" width="90" height="27" style="width:.9375in;height:.2812in" src="/images/email-signature/newsletter.gif"></span></a></p>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td width="70" style="width:52.5pt;padding:1.5pt 1.5pt 1.5pt 0cm">
                            <p class="MsoNormal"><span style="font-size:9.0pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#00539f">Email:</span></p>
                        </td>
                        <td width="150" style="width:112.5pt;padding:1.5pt 1.5pt 1.5pt 1.5pt">
                            <p class="MsoNormal"><span style="font-size:9.0pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#00539f"><a href="mailto:info@privatescan.nl" target="_blank"><span style="color:#00539f">info@privatescan.nl</span></a></span></p>
                        </td>
                        <td style="padding:1.5pt 1.5pt 1.5pt 1.5pt">
                            <p class="MsoNormal"><span style="font-size:9.0pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#00539f"><a href="http://www.privatescan.nl/webshop" target="_blank"><span style="color:#00539f">www.privatescan.nl/webshop</span></a></span></p>
                        </td>
                    </tr>
                    <tr>
                        <td width="70" style="width:52.5pt;padding:1.5pt 1.5pt 1.5pt 0cm">
                            <p class="MsoNormal"><span style="font-size:9.0pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#00539f">Internet:</span></p>
                        </td>
                        <td width="150" style="width:112.5pt;padding:1.5pt 1.5pt 1.5pt 1.5pt">
                            <p class="MsoNormal"><span style="font-size:9.0pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#00539f"><a href="http://www.privatescan.nl/" target="_blank"><span style="color:#00539f">www.privatescan.nl</span></a></span></p>
                        </td>
                        <td style="padding:1.5pt 1.5pt 1.5pt 1.5pt"></td>
                        <td width="170" style="width:127.5pt;padding:1.5pt 1.5pt 1.5pt 1.5pt">
                            <p class="MsoNormal"><span style="font-size:9.0pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#00539f">voor onze speciale acties</span></p>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </td>
        </tr>
        <tr>
            <td style="background:#e2e9f3;padding:22.5pt 22.5pt 22.5pt 22.5pt">
                <table border="0" cellspacing="0" cellpadding="0" width="100%" style="width:100.0%">
                    <tbody>
                    <tr>
                        <td valign="top" style="padding:0cm 0cm 0cm 0cm">
                            <p class="MsoNormal"><span style="font-size:12.0pt;color:#00539f"><img border="0" width="70" height="31" style="width:.7291in;height:.3229in" src="/images/email-signature/ps-mini-logo.png"></span></p>
                        </td>
                        <td valign="top" style="padding:0cm 0cm 0cm 0cm">
                            <p class="MsoNormal" style="margin-bottom:12.75pt;line-height:11.25pt"><b><span style="font-size:8.5pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#7090bc">Privatescan BV - Adam Smithstraat 39 - 7559 SW Hengelo (O)
 - Tel: +31 (0)74 255 26 80 - Fax: +31(0)74 255 26 99<br>
KvK (08153557) - VAT NL8174.26.218.B.01 - Banknummer 673229971 - IBAN: NL11INGB0673229971 - Swift/BIC: INGBNL2A</span></b></p>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <p class="MsoNormal" style="margin-bottom:12.75pt;text-align:justify">
                    <b><span style="font-size:8.5pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#7090bc">Disclaimer</span></b><span style="font-size:8.5pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#7090bc"><br>
Alle e-mail berichten (inclusief bijlagen) van Privatescan BV zijn met grote zorgvuldigheid samengesteld. Voor mogelijke onjuistheid en/of onvolledigheid van de hierin verstrekte informatie kan Privatescan BV geen aansprakelijkheid aanvaarden, evenmin kunnen
 aan de inhoud van dit bericht (inclusief bijlagen) rechten worden ontleend. De inhoud van dit bericht (inclusief bijlagen) kan vertrouwelijke informatie bevatten en is uitsluitend bestemd voor de geadresseerde van dit bericht. Indien u niet de beoogde ontvanger
 van dit bericht bent, verzoekt Privatescan BV u dit bericht te verwijderen, eventuele bijlagen niet te openen en wijst Privatescan BV u op de onrechtmatigheid van het gebruiken, kopiëren of verspreiden van de inhoud van dit bericht (inclusief bijlagen).</span></p>
                <p class="MsoNormal" style="text-align:justify"><span lang="EN-GB" style="font-size:8.5pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#7090bc">All e-mail messages (including attachments) are
 given in good faith by Privatescan BV. Privatescan BV cannot assume any responsibility for the accuracy or reliability of the information contained in these messages (including attachments), nor shall the information be construed as constituting any obligation
 on the part of Privatescan BV. The information contained in messages (with attachments) from Privatescan BV may be confidential or privileged and is only intended for the use of the receiver named. If you are not the intended recipient, you are requested by
 Privatescan BV to delete the message (with attachments) without opening it and you are notified by Privatescan BV that any disclosure, copying or distribution of the information contained in the message (with attachments) is strictly prohibited and unlawful.
</span></p>
            </td>
        </tr>
        </tbody>
    </table>
    <p class="MsoNormal"><span lang="EN-GB" style="font-size:12.0pt;color:#00539f">&nbsp;</span></p>
</div>
HTML;
    }
}
