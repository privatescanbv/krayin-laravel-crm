@if (empty($appointmentsByPerson))
    <p>Er zijn nog geen afspraken ingepland.</p>
@else
    <table style="width: 100%; border-collapse: collapse; margin: 20px 0; font-family: Arial, sans-serif;">
        <thead>
            <tr style="background-color: #f3f4f6;">
                <th style="padding: 12px; text-align: left; border: 1px solid #d1d5db; font-weight: 600; color: #111827;">Persoon</th>
                <th style="padding: 12px; text-align: left; border: 1px solid #d1d5db; font-weight: 600; color: #111827;">Onderzoek</th>
                <th style="padding: 12px; text-align: left; border: 1px solid #d1d5db; font-weight: 600; color: #111827;">Datum</th>
                <th style="padding: 12px; text-align: left; border: 1px solid #d1d5db; font-weight: 600; color: #111827;">Aankomst tijd</th>
                <th style="padding: 12px; text-align: left; border: 1px solid #d1d5db; font-weight: 600; color: #111827;">Locatie</th>
                <th style="padding: 12px; text-align: left; border: 1px solid #d1d5db; font-weight: 600; color: #111827;">Adres</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($appointmentsByPerson as $personData)
                @foreach ($personData['appointments'] as $index => $appointment)
                    <tr style="background-color: {{ $index % 2 === 0 ? '#ffffff' : '#f9fafb' }};">
                        @if ($index === 0)
                            <td style="padding: 12px; border: 1px solid #d1d5db; color: #374151; vertical-align: top;" rowspan="{{ count($personData['appointments']) }}">
                                <strong>{{ $personData['person_name'] }}</strong>
                            </td>
                        @endif
                        <td style="padding: 12px; border: 1px solid #d1d5db; color: #374151;">{{ $appointment['product_name'] }}</td>
                        <td style="padding: 12px; border: 1px solid #d1d5db; color: #374151;">{{ $appointment['date'] }}</td>
                        <td style="padding: 12px; border: 1px solid #d1d5db; color: #374151;">
                            {{ $appointment['time_from'] }}
                        </td>
                        <td style="padding: 12px; border: 1px solid #d1d5db; color: #374151;">{{ $appointment['clinic_name'] }}</td>
                        <td style="padding: 12px; border: 1px solid #d1d5db; color: #374151;">{{ $appointment['address'] }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
@endif

