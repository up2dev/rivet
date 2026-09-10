@extends('rivet::emails.layout')

@section('title', trans('rivet::mail.subject_two_factor_code'))

@section('body')
    <p style="margin:0 0 20px;">
        {{ trans('rivet::mail.two_factor_intro') }}
    </p>

    <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 20px;">
        <tr>
            <td style="background:#f4f4f7;border-radius:6px;padding:16px 32px;">
                <span style="font-family:Helvetica Neue,Helvetica,Arial,sans-serif;font-size:32px;font-weight:700;letter-spacing:8px;color:{{ config('mail.brand_color', '#4f46e5') }};">
                    {{ $code }}
                </span>
            </td>
        </tr>
    </table>

    <p style="margin:0;font-size:13px;color:#8a8f98;">
        {{ trans('rivet::mail.two_factor_expires') }}
    </p>
@endsection
