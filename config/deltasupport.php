<?php

$vipCommissionDays = 400;

return [
    /*
    | Inherited investment, wallet transfer, and commission edits stay in the
    | codebase. They are closed in the running application until a later phase
    | explicitly enables them. Tests turn this on so existing coverage still runs.
    */
    'legacy_financial_actions' => filter_var(env('LEGACY_FINANCIAL_ACTIONS', false), FILTER_VALIDATE_BOOLEAN),

    /*
    | The only database financial:arm may accept. An empty value refuses every database.
    | Setting this name does not arm posting.
    */
    'financial_approved_database' => env('FINANCIAL_APPROVED_DATABASE', ''),

    /*
    | The web Root Admin seal accepts only this schema name.
    | Uploading a seal does not create the admin and does not arm posting.
    */
    'admin_bootstrap_database' => 'Delta_support_db',

    'report_timezone' => 'Asia/Kolkata',

    'report_pdf_row_limit' => 500,

    /*
    | Package ROI and 20-level commission stay off until a later approval.
    | The command records one business date only. It does not backfill.
    | Hill Station Retreat uses the VIP commission duration below.
    */
    'package_income' => [
        'enabled' => false,
        'admin_root_id' => 'ST666666',
        'wait_days' => 10,
        'timezone' => 'Asia/Kolkata',
        'vip_commission_days' => $vipCommissionDays,
        'chunk_size' => 100,
        'schedule_time' => '00:10',
        'packages' => [
            'DEMO-COAST' => [
                'roi_daily' => '0.2500',
                'roi_days' => 800,
                'commission_pool' => '0.2400',
                'commission_days' => 400,
            ],
            'DEMO-HILLS' => [
                'roi_daily' => '2.5000',
                'roi_days' => 1000,
                'commission_pool' => '2.4000',
                'commission_days' => $vipCommissionDays,
            ],
        ],
    ],

    /*
    | ROI and Working debits can be posted only after a later approval.
    | WalletLedgerPoster refuses while this stays false, including from .env.
    */
    'wallet_ledger' => [
        'enabled' => false,
    ],
];
