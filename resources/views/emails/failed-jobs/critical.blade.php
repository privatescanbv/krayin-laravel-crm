<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KRITIEK – Mislukte jobs in de queue</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 6px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,.1); }
        .header { background: #c0392b; padding: 20px 30px; }
        .header h1 { color: #ffffff; margin: 0; font-size: 18px; }
        .banner { background: #fde8e8; border-top: 3px solid #c0392b; padding: 12px 30px; font-size: 13px; font-weight: bold; color: #c0392b; letter-spacing: 0.5px; text-align: center; }
        .body { padding: 28px 30px; color: #333333; line-height: 1.6; }
        .body h2 { margin-top: 0; font-size: 16px; color: #c0392b; }
        .count { font-size: 48px; font-weight: bold; color: #c0392b; text-align: center; padding: 20px 0 10px; }
        .count-label { text-align: center; font-size: 13px; color: #999999; margin-bottom: 20px; }
        .meta { background: #f9f9f9; border-left: 4px solid #c0392b; padding: 12px 16px; margin: 20px 0; font-size: 14px; }
        .meta div { margin-bottom: 6px; }
        .meta strong { display: inline-block; min-width: 160px; }
        .tip { background: #fff5f5; border: 1px solid #f0b0b0; border-radius: 4px; padding: 12px 16px; font-size: 13px; color: #7a0000; margin-top: 16px; }
        .tip code { background: #f0c0c0; padding: 1px 4px; border-radius: 3px; }
        .footer { padding: 16px 30px; background: #f0f0f0; font-size: 12px; color: #888888; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>&#128680; KRITIEK – Groot aantal mislukte queue-jobs</h1>
        </div>
        <div class="banner">KRITIEKE DREMPELWAARDE OVERSCHREDEN – DIRECTE ACTIE VEREIST</div>
        <div class="body">
            <h2>Kritieke situatie: het aantal mislukte jobs vereist directe aandacht</h2>
            <p>
                Er zijn <strong>{{ $failedJobCount }} mislukte jobs</strong> gevonden in de queue.
                Dit overschrijdt de <strong>kritieke drempelwaarde</strong> en vereist directe actie.
                Controleer de applicatiestatus en onderzoek de oorzaak van de fouten.
            </p>

            <div class="count">{{ $failedJobCount }}</div>
            <div class="count-label">mislukte jobs</div>

            <div class="meta">
                <div>
                    <strong>Aantal mislukte jobs:</strong>
                    {{ $failedJobCount }}
                </div>
                <div>
                    <strong>Omgeving:</strong>
                    {{ $environment }}
                </div>
                <div>
                    <strong>Tijdstip:</strong>
                    {{ $timestamp }}
                </div>
            </div>

            <div class="tip">
                <strong>Aanbevolen acties:</strong><br>
                1. Controleer de applicatielogs voor foutmeldingen<br>
                2. Gebruik <code>php artisan queue:failed</code> voor een overzicht<br>
                3. Gebruik <code>php artisan queue:retry &lt;id&gt;</code> voor individuele jobs<br>
                4. Gebruik <code>php artisan queue:retry all</code> om alle jobs opnieuw te proberen<br>
                5. Controleer de database- en servicestatus
            </div>
        </div>
        <div class="footer">
            Dit is een automatisch bericht van het Privatescan CRM queue-monitoringsysteem.
            Mails worden maximaal eens per 30 minuten verstuurd per drempelniveau.
        </div>
    </div>
</body>
</html>
