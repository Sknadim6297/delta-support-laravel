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
    | Admin/Member panel shell theme. Server-only — never expose a public toggle.
    | lux = legacy Lux shell (rollback). v2 = new panel design system.
    */
    'panel_theme' => in_array(strtolower((string) env('PANEL_THEME', 'lux')), ['v2', 'next'], true)
        ? 'v2'
        : 'lux',

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
    |
    | Production codes:
    | - DST-ROI-002 (Digha Tour + Monthly Income): 0.2500 USDT daily for 800 days.
    |   Published package benefits match this approved coastal-tier plan.
    | - DST-TOUR-003 (3-Year Premium Tour + ROI): 2.5000 USDT daily for 1000 days.
    |   Commission pool 2.4000 for 400 days. Tour benefits stay unchanged.
    |   Income is forward-only from package_income_activations.activation_business_date.
    |   Duration stays anchored to each purchase's original first accrual (approval + wait);
    |   activation is a posting floor, not a restarted 1000/400-day window.
    |
    | DEMO-* codes remain for local/demo catalogs and tests.
    | Hill Station Retreat (DEMO-HILLS) mirrors the DST-TOUR-003 income schedule.
    */
    'package_income' => [
        // Legacy flag for package-income:accrue only. financial:daily uses FinancialControls / arm / Tour auth.
        'enabled' => false,
        'admin_root_id' => 'ST666666',
        'wait_days' => 10,
        'timezone' => 'Asia/Kolkata',
        'vip_commission_days' => $vipCommissionDays,
        'chunk_size' => 100,
        'schedule_time' => env('PACKAGE_INCOME_SCHEDULE_TIME', '00:10'),
        'activation_required_packages' => ['DST-TOUR-003'],
        /*
        | Separate from package_income_activations.activation_business_date.
        | Activation floor alone must not authorize Tour wallet posting.
        | Keep false until written approval of Tour terms and posting is recorded.
        */
        'tour_posting_authorized' => filter_var(
            env('DELTASUPPORT_TOUR_INCOME_POSTING_AUTHORIZED', false),
            FILTER_VALIDATE_BOOLEAN
        ),
        /*
        | Controlled historical recovery (financial:recover-historical).
        | Standard packages only. Tour codes are always refused for historical apply.
        */
        'historical_recovery' => [
            'allowed_packages' => ['DST-ROI-002'],
            'max_days' => 90,
        ],
        /*
        | Explicit Tour recovery (financial:recover-tour).
        | Separate from Standard historical recovery. Requires Tour posting auth,
        | activation floor, explicit dates, and per-date apply confirmation.
        | Never recovers dates before package_income_activations.activation_business_date.
        */
        'tour_recovery' => [
            'max_dates' => 7,
        ],
        'packages' => [
            'DST-ROI-002' => [
                'roi_daily' => '0.2500',
                'roi_days' => 800,
                'commission_pool' => '0.2400',
                'commission_days' => 400,
            ],
            'DST-TOUR-003' => [
                'roi_daily' => '2.5000',
                'roi_days' => 1000,
                'commission_pool' => '2.4000',
                'commission_days' => $vipCommissionDays,
            ],
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
        /*
        | Definition source policy for PackageIncomeDefinitionResolver.
        | legacy_config_preferred: codes present in packages{} above stay authoritative
        | until packages.income_source is explicitly switched to database after approval.
        | Database versions never silently replace legacy GOLD/PREMIUM accrual terms.
        */
        'definition_source_policy' => 'legacy_config_preferred',
    ],

    /*
    | Associate Commission Structure (Silver/Gold/Premium/VIP, 20 ranks, 400 days).
    | Separate from Daily ROI. posting_enabled and snapshot_on_approval stay false
    | until written owner approval. Existing accrual continues to use commission_pool
    | × percentage for DST-ROI-002 / DST-TOUR-003.
    */
    /*
    | Isolated member preview (Login as Member).
    | Separate cookie from the primary web session so Admin and Member tabs
    | can run concurrently without Auth::login(member) on the admin guard.
    */
    'member_preview' => [
        'cookie' => env('MEMBER_PREVIEW_COOKIE', 'delta_support_member_preview'),
        'lifetime_minutes' => (int) env('MEMBER_PREVIEW_LIFETIME', 120),
    ],

    'associate_commission' => [
        'active_version' => env('DELTASUPPORT_ASSOCIATE_COMMISSION_VERSION', 'associate_v1'),
        'posting_enabled' => filter_var(env('DELTASUPPORT_ASSOCIATE_COMMISSION_POSTING', false), FILTER_VALIDATE_BOOLEAN),
        'snapshot_on_approval' => filter_var(env('DELTASUPPORT_ASSOCIATE_COMMISSION_SNAPSHOT', false), FILTER_VALIDATE_BOOLEAN),
        /*
        | Verified production package-code mapping for associate_v1:
        | SILVER DST-TOUR-001 (50), GOLD DST-ROI-002 (100),
        | PREMIUM DST-TOUR-003 (500), VIP DST-TOUR-004 (1000).
        | Associate mapping is independent of package_income ROI terms.
        | DST-TOUR-003 may still carry legacy ROI 2.5000/1000 in package_income;
        | that ROI is NOT moved to DST-TOUR-004 by this map.
        */
        'tier_package_codes' => [
            'silver' => env('DELTASUPPORT_ASSOC_CODE_SILVER', 'DST-TOUR-001'),
            'gold' => env('DELTASUPPORT_ASSOC_CODE_GOLD', 'DST-ROI-002'),
            'premium' => env('DELTASUPPORT_ASSOC_CODE_PREMIUM', 'DST-TOUR-003'),
            'vip' => env('DELTASUPPORT_ASSOC_CODE_VIP', 'DST-TOUR-004'),
        ],
    ],

    /*
    | ROI and Working debits can be posted only after a later approval.
    | WalletLedgerPoster refuses while this stays false, including from .env.
    */
    'wallet_ledger' => [
        'enabled' => false,
    ],

    /*
    | Explicit opt-in for wallet-funded package purchase debits.
    | Default false and independent of transfer/withdrawal settings.
    */
    'wallet_package_purchase' => [
        'enabled' => filter_var(env('DELTASUPPORT_WALLET_PACKAGE_PURCHASE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    | Original Delta Support Tour & Travels package plan (PROPOSED / inactive).
    | Does not replace package_income.packages. Does not credit wallets.
    | Conversion basis INR 104 = 1 USDT is a proposed fixed business ratio,
    | not a live FX rate. Daily USDT rates stay unapproved until written decision.
    | New terms apply only to future purchases after explicit activation approval.
    */
    'original_tour_plan' => [
        'version_code' => 'original_tour_plan_v1',
        'status' => 'proposed',
        'active' => false,
        'applies_to_new_purchases' => false,
        /*
        | Candidate package_income rows for FUTURE purchases only.
        | Not merged into package_income.packages until written activation.
        | Historical approved purchases keep their existing snapshots/terms.
        */
        'candidate_package_income' => [
            'DST-TOUR-001' => [
                'roi_daily' => '0.1058',
                'roi_days' => 900,
                'commission_pool' => null,
                'commission_days' => null,
            ],
            'DST-ROI-002' => [
                'roi_daily' => '0.2404',
                'roi_days' => 800,
                'commission_pool' => '0.2400',
                'commission_days' => 400,
            ],
            'DST-TOUR-003' => [
                'roi_daily' => '1.3654',
                'roi_days' => 700,
                'commission_pool' => null,
                'commission_days' => null,
            ],
            'DST-TOUR-004' => [
                'roi_daily' => '3.2019',
                'roi_days' => 600,
                'commission_pool' => null,
                'commission_days' => null,
            ],
            'DST-TOUR-005' => [
                'roi_daily' => '16.8654',
                'roi_days' => 570,
                'commission_pool' => null,
                'commission_days' => null,
            ],
            // DST-RY-006 deliberately omitted: no daily ROI.
        ],
        'conversion' => [
            'basis_label' => 'Proposed fixed business ratio (not live FX)',
            'inr_per_usdt' => '104',
            'rounding_policy' => 'four_decimal_round_half_up_candidate',
            'notes' => 'Candidate rates below use 4dp round-half-up. Do not merge into package_income.packages until written approval. Final-day adjustments remain unapproved.',
        ],
        'packages' => [
            'SILVER' => [
                'proposed_package_code' => 'DST-TOUR-001',
                'existing_usdt_price' => '50.0000',
                'original_inr_price' => '5200',
                'original_inr_daily' => '11',
                'proposed_usdt_daily' => '0.1058',
                'roi_days' => 900,
                'has_daily_roi' => true,
                'tour' => [
                    'eligible_after_days' => 30,
                    'nights' => 1,
                    'days' => 2,
                    'adults' => 2,
                    'children' => 1,
                    'food_coupon_inr' => null,
                ],
            ],
            'GOLD' => [
                'proposed_package_code' => 'DST-ROI-002',
                'existing_usdt_price' => '100.0000',
                'original_inr_price' => '10400',
                'original_inr_daily' => '25',
                'proposed_usdt_daily' => '0.2404',
                'roi_days' => 800,
                'has_daily_roi' => true,
                'tour' => [
                    'eligible_after_days' => 30,
                    'nights' => 2,
                    'days' => 3,
                    'adults' => 2,
                    'children' => 1,
                    'food_coupon_inr' => null,
                ],
            ],
            'PREMIUM' => [
                'proposed_package_code' => 'DST-TOUR-003',
                'existing_usdt_price' => '500.0000',
                'original_inr_price' => '52000',
                'original_inr_daily' => '142',
                'proposed_usdt_daily' => '1.3654',
                'roi_days' => 700,
                'has_daily_roi' => true,
                'tour' => [
                    'eligible_after_days' => 30,
                    'nights' => 2,
                    'days' => 3,
                    'adults' => 2,
                    'children' => 1,
                    'food_coupon_inr' => null,
                ],
            ],
            'VIP' => [
                'proposed_package_code' => 'DST-TOUR-004',
                'existing_usdt_price' => '1000.0000',
                'original_inr_price' => '104000',
                'original_inr_daily' => '333',
                'proposed_usdt_daily' => '3.2019',
                'roi_days' => 600,
                'has_daily_roi' => true,
                'tour' => [
                    'eligible_after_days' => 30,
                    'nights' => 2,
                    'days' => 3,
                    'adults' => 2,
                    'children' => 1,
                    'food_coupon_inr' => '2000',
                ],
            ],
            'DIAMOND' => [
                'proposed_package_code' => 'DST-TOUR-005',
                'existing_usdt_price' => '5000.0000',
                'original_inr_price' => '520000',
                'original_inr_daily' => '1754',
                'proposed_usdt_daily' => '16.8654',
                'roi_days' => 570,
                'has_daily_roi' => true,
                'tour' => [
                    'eligible_after_days' => 30,
                    'nights' => 2,
                    'days' => 3,
                    'adults' => 2,
                    'children' => 1,
                    'food_coupon_inr' => '2000',
                ],
                'notes' => 'Production code DST-TOUR-005. Associate commission not configured for DIAMOND.',
            ],
            'DELTA_SHARE' => [
                'proposed_package_code' => 'DST-RY-006',
                'existing_usdt_price' => '50.0000',
                'original_inr_price' => '5200',
                'original_inr_daily' => null,
                'proposed_usdt_daily' => null,
                'roi_days' => null,
                'has_daily_roi' => false,
                'has_monthly_roi' => false,
                'tour' => [
                    'eligible_after_days' => 100,
                    'nights' => 1,
                    'days' => 2,
                    'adults' => 2,
                    'children' => 1,
                    'food_coupon_inr' => '1000',
                ],
                'chart_notes' => [
                    'Original chart mentions INR 25,000 withdrawal after 3 years.',
                    'Do not create a withdrawal obligation until USDT conversion, maturity, and funding policy are approved.',
                    'Keep roi_eligible=false and level_commission_eligible=false.',
                ],
            ],
        ],
    ],

    /*
    | Rewards campaign media (private local disk under rewards/).
    | Does not affect qualification or wallet posting.
    */
    'rewards' => [
        'image_max_kb' => (int) env('DELTASUPPORT_REWARDS_IMAGE_MAX_KB', 2048),
        'video_max_kb' => (int) env('DELTASUPPORT_REWARDS_VIDEO_MAX_KB', 204800), // 200 MB
        'video_mimes' => ['mp4', 'webm'],
        'video_mimetypes' => ['video/mp4', 'video/webm', 'application/mp4'],
    ],

    /*
    | Windows Plesk in-process scheduler (deltasupport:scheduler-tick).
    | Default mode is dry-run/verify. Financial Artisan jobs stay blocked until
    | allow_financial is explicitly enabled after written authorization.
    | recover is due at minute 25 Asia/Kolkata (UTC schedule listings may show :55).
    */
    'scheduler' => [
        'mode' => env('DELTASUPPORT_SCHEDULER_MODE', 'dry-run'),
        'allow_financial' => filter_var(env('DELTASUPPORT_SCHEDULER_ALLOW_FINANCIAL', false), FILTER_VALIDATE_BOOLEAN),
        // Relative to storage_path(). Readable in Plesk File Manager under storage/logs.
        'verification_log' => env('DELTASUPPORT_SCHEDULER_VERIFY_LOG', 'logs/scheduler-tick-verify.log'),
    ],
];


