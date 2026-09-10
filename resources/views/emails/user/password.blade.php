@extends('rivet::emails.layout')

@section('title', trans('rivet::mail.subject_user_password'))

@section('body')
    <p style="margin:0;">
        {{ trans('rivet::mail.password_updated_intro') }}
    </p>
@endsection
