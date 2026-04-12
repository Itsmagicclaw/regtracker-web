<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin Configuration
    |--------------------------------------------------------------------------
    */
    'admin_email'  => env('ADMIN_EMAIL', 'vivek@remitso.com'),
    'admin_secret' => env('ADMIN_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Gemini API (QA Classification)
    |--------------------------------------------------------------------------
    */
    'gemini_api_key' => env('GEMINI_API_KEY', ''),
    'gemini_model'   => env('GEMINI_MODEL', 'gemini-1.5-flash'),
    'gemini_url'     => 'https://generativelanguage.googleapis.com/v1beta/models/',

    /*
    |--------------------------------------------------------------------------
    | Scraper Settings
    |--------------------------------------------------------------------------
    */
    'scraper_timeout' => (int) env('SCRAPER_TIMEOUT', 30),
    'scraper_retry'   => (int) env('SCRAPER_RETRY', 3),

    /*
    |--------------------------------------------------------------------------
    | QA Confidence Thresholds
    |--------------------------------------------------------------------------
    | auto_approve  : score >= this → auto-approved, sent to MTOs
    | hold_for_review: score >= this but < auto_approve → admin review queue
    | below hold_for_review → auto-discarded as noise
    */
    'confidence_thresholds' => [
        'auto_approve'    => (float) env('QA_AUTO_APPROVE_THRESHOLD', 80.0),
        'hold_for_review' => (float) env('QA_HOLD_THRESHOLD', 60.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert Severity → Notification Behaviour
    |--------------------------------------------------------------------------
    */
    'alert_behaviour' => [
        'critical' => 'instant',     // email immediately
        'high'     => 'instant',     // email immediately
        'medium'   => 'digest',      // daily digest
        'low'      => 'digest',      // daily digest
    ],

    /*
    |--------------------------------------------------------------------------
    | Regulatory Source URLs
    |--------------------------------------------------------------------------
    */
    'sources' => [
        'ofac_sdn'        => 'https://ofac.treasury.gov/downloads/sdn.xml',
        'uk_sanctions'    => 'https://sanctionslist.fcdo.gov.uk/docs/UK-Sanctions-List.xml',
        'un_sanctions'    => 'https://scsanctions.un.org/resources/xml/en/consolidated.xml',
        'eu_sanctions'    => 'https://webgate.ec.europa.eu/fsd/fsf/public/files/xmlFullSanctionsList_1_1/content',
        'dfat_sanctions'  => 'https://www.dfat.gov.au/sites/default/files/regulation/sanctions/consolidated.csv',
        'austrac_sitemap' => 'https://www.austrac.gov.au/sitemap.xml',
        'fca_rss'         => 'https://www.fca.org.uk/news/rss.xml',
        'fintrac_rss'     => 'https://www.fintrac-canafe.gc.ca/util/feed/newsen',
        'federal_register'=> 'https://www.federalregister.gov/api/v1/articles',
    ],

];
