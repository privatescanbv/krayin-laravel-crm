<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Warning – Mislukte jobs</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 6px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,.1); }
        .header { background: #e67e22; padding: 20px 30px; }
        .header h1 { color: #ffffff; margin: 0; font-size: 18px; }
        .body { padding: 28px 30px; color: #333333; line-height: 1.6; }
        .body h2 { margin-top: 0; font-size: 16px; color: #e67e22; }
        .count { font-size: 48px; font-weight: bold; color: #e67e22; text-align: center; padding: 20px 0 10px; }
        .count-label { text-align: center; font-size: 13px; color: #999999; margin-bottom: 20px; }
        .meta { background: #f9f9f9; border-left: 4px solid #e67e22; padding: 12px 16px; margin: 20px 0; font-size: 14px; }
        .meta div { margin-bottom: 6px; }
        .meta strong { display: inline-block; min-width: 160px; }
        .tip { background: #fff8f0; border: 1px solid #f0c080; border-radius: 4px; padding: 12px 16px; font-size: 13px; color: #7a5000; margin-top: 16px; }
        .tip code { background: #f0e0c0; padding: 1px 4px; border-radius: 3px; }
        .footer { padding: 16px 30px; background: #f0f0f0; font-size: 12px; color: #888888; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>&#9888; Queue Warning – Mislukte jobs gedetecteerd</h1>
        </div>
        <div class="body">
            <h2>Waarschuwing: het aantal mislukte queue-jobs overschrijdt de drempelwaarde</h2>
            <p>
                Er zijn <strong>{{ $failedJobCount }} mislukte jobs</strong> gevonden in de queue.
                Dit overschrijdt de ingestelde waarschuwingsdrempel.
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
                <strong>Wat te doen?</strong><br>
                Controleer de <code>failed_jobs</code> tabel of gebruik:<br>
                &bull; <code>php artisan queue:retry all</code> – jobs opnieuw proberen<br>
                &bull; <code>php artisan queue:failed</code> – overzicht van mislukte jobs<br>
                &bull; <code>php artisan queue:flush</code> – alle mislukte jobs verwijderen
            </div>
        </div>
        <div class="footer">
            Dit is een automatisch bericht van het Privatescan CRM queue-monitoringsysteem.
            Mails worden maximaal eens per 30 minuten verstuurd per drempelniveau.
        </div>
    </div>
</body>
</html>
