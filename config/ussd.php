<?php

return [

    'short_code' => env('NTB_USSD_SHORT_CODE', '*826#'),

    'default_locale' => env('NTB_USSD_DEFAULT_LOCALE', 'fr'),

    'max_input_chars' => (int) env('NTB_USSD_MAX_INPUT_CHARS', 160),

    'max_menu_response_chars' => (int) env('NTB_USSD_MAX_RESPONSE_CHARS', 182),

    'anonymous_submission_enabled' => (bool) env('NTB_USSD_ANONYMOUS_ENABLED', false),

    'track_max_failures' => (int) env('NTB_USSD_TRACK_MAX_FAILURES', 3),

    'otp_ttl_minutes' => (int) env('NTB_USSD_OTP_TTL_MINUTES', 10),

    'my_references_limit' => 5,

    /*
    | Location types shown on USSD (step 1.1) — mapped to locations.location_type in DB.
    */
    'location_types' => [
        ['key' => 'port', 'types' => ['port', 'terminal'], 'sort' => 1],
        ['key' => 'border_post', 'types' => ['border_post'], 'sort' => 2],
        ['key' => 'road', 'types' => ['inland_depot'], 'sort' => 3],
        ['key' => 'office', 'types' => ['inland_depot', 'border_post'], 'sort' => 4],
        ['key' => 'other', 'types' => [], 'sort' => 5],
    ],

    'default_corridor_code' => 'ETH-DJ',

    'default_countries' => [
        'affected' => 'Djibouti',
        'origin' => 'Ethiopia',
        'destination' => 'Djibouti',
    ],

    /*
    | Fallback issue/subcategory labels when no child NTB categories exist.
    */
    'issues_by_category_code' => [
        'SPS' => ['Inspection delay', 'Certificate rejected', 'Quarantine hold', 'Other / Not listed'],
        'TBT' => ['Lab testing', 'Labelling rules', 'Standards compliance', 'Other / Not listed'],
        'CUST' => ['Customs clearance delay', 'Documentation', 'Physical inspection', 'Other / Not listed'],
        'LIC' => ['Licence delay', 'Permit denied', 'Registration issue', 'Other / Not listed'],
        'TAX' => ['Unexpected fee', 'Tax assessment', 'Administrative charge', 'Other / Not listed'],
        'TRN' => ['Port delay', 'Transport restriction', 'Logistics bottleneck', 'Other / Not listed'],
    ],

    'support' => [
        'en' => [
            'contact' => 'MoTT NTB Helpdesk',
            'phone' => '+253 21 35 00 00',
            'hours' => 'Sun–Thu 08:00–16:00',
            'how_to_submit' => 'Register on the web portal or mobile app. Via USSD: category, location, issue, ID, confirm. Add documents later on the portal.',
        ],
        'fr' => [
            'contact' => 'Assistance BNT MoTT',
            'phone' => '+253 21 35 00 00',
            'hours' => 'Dim–Jeu 08h00–16h00',
            'how_to_submit' => 'Inscrivez-vous sur le portail Web ou l’app mobile. Via USSD : catégorie, lieu, problème, identifiant, confirmation. Ajoutez les pièces sur le portail.',
        ],
    ],

    'status_messages' => [
        'submitted' => [
            'en' => 'Submitted / awaiting validation',
            'fr' => 'Soumise / en attente de validation',
        ],
        'under_validation' => [
            'en' => 'Under review / processing',
            'fr' => 'En cours d’analyse / traitement',
        ],
        'additional_info_requested' => [
            'en' => 'Additional information required. Use the secure portal or support with your reference.',
            'fr' => 'Informations complémentaires requises. Utilisez le portail sécurisé ou l’assistance avec votre référence.',
        ],
        'assigned' => [
            'en' => 'Under review / processing',
            'fr' => 'En cours d’analyse / traitement',
        ],
        'in_processing' => [
            'en' => 'Under review / processing',
            'fr' => 'En cours d’analyse / traitement',
        ],
        'awaiting_external_input' => [
            'en' => 'Under review / processing',
            'fr' => 'En cours d’analyse / traitement',
        ],
        'escalated' => [
            'en' => 'Escalated',
            'fr' => 'Escaladée',
        ],
        'resolved' => [
            'en' => 'Resolution proposed / resolved. See secure portal for details.',
            'fr' => 'Résolution proposée / résolue. Voir le portail sécurisé pour les détails.',
        ],
        'closed' => [
            'en' => 'Closed. Quote your reference if you need authorized assistance.',
            'fr' => 'Clôturée. Indiquez votre référence si vous avez besoin d’assistance.',
        ],
        'archived' => [
            'en' => 'Closed. Quote your reference if you need authorized assistance.',
            'fr' => 'Clôturée. Indiquez votre référence si vous avez besoin d’assistance.',
        ],
        'rejected' => [
            'en' => 'Closed',
            'fr' => 'Clôturée',
        ],
        'reopened' => [
            'en' => 'Under review / processing',
            'fr' => 'En cours d’analyse / traitement',
        ],
    ],

    'messages' => [
        'welcome' => [
            'en' => "National NTB Reporting Platform\n1. Submit complaint\n2. Track complaint\n3. Practical information\n0. Exit",
            'fr' => "Plateforme nationale BNT\n1. Deposer plainte\n2. Suivre plainte\n3. Infos pratiques\n0. Quitter",
        ],
        'submission_success' => [
            'en' => 'Your report has been registered. Reference: {code}. Updates per applicable SLA. Keep reference confidential. Add evidence on the web portal.',
            'fr' => 'Signalement enregistre. Reference : {code}. Mises a jour selon le SLA applicable. Gardez la reference confidentielle. Ajoutez les preuves sur le portail.',
        ],
        'track_verify_fail' => [
            'en' => 'Reference not found or verification failed. Check and try again.',
            'fr' => 'Reference introuvable ou verification echouee. Verifiez et reessayez.',
        ],
        'track_locked' => [
            'en' => 'Too many attempts. Contact support or use the web portal.',
            'fr' => 'Trop de tentatives. Contactez l assistance ou le portail Web.',
        ],
        'anonymous_disabled' => [
            'en' => 'Anonymous reporting is not available. Choose another ID option.',
            'fr' => 'Signalement anonyme indisponible. Choisissez un autre identifiant.',
        ],
        'registration_required' => [
            'en' => 'Link your mobile number to a portal account or use trader ID options.',
            'fr' => 'Associez votre numero a un compte portail ou utilisez un identifiant commercant.',
        ],
    ],

];
