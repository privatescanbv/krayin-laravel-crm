<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>GVL Formulier Bevestiging</title>
    <style>
        :root {
            --privatescan-blue: #11518f;
        }
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: var(--privatescan-blue);
            margin: 0;
            padding: 0;
        }
        .header {
            background-color: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem 0;
        }
        .header-content {
            max-width: 64rem;
            margin: 0 auto;
            padding: 0 1rem;
        }
        .logo {
            height: 4rem;
        }
        .blue-bar {
            background-color: var(--privatescan-blue);
            padding: 1rem 0;
        }
        .blue-bar-content {
            max-width: 64rem;
            margin: 0 auto;
            padding: 0 1rem;
        }
        .blue-bar h1 {
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0;
        }
        .content {
            max-width: 64rem;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        .footer {
            background-color: #1e40af;
            color: white;
            padding: 1rem 0;
            margin-top: 2rem;
        }
        .footer-content {
            max-width: 64rem;
            margin: 0 auto;
            padding: 0 1rem;
        }
        .footer a {
            color: white;
            text-decoration: none;
        }
        .footer a:hover {
            color: #e5e7eb;
        }
    </style>
</head>
<body>
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

<!-- Blue bar -->
<div class="blue-bar">
    <div class="blue-bar-content">
        <h1>  @yield('title')</h1>
    </div>
</div>

<!-- Content -->
<div class="content">
  @yield('content')
</div>

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
</body>
</html>
