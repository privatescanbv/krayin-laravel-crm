@extends('layouts.patient-portal-auth')

@section('title', 'Nieuw wachtwoord instellen — Patiëntportaal')

@section('content')
    @php
        $portalTheme = asset('patient-portal-login');
    @endphp

    <div class="l_logo">
        <a href="javascript:void(0)"><img src="{{ $portalTheme }}/images/mainlogo.svg" alt="Privatescan" /></a>
    </div>

    <div class="form_h">
        <h1>Nieuw wachtwoord</h1>
        <span>Stel een nieuw wachtwoord in voor uw patiëntportaal.@if ($email)<br>{{ $email }}@endif</span>
    </div>

    <div class="password-requirements" aria-label="Wachtwoordeisen">
        <p class="password-requirements__title">Je wachtwoord moet bevatten:</p>
        <ul>
            <li>Minimaal 8 tekens</li>
            <li>Minimaal 1 hoofdletter (A-Z)</li>
            <li>Minimaal 1 cijfer (0-9)</li>
            <li>Minimaal 1 speciaal teken (! @ # $ % & *)</li>
        </ul>
    </div>

    @if (session('success'))
        <div class="alert-success" aria-live="polite">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert-error" aria-live="polite">{{ $errors->first() }}</div>
    @endif

    <form id="crm-patient-reset-password"
          class="log_form"
          method="post"
          action="{{ request()->fullUrl() }}">
        <div class="log_form">
            @csrf

            <label class="pword">
                <span>Nieuw wachtwoord</span>
                <input type="password"
                       id="password"
                       name="password"
                       placeholder="Vul je wachtwoord in"
                       class="input_box"
                       autocomplete="new-password"
                       required
                       autofocus />

                <div class="icon">
                    <img src="{{ $portalTheme }}/images/close_eye.svg"
                         class="eye-icon"
                         data-target="password"
                         alt="Toon wachtwoord"
                         style="cursor:pointer;" />
                </div>
            </label>

            <label class="pword">
                <span>Bevestig wachtwoord</span>
                <input type="password"
                       id="password_confirmation"
                       name="password_confirmation"
                       placeholder="Bevestig je wachtwoord"
                       class="input_box"
                       autocomplete="new-password"
                       required />

                <div class="icon">
                    <img src="{{ $portalTheme }}/images/close_eye.svg"
                         class="eye-icon"
                         data-target="password_confirmation"
                         alt="Toon wachtwoord"
                         style="cursor:pointer;" />
                </div>
            </label>

            <div class="check" style="justify-content: flex-end;">
                <a href="{{ route('patient.forgot-password.create') }}">Annuleren</a>
            </div>

            <div class="form_end">
                <button type="submit" class="input_box login_btn">
                    Wachtwoord opslaan
                </button>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    @php
        $portalTheme = asset('patient-portal-login');
    @endphp
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const base = @json(rtrim($portalTheme, '/'));
            document.querySelectorAll('#crm-patient-reset-password .eye-icon').forEach(function (icon) {
                icon.addEventListener('click', function () {
                    const input = document.getElementById(this.dataset.target);
                    if (!input) return;

                    if (input.type === 'password') {
                        input.type = 'text';
                        this.src = base + '/images/open_eye.svg';
                    } else {
                        input.type = 'password';
                        this.src = base + '/images/close_eye.svg';
                    }
                });
            });
        });
    </script>
@endpush
