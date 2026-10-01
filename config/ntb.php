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

    'mobile_token_ttl_days' => (int) env('NTB_MOBILE_TOKEN_TTL_DAYS', 90),

    'ussd_session_ttl_minutes' => (int) env('NTB_USSD_SESSION_TTL_MINUTES', 5),

    'countries' => [
        ['code' => 'DJ', 'name_en' => 'Djibouti', 'name_fr' => 'Djibouti'],
        ['code' => 'ET', 'name_en' => 'Ethiopia', 'name_fr' => 'Éthiopie'],
        ['code' => 'ER', 'name_en' => 'Eritrea', 'name_fr' => 'Érythrée'],
        ['code' => 'SO', 'name_en' => 'Somalia', 'name_fr' => 'Somalie'],
        ['code' => 'SD', 'name_en' => 'Sudan', 'name_fr' => 'Soudan'],
        ['code' => 'SS', 'name_en' => 'South Sudan', 'name_fr' => 'Soudan du Sud'],
        ['code' => 'KE', 'name_en' => 'Kenya', 'name_fr' => 'Kenya'],
        ['code' => 'UG', 'name_en' => 'Uganda', 'name_fr' => 'Ouganda'],
        ['code' => 'TZ', 'name_en' => 'Tanzania', 'name_fr' => 'Tanzanie'],
        ['code' => 'RW', 'name_en' => 'Rwanda', 'name_fr' => 'Rwanda'],
        ['code' => 'BI', 'name_en' => 'Burundi', 'name_fr' => 'Burundi'],
        ['code' => 'EG', 'name_en' => 'Egypt', 'name_fr' => 'Égypte'],
        ['code' => 'YE', 'name_en' => 'Yemen', 'name_fr' => 'Yémen'],
        ['code' => 'SA', 'name_en' => 'Saudi Arabia', 'name_fr' => 'Arabie saoudite'],
        ['code' => 'AE', 'name_en' => 'United Arab Emirates', 'name_fr' => 'Émirats arabes unis'],
        ['code' => 'CN', 'name_en' => 'China', 'name_fr' => 'Chine'],
        ['code' => 'IN', 'name_en' => 'India', 'name_fr' => 'Inde'],
        ['code' => 'FR', 'name_en' => 'France', 'name_fr' => 'France'],
        ['code' => 'GB', 'name_en' => 'United Kingdom', 'name_fr' => 'Royaume-Uni'],
        ['code' => 'US', 'name_en' => 'United States', 'name_fr' => 'États-Unis'],
        ['code' => 'ZA', 'name_en' => 'South Africa', 'name_fr' => 'Afrique du Sud'],
        ['code' => 'NG', 'name_en' => 'Nigeria', 'name_fr' => 'Nigeria'],
        ['code' => 'GH', 'name_en' => 'Ghana', 'name_fr' => 'Ghana'],
        ['code' => 'SN', 'name_en' => 'Senegal', 'name_fr' => 'Sénégal'],
        ['code' => 'CI', 'name_en' => "Côte d'Ivoire", 'name_fr' => "Côte d'Ivoire"],
        ['code' => 'BJ', 'name_en' => 'Benin', 'name_fr' => 'Bénin'],
        ['code' => 'OTHER', 'name_en' => 'Other', 'name_fr' => 'Autre'],
    ],

    'currencies' => ['USD', 'EUR', 'GBP', 'DJF', 'ETB', 'KES', 'UGX', 'TZS', 'RWF', 'XOF', 'XAF', 'ZAR', 'CNY', 'INR', 'SAR', 'AED'],

    'fcm' => [
        'server_key' => env('NTB_FCM_SERVER_KEY'),
    ],

];
