@php
    // Read the CSS file and inline it for email compatibility
    $cssPath = resource_path('css/email-templates.css');
    $css = file_exists($cssPath) ? file_get_contents($cssPath) : '';
@endphp

<style>
{!! $css !!}
</style>

<div class="email-container">
    @yield('content')
</div>

