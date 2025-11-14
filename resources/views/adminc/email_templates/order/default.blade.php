@extends('adminc.email_templates.base.layout')

@section('content')
    <div class="order-confirmation">
        <h2>Orderbevestiging</h2>

        <p>Geachte {{ $customer_name ?? 'heer/mevrouw' }},</p>

        <p>Hierbij bevestigen wij uw order met de volgende gegevens:</p>

        <div class="order-details">
            <p><strong>Ordernummer:</strong> {{ $order->id ?? '' }}</p>
            <p><strong>Titel:</strong> {{ $order->title ?? '' }}</p>
            <p><strong>Datum:</strong> {{ $order->created_at ? $order->created_at->format('d-m-Y') : '' }}</p>
            <p><strong>Status:</strong> {{ $order->status?->label() ?? '' }}</p>
        </div>

        @if (isset($order->orderItems) && $order->orderItems->count() > 0)
            <h3>Orderregels</h3>
            <table class="order-items-table" style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                <thead>
                    <tr style="background-color: #f5f5f5;">
                        <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Product</th>
                        <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Persoon</th>
                        <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">Aantal</th>
                        <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">Prijs</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->orderItems as $item)
                        <tr>
                            <td style="padding: 10px; border: 1px solid #ddd;">{{ $item->product->name ?? 'Onbekend product' }}</td>
                            <td style="padding: 10px; border: 1px solid #ddd;">{{ $item->person->name ?? '-' }}</td>
                            <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">{{ $item->quantity ?? 0 }}</td>
                            <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">€ {{ number_format((float)($item->total_price ?? 0), 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="background-color: #f5f5f5; font-weight: bold;">
                        <td colspan="3" style="padding: 10px; text-align: right; border: 1px solid #ddd;">Totaal:</td>
                        <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">€ {{ number_format((float)($order->total_price ?? 0), 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        @endif

        <p>Met vriendelijke groet,</p>
        <p>{{ config('app.name', 'Privatescan') }}</p>
    </div>
@endsection

