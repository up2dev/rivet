@extends('rivet::emails.layout')

@section('title', trans('rivet::mail.subject_user_logins'))

@section('body')
    <p style="margin:0 0 12px;">
        {{ trans('rivet::mail.logins_intro') }}
    </p>

    <ul style="margin:0;padding:0 0 0 20px;">
        @foreach ($logins as $login)
            <li style="margin:0 0 4px;">{{ $login }}</li>
        @endforeach
    </ul>
@endsection
