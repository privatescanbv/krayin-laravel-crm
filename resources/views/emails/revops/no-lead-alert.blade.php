<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RevOps – Geen nieuwe leads</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 6px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,.1); }
        .header { background: #c0392b; padding: 20px 30px; }
        .header h1 { color: #ffffff; margin: 0; font-size: 18px; }
        .body { padding: 28px 30px; color: #333333; line-height: 1.6; }
        .body h2 { margin-top: 0; font-size: 16px; color: #c0392b; }
        .meta { background: #f9f9f9; border-left: 4px solid #c0392b; padding: 12px 16px; margin: 20px 0; font-size: 14px; }
        .meta strong { display: inline-block; width: 160px; }
        .footer { padding: 16px 30px; background: #f0f0f0; font-size: 12px; color: #888888; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>RevOps Alert – Geen nieuwe leads</h1>
        </div>
        <div class="body">
            <h2>Afdeling: {{ $department->value }}</h2>
            <p>
                Er zijn de afgelopen <strong>24 uur</strong> geen nieuwe leads aangemaakt
                voor afdeling <strong>{{ $department->value }}</strong>.
            </p>
            <div class="meta">
                <div>
                    <strong>Afdeling:</strong>
                    {{ $department->value }}
                </div>
                <div>
                    <strong>Laatste lead aangemaakt:</strong>
                    @if ($lastLead)
                        {{ $lastLead->created_at->format('d-m-Y H:i') }}
                        ({{ $lastLead->created_at->diffForHumans() }})
                    @else
                        Geen leads gevonden
                    @endif
                </div>
                <div>
                    <strong>Tijdstip check:</strong>
                    {{ now()->format('d-m-Y H:i') }}
                </div>
            </div>
            <p>Controleer of de lead-intake voor deze afdeling nog correct werkt.</p>
        </div>
        <div class="footer">
            Dit is een automatisch bericht van het Privatescan CRM RevOps-systeem.
        </div>
    </div>
</body>
</html>
