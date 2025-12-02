@php
    /** @var \Webkul\Contact\Models\Person $person */
    $name = trim($person->name ?? '');
@endphp

@extends('adminc.layouts.mail')

@section('title')
    Welkom bij in het patiënt portaal
@endsection

@section('content')
    <p>
        Beste {{ $name !== '' ? e($name) : 'patiënt' }},
    </p>

    <p>
        Voor u is een account aangemaakt voor het
        Privatescan patiëntportaal. Met dit portaal kunt u uw afspraken en onderzoeken
        veilig online bekijken.
    </p>

    <p>
        <strong>Tijdelijk wachtwoord</strong><br>
        Uw tijdelijke wachtwoord is: <strong>{{ e($temporaryPassword) }}</strong>
    </p>

    <p>
        <strong>Inloggen en wachtwoord wijzigen</strong><br>
        Klik op de onderstaande link om in te loggen. Na het inloggen wordt u gevraagd
        om uw wachtwoord te wijzigen:
    </p>

    <p>
        <a href="{{ $loginUrl }}" style="color: #0e90d9;">
            Client portaal inloggen
        </a>
    </p>

    <p>
        Bewaar uw nieuwe wachtwoord zorgvuldig en deel dit niet met anderen.
    </p>

    <p>
        Met vriendelijke groet,<br>
        Privatescan
    </p>
@endsection
