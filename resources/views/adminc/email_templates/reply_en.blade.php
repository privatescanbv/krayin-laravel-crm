@extends('adminc.email_templates.base.layout')

@section('content')
    <p>Dear Mr./Ms. {{ $lastname }},</p>

    <p>
        [This is another template]
    </p>

    <p><br></p>
@endsection
