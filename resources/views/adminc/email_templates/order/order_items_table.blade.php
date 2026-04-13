@php
    use Illuminate\Support\Collection;

    $items = $order->orderItems ?? collect();

    if (! $items instanceof Collection) {
        $items = collect($items);
    }

    $filterPersonId = $personId ?? null;
    if ($filterPersonId) {
        $items = $items->where('person_id', $filterPersonId)->values();
    }

    $totalPrice = $filterPersonId
        ? $items->sum('total_price')
        : ($order->total_price ?? 0);
@endphp

@if ($items->isEmpty())
    <p>Er zijn nog geen orderregels toegevoegd.</p>
@else
    <table style="width:100%; border-collapse:collapse; margin:20px 0;">
        <thead>
        <tr style="background-color:#f5f5f5;">
            <th style="padding:10px; text-align:left; border:1px solid #ddd;">Product</th>
            @unless ($filterPersonId)
                <th style="padding:10px; text-align:left; border:1px solid #ddd;">Persoon</th>
            @endunless
            <th style="padding:10px; text-align:right; border:1px solid #ddd;">Aantal</th>
            <th style="padding:10px; text-align:right; border:1px solid #ddd;">Prijs</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($items as $item)
            <tr>
                <td style="padding:8px; border-bottom:1px solid #e5e7eb;">{{ $item->getProductName() ?: 'Onbekend product' }}</td>
                @unless ($filterPersonId)
                    <td style="padding:8px; border-bottom:1px solid #e5e7eb;">{{ $item->person->name ?? '-' }}</td>
                @endunless
                <td style="padding:8px; text-align:center; border-bottom:1px solid #e5e7eb;">{{ $item->quantity ?? 0 }}</td>
                <td style="padding:8px; text-align:right; border-bottom:1px solid #e5e7eb;">
                    € {{ number_format((float)($item->total_price ?? 0), 2, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
        <tr>
            <td colspan="{{ $filterPersonId ? 2 : 3 }}" style="padding:8px; text-align:right; font-weight:600; border-top:2px solid #e5e7eb;">
                Totaal:
            </td>
            <td style="padding:8px; text-align:right; font-weight:600; border-top:2px solid #e5e7eb;">
                € {{ number_format((float)$totalPrice, 2, ',', '.') }}</td>
        </tr>
        </tfoot>
    </table>
@endif

