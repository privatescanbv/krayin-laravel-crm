@php
    $portalTheme = asset('patient-portal-login');
@endphp
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Privatescan')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Same stylesheet as Keycloak theme: .docker/keycloak/themes/privatescan/login/resources/css/style.css --}}
    <link rel="stylesheet" href="{{ $portalTheme }}/css/style.css">

    @stack('head')
</head>

<body>

    {{--
        Structure matches Keycloak login.ftl (.log_main > .log > .right white card + .log_end footer logos).
        Static assets mirrored under public/patient-portal-login/ (css, fonts, images).
    --}}
    <div class="log_main">
        <div class="log">
            <div class="right">
                @yield('content')
            </div>

            <div class="log_end">
                <a class="button" href="javascript:void(0)" tabindex="-1" aria-hidden="true">
                    <img src="{{ $portalTheme }}/images/logo.svg" alt="" />
                </a>
                <a class="button" href="javascript:void(0)" tabindex="-1" aria-hidden="true">
                    <img src="{{ $portalTheme }}/images/hernia_logo.svg" alt="" />
                </a>
            </div>
        </div>
    </div>

    @stack('scripts')
</body>

</html>
