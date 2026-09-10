<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Master switch. When false, AuthController::login() issues a Sanctum
    | token directly and none of the routes/checks below are reachable.
    |
    */
    'enabled' => env('TWO_FACTOR_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Issuer
    |--------------------------------------------------------------------------
    |
    | Shown as the account's issuer name in an authenticator app (Google
    | Authenticator, Authy, ...) alongside the user's email.
    |
    */
    'issuer' => env('TWO_FACTOR_ISSUER', env('APP_NAME', 'Laravel')),

    /*
    |--------------------------------------------------------------------------
    | Available methods
    |--------------------------------------------------------------------------
    |
    | Which methods a project actually offers ('totp', 'email'). Drives two
    | things: what TwoFactorLoginChallenger lists in a pending 'enroll'
    | response (so the frontend knows what to propose during forced
    | enrollment, since a brand-new user has nothing confirmed yet to infer
    | it from), and which methods setup()/enableEmail() accept - a method
    | left out here is rejected even if a client requests it directly.
    |
    */
    'available_methods' => array_filter(explode(
        ',', env('TWO_FACTOR_AVAILABLE_METHODS', 'totp,email')
    )),

    /*
    |--------------------------------------------------------------------------
    | Force enrollment
    |--------------------------------------------------------------------------
    |
    | When true, a user with no confirmed two-factor method is blocked at
    | login behind an enrollment step (a pending-enrollment token, only
    | usable against the /auth/2fa/* setup endpoints) rather than being
    | let through with a normal Sanctum token. Users holding the
    | exemption permission below are never forced.
    |
    */
    'force_enrollment' => env('TWO_FACTOR_FORCE_ENROLLMENT', false),

    /*
    |--------------------------------------------------------------------------
    | Exemption permission
    |--------------------------------------------------------------------------
    |
    | A user whose roles carry this permission skips two-factor entirely
    | at login - both the verification step for an already-enrolled user,
    | and the forced-enrollment requirement above.
    |
    */
    'bypass_permission' => env('TWO_FACTOR_BYPASS_PERMISSION', 'RIVET_BYPASS_2FA'),

    /*
    |--------------------------------------------------------------------------
    | Pending token TTL
    |--------------------------------------------------------------------------
    |
    | Minutes a pending token (issued after password validation, before
    | two-factor verification/enrollment completes) stays valid. Stored in
    | the default cache store, never in the database.
    |
    */
    'pending_token_ttl' => env('TWO_FACTOR_PENDING_TOKEN_TTL', 10),

    /*
    |--------------------------------------------------------------------------
    | Email OTP code TTL
    |--------------------------------------------------------------------------
    |
    | Minutes an emailed one-time code stays valid.
    |
    */
    'email_code_ttl' => env('TWO_FACTOR_EMAIL_CODE_TTL', 10),
];
