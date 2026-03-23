<p>Beste kliniek,</p>

<p>In de bijlage vindt u de AFB-documenten voor de geplande onderzoeken.</p>

<p>
    <strong>Type verzending:</strong> {{ $type }}<br>
    <strong>Verstuurd op:</strong> {{ $sentAt }}<br>
    <strong>Orders:</strong>
    @if (!empty($orderNumbers))
        {{ implode(', ', $orderNumbers) }}
    @else
        onbekend
    @endif
</p>

<p>Met vriendelijke groet,<br>Privatescan</p>
