<?php

return [
    /*
    | Accelerated stress-testing mode. It replaces the three independent production
    | financial schedules with one coordinated cycle (ROI -> Level Commission -> Rank)
    | that walks forward through simulated business dates. Never enable in production.
    */
    'accelerated' => [
        'enabled' => (bool) env('FINANCIAL_ACCELERATED_TESTING', false),
        'interval_minutes' => 10,
        // First simulated business date; defaults to the day after the earliest investment.
        'start_date' => env('FINANCIAL_ACCELERATED_START_DATE'),
        // Every member must carry this id prefix, otherwise the cycle refuses to run.
        'synthetic_member_prefix' => env('FINANCIAL_SYNTHETIC_MEMBER_PREFIX', 'ST'),
        'checkpoint_path' => storage_path('app/financial-cycle/checkpoint.json'),
        'lock_path' => storage_path('framework/financial-cycle.lock'),
        'log_path' => storage_path('logs/financial-cycle.log'),
    ],
];