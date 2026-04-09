@props([
    'afb' =>  [],
    'order' => null,
])

    <!DOCTYPE html>
<html lang="de">
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
    <h1>Anforderungsbogen Privatescan</h1>

    <div class="section">
        <table>
            <tr>
                <td class="label">Klinik/Krankenhaus</td>
                <td>{{ $afb['header']['clinic_name'] }}</td>
                <td class="label">Datum</td>
                <td>{{ $afb['header']['print_date'] }}</td>
            </tr>
            <tr>
                <td class="label">Von</td>
                <td>{{ $afb['header']['assigned_user'] }}</td>
                <td class="label">Verk.nr</td>
                <td>{{ $afb['header']['order_number'] }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Patientendaten</div>
        <table>
            <tr>
                <td>{{ $afb['patient']['salutation'] }}</td>
                <td><span class="label">Geb. Datum</span>{{ $afb['patient']['birthday'] }}</td>
                <td class="label">Vorname</td>
                <td>{{ $afb['patient']['first_name'] }}</td>
            </tr>
            <tr>
                <td class="label">Nachname(n)</td>
                <td>{{ $afb['patient']['last_name'] }}</td>
                <td class="label">Straße</td>
                <td>{{ $afb['patient']['address'] }}</td>
            </tr>
            <tr>
                <td class="label">PLZ</td>
                <td>{{ $afb['patient']['postal_code'] }}</td>
                <td class="label">Ort</td>
                <td>{{ $afb['patient']['city'] }}</td>
            </tr>
            <tr>
                <td class="label">Land</td>
                <td>{{ $afb['patient']['country'] }}</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <tr>
                <td class="label">Länge (cm)</td>
                <td> {{ $afb['medical']['height'] ?: '-' }}</td>
                <td class="label">Gewicht (kg)</td>
                <td>{{ $afb['medical']['weight'] ?: '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Medizinische Anamnese</div>
        <table>
            <tr>
                <td colspan="2"></td>
                <td colspan="2">Wenn ja, Anmerkung oder Beschreibung:</td>
            </tr>
            <tr>
                <td class="label">Platzangst</td>
                <td>{{ $afb['medical']['claustrophobia'] }}</td>
                <td colspan="2">&nbsp;</td>
            </tr>
            <tr>
                <td class="label">Diabetes</td>
                <td>{{ $afb['medical']['diabetes'] }}</td>
                <td colspan="2">{{ $afb['medical']['diabetes_notes'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Metalle</td>
                <td>{{ $afb['medical']['metals'] }}</td>
                <td colspan="2">{{ $afb['medical']['metals_notes'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Herz OP</td>
                <td>{{ $afb['medical']['heart_surgery'] }}</td>
                <td colspan="2">{{ $afb['medical']['heart_surgery_notes'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Gerät/Implantat</td>
                <td>{{ $afb['medical']['implant'] }}</td>
                <td colspan="2">{{ $afb['medical']['implant_notes'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Allergien</td>
                <td>{{ $afb['medical']['allergies'] }}</td>
                <td colspan="2">{{ $afb['medical']['allergies_notes'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Kontraindic.</td>
                <td>{{ $afb['medical']['contra_indication'] }}</td>
                <td colspan="2">{{ $afb['medical']['contra_notes'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Anmerkung</td>
                <td colspan="3">{{ $afb['medical']['remark'] ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Untersuchungen</div>
        <table>
            <tr>
                <td class="label">Datum</td>
                <td>{{ $order->first_examination_at?->format('d-m-Y') ?: '-' }}</td>
            </tr>
            @forelse ($afb['examinations'] as $exam)
                <tr>
                    <td class="label" style="width: 10%;">{{ $exam['start_time'] }}</td>
                    <td>{!! nl2br(e($exam['clinic_product_description'])) !!}</td>
                </tr>
            @empty
{{--                Technical error, this case should never happen --}}
                <tr>
                    <td colspan="2">Keine Untersuchungen für diese Klinik geplant.</td>
                </tr>
            @endforelse
        </table>
    </div>

    <div class="section">
        <div class="section-title">Anamnese</div>
        <table>
            <tr>
                <td>{!! nl2br(e($afb['clinic_anamnesis'] ?? '-')) !!}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Zusatz Informationen</div>
        <table>
            <tr>
                <td>{!! nl2br(e($afb['extra_info'] ?? '-')) !!}</td>
            </tr>
        </table>
    </div>
</body>
</html>
