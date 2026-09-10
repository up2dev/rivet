@extends('rivet::emails.layout')

@section('title', trans('rivet::mail.subject_user_validates'))

@section('body')
    <p style="margin:0;">
        {{ trans('rivet::mail.email_validated_intro') }}
    </p>

    @if (is_null($user->password))
        <p style="margin:16px 0 0;">
            {{ trans('rivet::mail.email_validated_check_inbox') }}
        </p>
    @endif
@endsection
