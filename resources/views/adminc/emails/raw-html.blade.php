{{--
    Passthrough view for MailMessage notifications that already received fully
    rendered HTML from EmailTemplateRenderingService (layout + inlined CSS).

    Notifications must use ->view(...) (no ->html(...) on MailMessage), so we
    echo the pre-rendered HTML verbatim and let the renderer's wrapper layout
    carry the email styling.
--}}
{!! $html !!}
