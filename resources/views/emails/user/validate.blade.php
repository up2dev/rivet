@extends('rivet::emails.layout')

@section('title', trans('rivet::mail.subject_user_validate'))

@section('body')
    <p style="margin:0 0 16px;">
        {{ trans('rivet::mail.validate_intro', [ 'login' => $user->login ]) }}
    </p>

    <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 20px;">
        <tr>
            <td style="background:{{ config('mail.brand_color', '#4f46e5') }};border-radius:6px;">
                <a href="{{ config('app.frontend_url') }}/verify-email/{{ $token }}"
                   target="_blank"
                   style="display:inline-block;padding:12px 24px;color:#ffffff;font-family:Helvetica Neue,Helvetica,Arial,sans-serif;font-size:15px;font-weight:600;text-decoration:none;border-radius:6px;">
                    {{ trans('rivet::mail.validate_button') }}
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0;font-size:13px;color:#8a8f98;">
        {{ trans('rivet::mail.link_fallback') }}<br>
        <a href="{{ config('app.frontend_url') }}/verify-email/{{ $token }}" class="link">{{ config('app.frontend_url') }}/verify-email/{{ $token }}</a>
    </p>
@endsection
