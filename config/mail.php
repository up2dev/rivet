<?php

return [

    /*
    |--------------------------------------------------------------------------
    | If the email address validation is sended
    |--------------------------------------------------------------------------
    */

    'is_mail_checked' => env('MAIL_EMAIL_CHECKED', true),

    /*
    |--------------------------------------------------------------------------
    | If the password first creation is sended
    |--------------------------------------------------------------------------
    */

    'is_forcing_password_creation' => env('MAIL_FORCE_PASSWORD_CREATION', true),

    /*
    |--------------------------------------------------------------------------
    | If the password change sends an email
    |--------------------------------------------------------------------------
    */

    'is_confirming_password' => env('MAIL_CONFIRM_PASSWORD', true),

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | Mail\BaseMail reads config('mail.from.address')/('mail.from.name').
    | This package's own config/mail.php never declared these keys,
    | silently relying on the host app's own default Laravel
    | config/mail.php (which does declare them) never having been
    | altered. mergeConfigFrom() means these are only used as a
    | fallback - an app defining its own 'mail.from' still wins.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name'    => env('MAIL_FROM_NAME', 'Example')
    ],

    /*
    |--------------------------------------------------------------------------
    | Email accent color
    |--------------------------------------------------------------------------
    |
    | Used by the default email layout (resources/views/emails/layout.blade.php)
    | for the divider under the header and button backgrounds - the one
    | visual customization point for a white-label deployment.
    |
    */

    'brand_color' => env('MAIL_BRAND_COLOR', '#4f46e5'),

];
