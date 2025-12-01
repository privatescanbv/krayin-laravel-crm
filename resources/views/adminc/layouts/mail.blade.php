<div class="email-container">
    @php
        // showHeader: yield 'showHeader' accepts 'true' or 'false'; default 'true'
        $showHeader = trim($__env->yieldContent('showHeader', 'true'));
        $showFooter = trim($__env->yieldContent('showFooter', 'true'));
    @endphp

    @if ($showHeader !== 'false')
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="https://privatescan.nl" target="_blank">
                @php
                    // Use absolute URL to static logo file (doesn't change with builds)
                    $logoUrl = rtrim(config('app.url', 'https://privatescan.nl'), '/') . '/images/logo.svg';
                @endphp
                <img src="{{ $logoUrl }}" alt="PrivateScan Logo" class="logo">
            </a>
        </div>
    </header>
    @endif
    <!-- Blue bar -->
    @if (trim($__env->yieldContent('title', $title ?? '')) !== '')
        <div class="blue-bar">
            <div class="blue-bar-content">
                <h1>@yield('title', $title ?? '')</h1>
            </div>
        </div>
    @endif

    <!-- Content -->
    <div class="content ">
        @yield('content')
    </div>

    @if ($showHeader !== 'false')
    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="text-sm">
                <span>© 2025 Privatescan</span>
                | <a href="https://www.privatescan.nl/algemene-voorwaarden/" target="_blank">Algemene voorwaarden</a>
                - <a href="https://www.privatescan.nl/privacy-statement/" target="_blank">Privacy policy</a>
                - <a href="https://www.privatescan.nl/disclaimer/" target="_blank">Disclaimer</a>
            </div>
        </div>
    </footer>
    @endif
</div>
