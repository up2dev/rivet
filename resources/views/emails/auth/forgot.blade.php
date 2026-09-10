@extends('rivet::emails.layout')

@section('title', trans('rivet::mail.subject_auth_forgot'))

@section('body')
    <p style="margin:0 0 16px;">
        {{ trans('rivet::mail.forgot_intro', [ 'login' => $user->login ]) }}
    </p>

    <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 20px;">
        <tr>
            <td style="background:{{ config('mail.brand_color', '#4f46e5') }};border-radius:6px;">
                <a href="{{ config('app.frontend_url') }}/reset-password/{{ $token }}"
                   target="_blank"
                   style="display:inline-block;padding:12px 24px;color:#ffffff;font-family:Helvetica Neue,Helvetica,Arial,sans-serif;font-size:15px;font-weight:600;text-decoration:none;border-radius:6px;">
                    {{ trans('rivet::mail.forgot_button') }}
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 8px;font-size:13px;color:#8a8f98;">
        {{ trans('rivet::mail.link_fallback') }}<br>
        <a href="{{ config('app.frontend_url') }}/reset-password/{{ $token }}" class="link">{{ config('app.frontend_url') }}/reset-password/{{ $token }}</a>
    </p>

    <p style="margin:0;font-size:13px;color:#8a8f98;">
        {{ trans('rivet::mail.link_expires', [ 'date' => $token_expires_at->format('d/m/Y H:i') ]) }}
    </p>
@endsection
