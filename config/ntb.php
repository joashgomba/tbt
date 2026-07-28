<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Multi-factor authentication
    |--------------------------------------------------------------------------
    |
    | Disable during local/demo use when OTP email/SMS delivery is unavailable.
    | Set NTB_MFA_ENABLED=true in production once real contact channels exist.
    |
    */

    'mfa_enabled' => env('NTB_MFA_ENABLED', false),

];
