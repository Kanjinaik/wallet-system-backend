<?php

return [
    'prepaid-postpaid' => [
        'prepaid' => [
            'Airtel' => env('BBPS_BILLER_MOBILE_PREPAID_AIRTEL', 'BILAVAIRTEL001'),
            'BSNL' => env('BBPS_BILLER_MOBILE_PREPAID_BSNL', ''),
            'Jio' => env('BBPS_BILLER_MOBILE_PREPAID_JIO', ''),
            'MTNL' => env('BBPS_BILLER_MOBILE_PREPAID_MTNL', ''),
            'Vi' => env('BBPS_BILLER_MOBILE_PREPAID_VI', ''),
        ],
        'postpaid' => [
            'Airtel' => env('BBPS_BILLER_MOBILE_POSTPAID_AIRTEL', ''),
            'BSNL' => env('BBPS_BILLER_MOBILE_POSTPAID_BSNL', ''),
            'Jio' => env('BBPS_BILLER_MOBILE_POSTPAID_JIO', ''),
            'MTNL' => env('BBPS_BILLER_MOBILE_POSTPAID_MTNL', ''),
            'Vi' => env('BBPS_BILLER_MOBILE_POSTPAID_VI', ''),
        ],
    ],
    'dth' => [
        'Tata Play (Formerly Tata Sky)' => env('BBPS_BILLER_DTH_TATA_PLAY', ''),
        'Airtel Digital TV' => env('BBPS_BILLER_DTH_AIRTEL_DIGITAL_TV', ''),
        'Sun Direct' => env('BBPS_BILLER_DTH_SUN_DIRECT', ''),
        'Dish TV' => env('BBPS_BILLER_DTH_DISH_TV', ''),
        'd2h' => env('BBPS_BILLER_DTH_D2H', ''),
    ],
    'electricity' => [
        'Andhra Pradesh Central Power Distribution Corporation LTD (APCPDCL)' => env('BBPS_BILLER_ELECTRICITY_APCPDCL', ''),
        'Andhra Pradesh Central Power (APCPDCL)' => env('BBPS_BILLER_ELECTRICITY_APCPDCL_ALT', ''),
        'TTD Electricity' => env('BBPS_BILLER_ELECTRICITY_TTD', ''),
        'Tirumala Tirupati Devasthanams (TTD)' => env('BBPS_BILLER_ELECTRICITY_TTD_ALT', ''),
    ],
    'metro' => [
        'Delhi Metro' => env('BBPS_BILLER_METRO_DELHI', ''),
        'Mumbai Metro' => env('BBPS_BILLER_METRO_MUMBAI', ''),
        'Hyderabad Metro' => env('BBPS_BILLER_METRO_HYDERABAD', ''),
        'Bengaluru Metro' => env('BBPS_BILLER_METRO_BENGALURU', ''),
    ],
    'broadband' => [
        'Airtel Xstream' => env('BBPS_BILLER_BROADBAND_AIRTEL_XSTREAM', ''),
        'JioFiber' => env('BBPS_BILLER_BROADBAND_JIOFIBER', ''),
        'BSNL Broadband' => env('BBPS_BILLER_BROADBAND_BSNL', ''),
        'ACT Fibernet' => env('BBPS_BILLER_BROADBAND_ACT', ''),
    ],
    'education' => [
        'School Fees' => env('BBPS_BILLER_EDUCATION_SCHOOL_FEES', ''),
        'College Fees' => env('BBPS_BILLER_EDUCATION_COLLEGE_FEES', ''),
        'Exam Fees' => env('BBPS_BILLER_EDUCATION_EXAM_FEES', ''),
        'Coaching Fees' => env('BBPS_BILLER_EDUCATION_COACHING_FEES', ''),
    ],
    'insurance' => [
        'LIC' => env('BBPS_BILLER_INSURANCE_LIC', ''),
        'HDFC Life' => env('BBPS_BILLER_INSURANCE_HDFC_LIFE', ''),
        'ICICI Prudential' => env('BBPS_BILLER_INSURANCE_ICICI_PRUDENTIAL', ''),
        'SBI Life' => env('BBPS_BILLER_INSURANCE_SBI_LIFE', ''),
    ],
    'pay-loan' => [
        'Bajaj Finance' => env('BBPS_BILLER_LOAN_BAJAJ_FINANCE', ''),
        'HDB Financial' => env('BBPS_BILLER_LOAN_HDB_FINANCIAL', ''),
        'TVS Credit' => env('BBPS_BILLER_LOAN_TVS_CREDIT', ''),
        'Tata Capital' => env('BBPS_BILLER_LOAN_TATA_CAPITAL', ''),
    ],
];
