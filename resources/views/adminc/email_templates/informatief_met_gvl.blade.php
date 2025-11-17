@extends('adminc.email_templates.base.layout')

@section('content')
    <p>Geachte heer/mevrouw {{ $lastname }},</p>

    <p>
        Graag willen wij u informeren over uw aanvraag bij Privatescan. Om uw aanvraag verder te kunnen verwerken, hebben wij enkele gegevens van u nodig.
    </p>

    <p>
        U kunt deze gegevens invullen via het onderstaande GVL-formulier. Dit formulier helpt ons om een beter beeld te krijgen van uw situatie en om u de best mogelijke zorg te kunnen bieden.
    </p>

    @if (isset($gvl_form_link) && !empty($gvl_form_link))
        <p>
            <strong>GVL-formulier:</strong><br>
            <a href="{{ $gvl_form_link }}" target="_blank" style="color: #2563eb; text-decoration: underline;">
                {{ $gvl_form_link }}
            </a>
        </p>
    @else
        <span>Error: Geen GVL link aanwezig</span>
    @endif

    <p>
        Mocht u vragen hebben of hulp nodig hebben bij het invullen van het formulier, neem dan gerust contact met ons op.
    </p>

    <p><br></p>
@endsection

