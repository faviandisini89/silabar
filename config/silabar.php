<?php

return [
    /*
    | Python analytics service (FastAPI). Laravel only calls it; results are written
    | by the service straight into the shared database.
    */
    'analytics' => [
        'url' => env('ANALYTICS_URL', 'http://127.0.0.1:8001'),
        'token' => env('ANALYTICS_TOKEN', ''),
        'timeout' => (int) env('ANALYTICS_TIMEOUT', 180),
    ],

    'mail_notifications' => (bool) env('SILABAR_MAIL_NOTIFICATIONS', false),

    /*
    | Defaults for every setting an Admin can change in the UI (stored in the `settings` table).
    | The `procure.*` keys are also read by the Python service.
    */
    'settings' => [
        'loan.auto_approve' => true,
        'loan.auto_approve_trust' => 85,
        'loan.grace_hours' => 24,
        'penalty.late_per_day' => 2000,
        'penalty.damaged_pct' => 30,
        'procure.min_unmet' => 5,
        'procure.min_users' => 3,
        'procure.fill_below' => 80,
        'procure.window_days' => 30,
        'procure.maint_share' => 50,
        'procure.util_high' => 90,
        'procure.w_unmet' => 0.35,
        'procure.w_users' => 0.25,
        'procure.w_util' => 0.25,
        'procure.w_trend' => 0.15,
    ],

];
