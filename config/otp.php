<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OTP Expiry
    |--------------------------------------------------------------------------
    |
    | Number of minutes an OTP code remains valid after being issued.
    |
    */

    'expiry_minutes' => env('OTP_EXPIRY_MINUTES', 10),

    /*
    |--------------------------------------------------------------------------
    | Max Verification Attempts
    |--------------------------------------------------------------------------
    |
    | Number of times a given OTP may be checked before it's rejected outright,
    | even if the code is still within its expiry window.
    |
    */

    'max_attempts' => env('OTP_MAX_ATTEMPTS', 5),

    /*
    |--------------------------------------------------------------------------
    | Resend Throttle
    |--------------------------------------------------------------------------
    |
    | Minimum number of seconds a phone/purpose pair must wait between
    | consecutive OTP requests.
    |
    */

    'resend_throttle_seconds' => env('OTP_RESEND_THROTTLE_SECONDS', 60),

];
