<?php

namespace Database\Seeders;

use App\Enums\Departments;
use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Webkul\User\Models\Group;
use Webkul\User\Models\User;
use Webkul\User\Models\UserDefaultValue;

class UserTestSeeder extends Seeder
{
    /**
     * Get password for a user by email from the seeder data.
     */
    public static function getPasswordForEmail(string $email): ?string
    {
        return static::getUserPasswords()[$email] ?? null;
    }

    /**
     * Get user passwords - defined once, used everywhere.
     *
     * @return array<string, string>
     */
    protected static function getUserPasswords(): array
    {
        return [
            'tester@privatescan.nl' => '8AAZ5jc%e&12',
        ];
    }

    /**
     * Seed the application's database.
     *
     * @param  array  $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        DB::table('users')->delete();
        DB::table('groups')->delete();
        DB::table('user_groups')->delete();

        // Define group IDs for better readability
        $groupPrivatescanId = 1;
        $groupHerniaId = 2;

        // Get department IDs
        $herniaDepartment = Department::where('name', Departments::HERNIA->value)->first();
        $privatescanDepartment = Department::where('name', Departments::PRIVATESCAN->value)->first();

        // Define groups with department relationships
        $groups = [
            [
                'id'            => $groupHerniaId,
                'name'          => Departments::HERNIA->value,
                'description'   => 'Hernia team',
                'department_id' => $herniaDepartment?->id,
            ],
            [
                'id'            => $groupPrivatescanId,
                'name'          => Departments::PRIVATESCAN->value,
                'description'   => 'Privatescan team',
                'department_id' => $privatescanDepartment?->id,
            ],
        ];

        // Create groups
        foreach ($groups as $group) {
            Group::updateOrCreate(
                ['name' => $group['name']],
                $group
            );
        }

        // Get passwords from central location
        $passwords = static::getUserPasswords();

        // Define users with their group assignments
        $users = [
            [
                'first_name'      => 'Test',
                'last_name'       => 'e2e',
                'email'           => 'tester@privatescan.nl',
                'password'        => $passwords['tester@privatescan.nl'],
                'status'          => 1,
                'role_id'         => 1,
                'view_permission' => 'global',
                'group_id'        => null, // Admin has no specific group
                'signature'       => $this->signatureTemplate('Mark', 'Tester', 'tester@privatescan.nl'),
            ]
        ];

        // Create users and assign to groups
        foreach ($users as $userData) {
            $groupId = $userData['group_id'];
            unset($userData['group_id']); // Remove group_id from user data

            // Use updateOrCreate to prevent duplicate key errors during parallel testing
            // Pass plaintext password so User model mutator can capture it for Keycloak sync
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'first_name'      => $userData['first_name'],
                    'last_name'       => $userData['last_name'],
                    'password'        => $userData['password'], // Plaintext - User model will hash it and store plaintext for observer
                    'created_at'      => date('Y-m-d H:i:s'),
                    'updated_at'      => date('Y-m-d H:i:s'),
                    'status'          => $userData['status'],
                    'role_id'         => $userData['role_id'],
                    'view_permission' => $userData['view_permission'],
                    'signature'       => $userData['signature'] ?? null,
                ]
            );

            // Assign user to group if specified
            if ($groupId) {
                DB::table('user_groups')->updateOrInsert(
                    [
                        'user_id'  => $user->id,
                        'group_id' => $groupId,
                    ],
                    [
                        'user_id'  => $user->id,
                        'group_id' => $groupId,
                    ]
                );
            }

            // Seed default user default values
            $defaultSettings = [
                'lead.department_id'   => '2',
                'lead.lead_channel_id' => '1',
                'lead.lead_source_id'  => '6',
                'lead.lead_type_id'    => '1',
            ];

            // Override for Mark Bulthuis
            if ($user->email === 'mark.bulthuis@privatescan.nl') {
                $defaultSettings['lead.department_id'] = '1';
            }

            foreach ($defaultSettings as $key => $value) {
                UserDefaultValue::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'key'     => $key,
                    ],
                    [
                        'value' => $value,
                    ]
                );
            }
        }
    }

    private function signatureTemplate(string $firstName, string $lastName, string $email): string
    {
        return <<<HTML
<div>
<table border="0" cellspacing="0" cellpadding="0" width="800" style="width:600.0pt">
<tbody>
<tr>
<td style="padding:12.75pt 22.5pt 22.5pt 0cm">
<p class="MsoNormal" style="margin-bottom:12.75pt"><span style="font-size:10.5pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#00539f">Met vriendelijke groet,<u></u><u></u></span></p>
<p class="MsoNormal"><b><span style="font-size:10.5pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#ee7029">{$firstName} {$lastName}
</span></b><span style="font-size:10.5pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#00539f"><br>
<a href="mailto:{$email}" target="_blank"><span style="color:blue">{$email}</span></a><u></u><u></u></span></p>
</td>
</tr>
<tr>
<td style="border-top:solid #acbfd9 1.0pt;border-left:none;border-bottom:solid #acbfd9 1.0pt;border-right:none;padding:22.5pt 0cm 22.5pt 0cm">
<table border="0" cellspacing="0" cellpadding="0" width="100%" style="width:100.0%">
<tbody>
<tr>
<td colspan="2" style="padding:1.5pt 1.5pt 1.5pt 0cm">
<p class="MsoNormal"><b><span style="font-size:12.0pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#ee7029">Privatescan BV<u></u><u></u></span></b></p>
</td>
<td valign="bottom" style="padding:1.5pt 1.5pt 1.5pt 1.5pt">
<p class="MsoNormal"><span style="font-size:9.0pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#ee7029">Andere websites<u></u><u></u></span></p>
</td>
<td width="170" style="width:127.5pt;padding:1.5pt 1.5pt 1.5pt 1.5pt">
<p class="MsoNormal"><b><span style="font-size:12.0pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#ee7029">Volg ons ook via<u></u><u></u></span></b></p>
</td>
</tr>
<tr>
<td width="70" style="width:52.5pt;padding:1.5pt 1.5pt 1.5pt 0cm">
<p class="MsoNormal"><span style="font-size:9.0pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#00539f">Telefoon:<u></u><u></u></span></p>
</td>
<td width="150" style="width:112.5pt;padding:1.5pt 1.5pt 1.5pt 1.5pt">
<p class="MsoNormal"><span style="font-size:9.0pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#00539f">+31 (0)74-255 26 80<u></u><u></u></span></p>
</td>
<td style="padding:1.5pt 1.5pt 1.5pt 1.5pt">
<p class="MsoNormal"><span style="font-size:9.0pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#00539f"><a href="http://www.bodyscan.nl/" target="_blank" data-saferedirecturl="https://www.google.com/url?q=http://www.bodyscan.nl/&amp;source=gmail&amp;ust=1762501457339000&amp;usg=AOvVaw2fSjZEGnHYdXF9nY59tgCU"><span style="color:#00539f">www.bodyscan.nl</span></a><u></u><u></u></span></p>
</td>
<td width="170" rowspan="2" style="width:127.5pt;padding:1.5pt 1.5pt 1.5pt 1.5pt">
<table border="0" cellspacing="0" cellpadding="0" width="170" style="width:127.5pt">
<tbody>
<tr>
<td width="37" style="width:27.75pt;padding:0cm 0cm 0cm 0cm">
<p class="MsoNormal"><a href="http://www.facebook.com/privatescan" target="_blank" data-saferedirecturl="https://www.google.com/url?q=http://www.facebook.com/privatescan&amp;source=gmail&amp;ust=1762501457339000&amp;usg=AOvVaw1DEEHf6P17iWLOt3tPfy7m"><span style="font-size:12.0pt;color:blue;text-decoration:none"><img border="0" width="27" height="27" style="width:.2812in;height:.2812in" id="m_7446471370190538772Afbeelding_x0020_1747465774" src="https://mail.google.com/mail/u/0?ui=2&amp;ik=8364b387be&amp;attid=0.0.1&amp;permmsgid=msg-f:1847961942154638185&amp;th=19a5479870917369&amp;view=fimg&amp;fur=ip&amp;permmsgid=msg-f:1847961942154638185&amp;sz=s0-l75-ft&amp;attbid=ANGjdJ-k7X2iPsX0GU1sW8N3P50bofA8H53gwk2SVQ3A35M0qdiP68mRlMpFIG6jONuh1NtYjejCb4VuEH3AGts87Ngcit4GUL0cX3XEn__N6oz-kxerc5SlK5NdE68&amp;disp=emb&amp;zw" data-image-whitelisted="" class="CToWUd" data-bit="iit"></span></a><span style="font-size:12.0pt;font-family:&quot;Calibri&quot;,sans-serif;color:#00539f"><u></u><u></u></span></p>
</td>
<td width="37" style="width:27.75pt;padding:0cm 0cm 0cm 0cm">
<p class="MsoNormal"><a href="http://twitter.com/privatescan" target="_blank" data-saferedirecturl="https://www.google.com/url?q=http://twitter.com/privatescan&amp;source=gmail&amp;ust=1762501457339000&amp;usg=AOvVaw0AVt44x1asV9-0kAyYcsey"><span style="font-size:12.0pt;color:blue;text-decoration:none"><img border="0" width="27" height="27" style="width:.2812in;height:.2812in" id="m_7446471370190538772Afbeelding_x0020_1848894262" src="https://mail.google.com/mail/u/0?ui=2&amp;ik=8364b387be&amp;attid=0.0.2&amp;permmsgid=msg-f:1847961942154638185&amp;th=19a5479870917369&amp;view=fimg&amp;fur=ip&amp;permmsgid=msg-f:1847961942154638185&amp;sz=s0-l75-ft&amp;attbid=ANGjdJ9fxYg1oKn4KsNCviNnKffAD3QmQ_02_CTJwd5Yo0lEykpZOLkzE5rCs3TLGbQYoMENXdGb-NAmUjbqLIdfILh1daofSgFcwjQeQri6mSRTEmqzREIF5BgepLI&amp;disp=emb&amp;zw" data-image-whitelisted="" class="CToWUd" data-bit="iit"></span></a><span style="font-size:12.0pt;color:#00539f"><u></u><u></u></span></p>
</td>
<td style="padding:0cm 0cm 0cm 0cm">
<p class="MsoNormal"><a href="http://www.privatescan.nl/contact/aanmelden-nieuwsbrief" target="_blank" data-saferedirecturl="https://www.google.com/url?q=http://www.privatescan.nl/contact/aanmelden-nieuwsbrief&amp;source=gmail&amp;ust=1762501457339000&amp;usg=AOvVaw39TGpMISM9caDwMiCPtwdO"><span style="font-size:12.0pt;color:blue;text-decoration:none"><img border="0" width="90" height="27" style="width:.9375in;height:.2812in" id="m_7446471370190538772Afbeelding_x0020_151450888" src="https://mail.google.com/mail/u/0?ui=2&amp;ik=8364b387be&amp;attid=0.0.3&amp;permmsgid=msg-f:1847961942154638185&amp;th=19a5479870917369&amp;view=fimg&amp;fur=ip&amp;permmsgid=msg-f:1847961942154638185&amp;sz=s0-l75-ft&amp;attbid=ANGjdJ9WDXbk_sIuJCT3CJ5TbnbfI632AkizjwKs8dz-cHLBrrMJKz9Ixe2spH7bgCibOulA7n-cvePeW_tVSSviCDHJDxPLQSD4A3dM60tjHbTK1WJlsKY753il2pI&amp;disp=emb&amp;zw" data-image-whitelisted="" class="CToWUd" data-bit="iit"></span></a><span style="font-size:12.0pt;color:#00539f"><u></u><u></u></span></p>
</td>
</tr>
</tbody>
</table>
</td>
</tr>
<tr>
<td width="70" style="width:52.5pt;padding:1.5pt 1.5pt 1.5pt 0cm">
<p class="MsoNormal"><span style="font-size:9.0pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#00539f">Email:</span><span style="font-size:9.0pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#00539f"><u></u><u></u></span></p>
</td>
<td width="150" style="width:112.5pt;padding:1.5pt 1.5pt 1.5pt 1.5pt">
<p class="MsoNormal"><span style="font-size:9.0pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#00539f"><a href="mailto:info@privatescan.nl" target="_blank"><span style="color:#00539f">info@privatescan.nl</span></a><u></u><u></u></span></p>
</td>
<td style="padding:1.5pt 1.5pt 1.5pt 1.5pt">
<p class="MsoNormal"><span style="font-size:9.0pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#00539f"><a href="http://www.privatescan.nl/webshop" target="_blank" data-saferedirecturl="https://www.google.com/url?q=http://www.privatescan.nl/webshop&amp;source=gmail&amp;ust=1762501457339000&amp;usg=AOvVaw1jQnb7Wj5duohZKITuC4DV"><span style="color:#00539f">www.privatescan.nl/webshop</span></a><u></u><u></u></span></p>
</td>
</tr>
<tr>
<td width="70" style="width:52.5pt;padding:1.5pt 1.5pt 1.5pt 0cm">
<p class="MsoNormal"><span style="font-size:9.0pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#00539f">Internet:<u></u><u></u></span></p>
</td>
<td width="150" style="width:112.5pt;padding:1.5pt 1.5pt 1.5pt 1.5pt">
<p class="MsoNormal"><span style="font-size:9.0pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#00539f"><a href="http://www.privatescan.nl/" target="_blank" data-saferedirecturl="https://www.google.com/url?q=http://www.privatescan.nl/&amp;source=gmail&amp;ust=1762501457339000&amp;usg=AOvVaw3j0T7R2seK5TUWvnwgPGeq"><span style="color:#00539f">www.privatescan.nl</span></a><u></u><u></u></span></p>
</td>
<td style="padding:1.5pt 1.5pt 1.5pt 1.5pt"></td>
<td width="170" style="width:127.5pt;padding:1.5pt 1.5pt 1.5pt 1.5pt">
<p class="MsoNormal"><span style="font-size:9.0pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#00539f">voor onze speciale acties</span><span style="font-size:9.0pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#00539f"><u></u><u></u></span></p>
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
<p class="MsoNormal"><span style="font-size:12.0pt;color:#00539f"><img border="0" width="70" height="31" style="width:.7291in;height:.3229in" id="m_7446471370190538772Afbeelding_x0020_1161809691" src="https://mail.google.com/mail/u/0?ui=2&amp;ik=8364b387be&amp;attid=0.0.4&amp;permmsgid=msg-f:1847961942154638185&amp;th=19a5479870917369&amp;view=fimg&amp;fur=ip&amp;permmsgid=msg-f:1847961942154638185&amp;sz=s0-l75-ft&amp;attbid=ANGjdJ-1xFd3Eu6utHrtC-FhoVBBO_-QEtiHJfgGnnwXOT90fnTcBD2GjkUJLy4UzyNsGGAaJm-pTbGqPxTW7dcEJ7e2mupOPaqCsg-lWCFgYiDlCW3uzrVnYGPy39Q&amp;disp=emb&amp;zw" data-image-whitelisted="" class="CToWUd" data-bit="iit"></span><span style="font-size:12.0pt;font-family:&quot;Calibri&quot;,sans-serif;color:#00539f"><u></u><u></u></span></p>
</td>
<td valign="top" style="padding:0cm 0cm 0cm 0cm">
<p class="MsoNormal" style="margin-bottom:12.75pt;line-height:11.25pt"><b><span style="font-size:8.5pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#7090bc">Privatescan BV - Adam Smithstraat 39 - 7559 SW Hengelo (O)
 - Tel: +31 (0)74 255 26 80 - Fax: +31(0)74 255 26 99<br>
KvK (08153557) - VAT NL8174.26.218.B.01 - Banknummer 673229971 - IBAN: NL11INGB0673229971 - Swift/BIC: INGBNL2A</span></b><b><span style="font-size:8.5pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#7090bc"><u></u><u></u></span></b></p>
</td>
</tr>
</tbody>
</table>
<p class="MsoNormal" style="margin-bottom:12.75pt;text-align:justify">
<b><span style="font-size:8.5pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#7090bc">Disclaimer</span></b><span style="font-size:8.5pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#7090bc"><br>
Alle e-mail berichten (inclusief bijlagen) van Privatescan BV zijn met grote zorgvuldigheid samengesteld. Voor mogelijke onjuistheid en/of onvolledigheid van de hierin verstrekte informatie kan Privatescan BV geen aansprakelijkheid aanvaarden, evenmin kunnen
 aan de inhoud van dit bericht (inclusief bijlagen) rechten worden ontleend. De inhoud van dit bericht (inclusief bijlagen) kan vertrouwelijke informatie bevatten en is uitsluitend bestemd voor de geadresseerde van dit bericht. Indien u niet de beoogde ontvanger
 van dit bericht bent, verzoekt Privatescan BV u dit bericht te verwijderen, eventuele bijlagen niet te openen en wijst Privatescan BV u op de onrechtmatigheid van het gebruiken, kopiëren of verspreiden van de inhoud van dit bericht (inclusief bijlagen).</span><span style="font-size:8.5pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#7090bc"><u></u><u></u></span></p>
<p class="MsoNormal" style="text-align:justify"><span lang="EN-GB" style="font-size:8.5pt;font-family:&quot;Trebuchet MS&quot;,sans-serif;color:#7090bc">All e-mail messages (including attachments) are
 given in good faith by Privatescan BV. Privatescan BV cannot assume any responsibility for the accuracy or reliability of the information contained in these messages (including attachments), nor shall the information be construed as constituting any obligation
 on the part of Privatescan BV. The information contained in messages (with attachments) from Privatescan BV may be confidential or privileged and is only intended for the use of the receiver named. If you are not the intended recipient, you are requested by
 Privatescan BV to delete the message (with attachments) without opening it and you are notified by Privatescan BV that any disclosure, copying or distribution of the information contained in the message (with attachments) is strictly prohibited and unlawful.
<u></u><u></u></span></p>
</td>
</tr>
</tbody>
</table>
<p class="MsoNormal"><span lang="EN-GB" style="font-size:12.0pt;color:#00539f"><u></u>&nbsp;<u></u></span></p>
</div>
HTML;
    }
}
