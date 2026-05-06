@extends('layouts.patient-portal-auth')

@section('title', 'Wachtwoord vergeten — Patiëntportaal')

@section('content')
    @php
        $portalTheme = asset('patient-portal-login');
    @endphp

    <div class="l_logo">
        <a href="javascript:void(0)"><img src="{{ $portalTheme }}/images/mainlogo.svg" alt="Privatescan" /></a>
    </div>

    <div class="form_h">
        <h1>Wachtwoord vergeten</h1>
        <span>Wachtwoord vergeten? Vraag hier een nieuwe resetlink aan.</span>
    </div>

    @if (session('success'))
        <div class="alert-success" aria-live="polite">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert-error" aria-live="polite">{{ $errors->first() }}</div>
    @endif

    <form class="log_form"
          method="post"
          action="{{ route('patient.forgot-password.store') }}">
        <div class="log_form">
            @csrf

            <label class="mail">
                <span>E-mail</span>
                <input type="email"
                       id="email"
                       name="email"
                       value="{{ old('email') }}"
                       placeholder="Vul je e-mailadres in"
                       class="input_box"
                       autocomplete="email"
                       required
                       autofocus />
            </label>

            {{-- Footer row styled like Login’s .check secondary link area (no “Onthoud mij” here). --}}
            <div class="check" style="justify-content: flex-end;">
                <a href="{{ config('services.portal.patient.web_url') }}">Terug naar inloggen</a>
            </div>

            <div class="form_end">
                <button type="submit" class="input_box login_btn">
                    Resetlink versturen
                </button>
            </div>
        </div>
    </form>
@endsection
