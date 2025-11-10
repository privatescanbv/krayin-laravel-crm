@extends('adminc.email_templates.base.layout')

@section('content')
    <p>Geachte {{ $lead->name ?? 'klant' }},</p>

    <p>Hierbij ontvangt u de samenvatting van uw order:</p>
    <ul>
        <li>Ordernummer: {{ $order->id }}</li>
        <li>Titel: {{ $order->title }}</li>
        <li>Totaalbedrag: € {{ number_format((float)($order->total_price ?? 0), 2, ',', '.') }}</li>
    </ul>

    <p>Graag ontvangen wij uw akkoord door te reageren op deze e-mail.</p>

    <p>Met vriendelijke groet,</p>
@endsection
