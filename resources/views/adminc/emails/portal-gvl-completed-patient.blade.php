@php
    /** @var \Webkul\Contact\Models\Person $person */
    $name = trim($person->name ?? '');
@endphp

@extends('adminc.layouts.mail')

@section('title')
    GVL Formulier Bevestiging
@endsection

@section('content')

    <!-- Content -->
    <div class="content">
        <h2>Beste {{ $name }},</h2>

        <p>Bedankt voor het invullen van het diagnose formulier. Binnen 1 tot 2 werkdagen nemen wij contact met je op
            voor een voorlopig adviesgesprek.
            <br/>U kunt uw ingevulde formulier terug vinden in ons <link href="{{ $patientPortalUrl }}">patient portaal</link>.
        </p>

        <p>Met vriendelijke groet,<br>
            PrivateScan</p>
    </div>
@endsection
