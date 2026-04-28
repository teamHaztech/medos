<?php

/**
 * Region-specific configuration for MedOS.
 * The system adapts based on hospital country code (IN, AE).
 */

return [

    // ---------------------------------------------------------------
    // INDIA
    // ---------------------------------------------------------------
    'IN' => [
        'name'     => 'India',
        'currency' => '₹',
        'currency_code' => 'INR',
        'phone_prefix' => '+91',
        'phone_digits' => 10,
        'timezone' => 'Asia/Kolkata',
        'date_format' => 'd/m/Y',
        'time_format' => 'h:i A',

        // Languages
        'languages' => [
            'en'  => ['name' => 'English',  'native' => 'English',   'dir' => 'ltr'],
            'hi'  => ['name' => 'Hindi',    'native' => 'हिंदी',     'dir' => 'ltr'],
            'mr'  => ['name' => 'Marathi',  'native' => 'मराठी',     'dir' => 'ltr'],
            'kok' => ['name' => 'Konkani',  'native' => 'कोंकणी',    'dir' => 'ltr'],
            'ta'  => ['name' => 'Tamil',    'native' => 'தமிழ்',     'dir' => 'ltr'],
            'te'  => ['name' => 'Telugu',   'native' => 'తెలుగు',    'dir' => 'ltr'],
            'kn'  => ['name' => 'Kannada',  'native' => 'ಕನ್ನಡ',     'dir' => 'ltr'],
            'bn'  => ['name' => 'Bengali',  'native' => 'বাংলা',     'dir' => 'ltr'],
            'gu'  => ['name' => 'Gujarati', 'native' => 'ગુજરાતી',   'dir' => 'ltr'],
            'ml'  => ['name' => 'Malayalam','native' => 'മലയാളം',    'dir' => 'ltr'],
        ],
        'default_language' => 'en',
        'voice_languages' => ['en', 'hi', 'mr', 'kok'], // For room display announcements

        // Health ID System
        'health_id' => [
            'system'      => 'ABHA',
            'full_name'   => 'Ayushman Bharat Health Account',
            'authority'   => 'ABDM (Ayushman Bharat Digital Mission)',
            'format'      => '14-digit (XX-XXXX-XXXX-XX)',
            'digits'      => 14,
            'enabled'     => true,
            'field_label' => 'ABHA Number',
            'field_label_local' => 'आभा नंबर',
            'api_base'    => 'https://healthidsbx.abdm.gov.in/api', // sandbox
        ],

        // Insurance
        'insurance' => [
            'system'    => 'PMJAY + TPA',
            'providers' => ['PMJAY', 'Star Health', 'ICICI Lombard', 'MediAssist', 'Paramount', 'Vidal Health'],
            'id_label'  => 'Insurance / PMJAY Card',
            'claim_format' => 'TPA',
        ],

        // Payment
        'payment_methods' => ['UPI', 'Cash', 'Card', 'NetBanking'],
        'tax_name'  => 'GST',
        'tax_rate'  => 18, // percent, if applicable

        // Emergency
        'emergency_number' => '108',

        // Compliance
        'regulations' => ['DPDP Act 2023', 'IT Act 2000', 'NMC Guidelines', 'ABDM Standards'],
    ],

    // ---------------------------------------------------------------
    // UAE (Dubai, Abu Dhabi)
    // ---------------------------------------------------------------
    'AE' => [
        'name'     => 'UAE',
        'currency' => 'AED',
        'currency_code' => 'AED',
        'phone_prefix' => '+971',
        'phone_digits' => 9,
        'timezone' => 'Asia/Dubai',
        'date_format' => 'd/m/Y',
        'time_format' => 'h:i A',

        // Languages
        'languages' => [
            'en' => ['name' => 'English', 'native' => 'English',  'dir' => 'ltr'],
            'ar' => ['name' => 'Arabic',  'native' => 'العربية',  'dir' => 'rtl'],
        ],
        'default_language' => 'en',
        'voice_languages' => ['en', 'ar'],

        // Health ID System
        'health_id' => [
            'system'      => 'Emirates ID',
            'full_name'   => 'Emirates Identity Card',
            'authority'   => 'Federal Authority for Identity & Citizenship (ICA)',
            'format'      => '15-digit (784-XXXX-XXXXXXX-X)',
            'digits'      => 15,
            'enabled'     => true,
            'field_label' => 'Emirates ID',
            'field_label_local' => 'هوية إماراتية',
            'api_base'    => null,
        ],

        // Insurance (mandatory in UAE)
        'insurance' => [
            'system'    => 'DHA / DoH',
            'providers' => ['Daman', 'NAS', 'Oman Insurance', 'AXA', 'Cigna', 'MetLife', 'Allianz'],
            'id_label'  => 'Insurance Card',
            'claim_format' => 'DHA e-Claims',
        ],

        // Payment
        'payment_methods' => ['Card', 'Cash', 'Apple Pay', 'Insurance'],
        'tax_name'  => 'VAT',
        'tax_rate'  => 5,

        // Emergency
        'emergency_number' => '999',

        // Compliance
        'regulations' => ['PDPL (Federal Decree-Law No. 45)', 'DHA Data Governance', 'HAAD Standards', 'NABIDH'],
    ],
];
