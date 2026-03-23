<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>AFB</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #111;
        }

        h1 {
            font-size: 22px;
            margin: 0 0 8px 0;
        }

        .section {
            margin-top: 14px;
        }

        .section-title {
            background: #d9d9d9;
            font-weight: bold;
            border: 1px solid #1f2937;
            border-bottom: 0;
            padding: 4px 6px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th, td {
            border: 1px solid #1f2937;
            padding: 3px 6px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .label {
            font-weight: bold;
            width: 23%;
        }

        .notes-label {
            width: 36%;
            font-weight: bold;
        }

        .muted {
            color: #555;
        }
    </style>
</head>
<body>
    <h1>Aanvraagformulier Behandeling</h1>

    <div class="section">
        <table>
            <tr>
                <td class="label">Kliniek/Krankenhaus</td>
                <td>{{ $afb['header']['clinic_name'] }}</td>
                <td class="label">Printdatum</td>
                <td>{{ $afb['header']['print_date'] }}</td>
            </tr>
            <tr>
                <td class="label">Toegewezen medewerker</td>
                <td>{{ $afb['header']['assigned_user'] }}</td>
                <td class="label">Ordernummer</td>
                <td>{{ $afb['header']['order_number'] }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Patientgegevens</div>
        <table>
            <tr>
                <td class="label">Aanhef</td>
                <td>{{ $afb['patient']['salutation'] }}</td>
                <td class="label">Voornaam</td>
                <td>{{ $afb['patient']['first_name'] }}</td>
            </tr>
            <tr>
                <td class="label">Achternaam</td>
                <td>{{ $afb['patient']['last_name'] }}</td>
                <td class="label">Adres</td>
                <td>{{ $afb['patient']['address'] }}</td>
            </tr>
            <tr>
                <td class="label">Postcode</td>
                <td>{{ $afb['patient']['postal_code'] }}</td>
                <td class="label">Plaats</td>
                <td>{{ $afb['patient']['city'] }}</td>
            </tr>
            <tr>
                <td class="label">Land</td>
                <td>{{ $afb['patient']['country'] }}</td>
                <td class="label">Lengte / Gewicht</td>
                <td>
                    {{ $afb['medical']['height'] ? $afb['medical']['height'].' cm' : '-' }}
                    /
                    {{ $afb['medical']['weight'] ? $afb['medical']['weight'].' kg' : '-' }}
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Medische anamnese</div>
        <table>
            <tr>
                <td class="label">Claustrofobie</td>
                <td>{{ $afb['medical']['claustrophobia'] }}</td>
                <td class="notes-label">Opmerking</td>
                <td class="muted">Alleen notities indien aanwezig</td>
            </tr>
            <tr>
                <td class="label">Diabetes</td>
                <td>{{ $afb['medical']['diabetes'] }}</td>
                <td>{{ $afb['medical']['diabetes_notes'] ?? '-' }}</td>
                <td></td>
            </tr>
            <tr>
                <td class="label">Metalen</td>
                <td>{{ $afb['medical']['metals'] }}</td>
                <td>{{ $afb['medical']['metals_notes'] ?? '-' }}</td>
                <td></td>
            </tr>
            <tr>
                <td class="label">Hartoperatie</td>
                <td>{{ $afb['medical']['heart_surgery'] }}</td>
                <td>{{ $afb['medical']['heart_surgery_notes'] ?? '-' }}</td>
                <td></td>
            </tr>
            <tr>
                <td class="label">Implantaten</td>
                <td>{{ $afb['medical']['implant'] }}</td>
                <td>{{ $afb['medical']['implant_notes'] ?? '-' }}</td>
                <td></td>
            </tr>
            <tr>
                <td class="label">Allergieen</td>
                <td>{{ $afb['medical']['allergies'] }}</td>
                <td>{{ $afb['medical']['allergies_notes'] ?? '-' }}</td>
                <td></td>
            </tr>
            <tr>
                <td class="label">Contra-indicaties</td>
                <td>{{ $afb['medical']['contra_indication'] }}</td>
                <td>{{ $afb['medical']['contra_notes'] ?? '-' }}</td>
                <td></td>
            </tr>
            <tr>
                <td class="label">Opmerking</td>
                <td colspan="3">{{ $afb['medical']['remark'] ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Onderzoeken</div>
        <table>
            <tr>
                <th style="width: 18%;">Datum</th>
                <th style="width: 16%;">Tijd onderzoek</th>
                <th style="width: 16%;">Start</th>
                <th>Omschrijving</th>
            </tr>
            @forelse($afb['examinations'] as $exam)
                <tr>
                    <td>{{ $exam['date'] }}</td>
                    <td>{{ $exam['appointment_time'] }}</td>
                    <td>{{ $exam['start_time'] }}</td>
                    <td>{!! nl2br(e($exam['clinic_description'])) !!}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">Geen onderzoeken gepland voor deze kliniek.</td>
                </tr>
            @endforelse
        </table>
    </div>

    <div class="section">
        <div class="section-title">Anamnese (kliniek)</div>
        <table>
            <tr>
                <td>{!! nl2br(e($afb['clinic_anamnesis'] ?? '-')) !!}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Extra informatie</div>
        <table>
            <tr>
                <td>{!! nl2br(e($afb['extra_info'] ?? '-')) !!}</td>
            </tr>
        </table>
    </div>
</body>
</html>
