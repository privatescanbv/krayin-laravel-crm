<p>Sehr geehrte Damen und Herren,</p>

<p>Im Anhang finden Sie die AFB-Dokumente für die geplanten Untersuchungen.</p>

<p>
    <strong>Versandtyp:</strong> {{ $type }}<br>
    <strong>Versanddatum:</strong> {{ $sentAt }}<br>
    <strong>Aufträge:</strong>
    @if (!empty($orderNumbers))
        {{ implode(', ', $orderNumbers) }}
    @else
        unbekannt
    @endif
</p>

<p>Mit freundlichen Grüßen,<br>Privatescan</p>
