@extends('adminc.email_templates.base.layout')

@section('content')
    <p>Sehr geehrte(r) Herr/Frau {{ $lastname }},</p>

    <p>
        [Dies ist eine andere Vorlage]
    </p>

    <p><br></p>
@endsection
