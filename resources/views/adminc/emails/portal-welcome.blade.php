@php
    /** @var \Webkul\Contact\Models\Person $person */
    $name = trim($person->name ?? '');
@endphp

@extends('adminc.layouts.mail')

@section('title')
    Uitnodiging Privatescan Portaal
@endsection

@section('content')
    <p>
        Regel uw zorgzaken nu makkelijk en betrouwbaar online via www.privatescan.nl.
    </p>
    <p>
        Beste {{ $name !== '' ? e($name) : 'patiënt' }},
    </p>

    <p>Privatescan nodigt u van harte uit om uw eigen gezondheidsportaal te openen. Hierin kunt u online zelf:</p>

    <ul>
        <li>uw medisch dossier inzien,</li>
        <li>uitslagen en afspraken inzien,</li>
        <li>vragen stellen,</li>
        <li>wijzigingen in uw gegevens (woonadres, e-mailadres en/of telefoonnummer) doorgeven.</li>
    </ul>

    <p><strong>Hoe opent u een gezondheidsportaal op Privatescan.nl?</strong></p>

    <ol>
        <li>Ga naar <a href="{{ $loginUrl }}" target="_blank" rel="noopener">{{ $loginUrl }}</a>.</li>
        <li>Klik op de knop <strong>Inloggen</strong> op de hoofdpagina.</li>
        <li>Log in met uw gegevens.</li>
        <li>Logt u voor de eerste keer in? Dan wordt u gevraagd het contract met uw zorgverlener digitaal te ondertekenen.</li>
    </ol>

    <p>
        De eerste keer kunt u inloggen met dit tijdelijke wachtwoord, die u daarna aanpast naar een eigen wachtwoord.
        <strong>Tijdelijk wachtwoord</strong>:<br>
        <strong>{{ e($temporaryPassword) }}</strong>
    </p>

    <p>Dankzij deze akkoordverklaring bent u ervan verzekerd dat de uitwisseling van uw gegevens met Privatescan/Herniapoli zorgvuldig en betrouwbaar is geregeld.</p>

    <p><strong>Heeft u vragen?</strong><br>
        U kunt op de ondersteuningspagina veelgestelde vragen en instructievideo’s raadplegen via
        <a href="https://www.privatescan.nl" target="_blank" rel="noopener">www.privatescan.nl</a>.
        Daarnaast kunt u telefonisch contact met ons opnemen.</p>

    <p>Met vriendelijke groet,<br>
        Privatescan / Herniapoli</p>
@endsection
